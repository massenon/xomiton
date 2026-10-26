<?php

declare(strict_types=1);

use App\Application\Newsletter\Campaigns\CampaignSender;
use App\Application\Newsletter\Campaigns\ProcessCampaignQueue;
use App\Application\Newsletter\NewsletterLinks;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendCampaignBatch;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\Mailer\Exception\TransportException;

/*
 * P14-03 : envoi des campagnes par lots (200 par minute), personnalisation, liens de suivi signés HMAC, pixel,
 * List-Unsubscribe / List-Unsubscribe-Post, désabonné·es exclu·es, rebonds, jamais deux envois à la même personne.
 */

function dueCampaign(int $subscribers = 3, array $campaign = []): NewsletterCampaign
{
    NewsletterSubscriber::factory()->count($subscribers)->create();

    return NewsletterCampaign::factory()->due()->create($campaign + [
        'body' => '<p>Bonjour {{user_first_name}}</p><p><a href="https://exemple.org/page?a=1&amp;b=2">Lire</a> <a href="mailto:a@b.bj">Écrire</a></p>',
    ]);
}

it('envoie une campagne par lots de 200 par minute puis la termine', function (): void {
    Mail::fake();
    $campaign = dueCampaign(250);

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sending)
        ->and($campaign->recipients_count)->toBe(250)
        ->and($campaign->recipients()->whereNotNull('sent_at')->count())->toBe(200);
    Mail::assertSent(TemplateMail::class, 200);

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $campaign->refresh();
    expect($campaign->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->sent_count)->toBe(250)
        ->and($campaign->sent_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'newsletter.campaign_sent')->count())->toBe(1);
    Mail::assertSent(TemplateMail::class, 250);
});

it('ne vise que les abonné·es actifs : en attente, désabonnés et rebonds sont exclus (RG-11, RG-12)', function (): void {
    Mail::fake();
    NewsletterSubscriber::factory()->pending()->create();
    NewsletterSubscriber::factory()->unsubscribed()->create();
    NewsletterSubscriber::factory()->bounced()->create();
    $campaign = dueCampaign(2);

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    expect($campaign->refresh()->recipients_count)->toBe(2)
        ->and($campaign->recipients()->count())->toBe(2);
    Mail::assertSent(TemplateMail::class, 2);
});

it('écarte une personne qui se désabonne après la constitution de la liste', function (): void {
    Mail::fake();
    $campaign = dueCampaign(2);
    app(CampaignSender::class)->start($campaign);
    $left = NewsletterSubscriber::query()->first();
    $left->unsubscribe();

    $result = app(CampaignSender::class)->sendBatch($campaign->refresh());

    expect($result['sent'])->toBe(1)->and($result['skipped'])->toBe(1);
    Mail::assertSent(TemplateMail::class, 1);
    Mail::assertNotSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo($left->email));
    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sent)->and($campaign->sent_count)->toBe(1);
});

it('n’envoie jamais deux fois à la même personne pour une campagne', function (): void {
    Mail::fake();
    $campaign = dueCampaign(3);
    app(CampaignSender::class)->start($campaign);
    $campaign->refresh();

    // Deux lots qui se chevauchent, puis un passage après la fin.
    app(CampaignSender::class)->sendBatch($campaign);
    $campaign->forceFill(['status' => CampaignStatus::Sending])->save();
    app(CampaignSender::class)->sendBatch($campaign);
    app(ProcessCampaignQueue::class)->run(sync: true);

    Mail::assertSent(TemplateMail::class, 3);
    expect(fn () => NewsletterCampaignRecipient::query()->create(['campaign_id' => $campaign->id, 'subscriber_id' => NewsletterSubscriber::query()->first()->id]))
        ->toThrow(Illuminate\Database\QueryException::class);
});

it('réserve le destinataire avant l’envoi : une ligne déjà envoyée n’est pas reprise', function (): void {
    Mail::fake();
    $campaign = dueCampaign(2);
    app(CampaignSender::class)->start($campaign);
    $campaign->recipients()->first()->forceFill(['sent_at' => now()])->save();

    app(CampaignSender::class)->sendBatch($campaign->refresh());

    Mail::assertSent(TemplateMail::class, 1);
});

it('personnalise le message et pose liens de suivi signés, pixel et en-têtes de désinscription', function (): void {
    Mail::fake();
    $subscriber = NewsletterSubscriber::factory()->create(['name' => 'Awa Houngbédji']);
    $campaign = NewsletterCampaign::factory()->due()->create(['body' => '<p>Bonjour {{user_first_name}}</p><p><a href="https://exemple.org/page?a=1&amp;b=2">Lire</a> <a href="mailto:a@b.bj">Écrire</a></p>']);

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $links = app(NewsletterLinks::class);
    $recipient = NewsletterCampaignRecipient::query()->firstOrFail();

    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use ($subscriber, $recipient, $links): bool {
        preg_match('#/newsletter/clic/(\d+)/([a-f0-9]{32})\?url=([^"]+)#', $mail->htmlBody, $click);
        preg_match('#/newsletter/ouverture/(\d+)/([a-f0-9]{32})#', $mail->htmlBody, $open);
        $target = html_entity_decode(urldecode(html_entity_decode($click[3] ?? '')));

        return $mail->hasTo($subscriber->email)
            && str_contains($mail->htmlBody, 'Bonjour Awa')
            && str_contains($mail->htmlBody, 'mailto:a@b.bj')
            && $target === 'https://exemple.org/page?a=1&b=2'
            && $links->verifyClick($recipient->id, $target, $click[2])
            && $links->verifyOpen($recipient->id, $open[2])
            && $mail->extraHeaders['List-Unsubscribe'] === '<'.$links->unsubscribeUrl($subscriber, $recipient->campaign_id).'>'
            && $mail->extraHeaders['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click'
            && str_contains($mail->textBody, '/newsletter/desinscription/'.$subscriber->token);
    });
});

it('désinscrit en un clic par l’adresse List-Unsubscribe (POST sans jeton CSRF) et marque le destinataire', function (): void {
    Mail::fake();
    $subscriber = NewsletterSubscriber::factory()->create();
    $campaign = NewsletterCampaign::factory()->due()->create();
    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $url = trim(app(NewsletterLinks::class)->unsubscribeHeaders($subscriber, $campaign->id)['List-Unsubscribe'], '<>');

    $this->post($url, ['List-Unsubscribe' => 'One-Click'])->assertOk();

    expect($subscriber->refresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and(NewsletterCampaignRecipient::query()->where('subscriber_id', $subscriber->id)->value('unsubscribed'))->toBeTruthy();
});

it('refuse un lien de clic à signature invalide (403) et n’enregistre rien', function (): void {
    $recipient = NewsletterCampaignRecipient::factory()->sent()->create();
    $bad = str_repeat('0', 32);

    $this->get('/newsletter/clic/'.$recipient->id.'/'.$bad.'?url='.urlencode('https://exemple.org'))->assertForbidden();
    // Cible modifiée : la signature ne couvre plus l'URL.
    $links = app(NewsletterLinks::class);
    $signed = $links->clickUrl($recipient, 'https://exemple.org/a');
    $this->get(str_replace(urlencode('https://exemple.org/a'), urlencode('https://autre.example/'), $signed))->assertForbidden();

    $this->get('/newsletter/ouverture/'.$recipient->id.'/'.$bad)->assertOk()->assertHeader('Content-Type', 'image/gif');
    expect($recipient->refresh()->opened_at)->toBeNull();

    $this->get($signed)->assertRedirect('https://exemple.org/a');
    expect($recipient->refresh()->opened_at)->not->toBeNull();
});

function failingMailer(int $code): void
{
    $pending = Mockery::mock(PendingMail::class);
    $pending->shouldReceive('send')->andThrow(new TransportException(sprintf('Expected response code "250" but got code "%d", with message "%d unknown awa@exemple.bj".', $code, $code)));
    $mailer = Mockery::mock(Illuminate\Contracts\Mail\Mailer::class);
    $mailer->shouldReceive('to')->andReturn($pending);
    Mail::shouldReceive('mailer')->andReturn($mailer);
}

it('traite un refus définitif du serveur comme un rebond : l’adresse n’est plus ciblée et rien ne fuit dans les journaux', function (): void {
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'awa@exemple.bj']);
    $campaign = NewsletterCampaign::factory()->due()->create();
    failingMailer(550);

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $recipient = NewsletterCampaignRecipient::query()->firstOrFail();
    expect($recipient->bounced)->toBeTrue()
        ->and($recipient->error)->toBe('smtp_550')
        ->and($subscriber->refresh()->status)->toBe(SubscriberStatus::Bounced)
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Sent);

    $logged = AuditLog::query()->get(['action', 'detail'])->toJson().MailLog::query()->get()->toJson();
    expect($logged)->not->toContain('awa@exemple.bj')->not->toContain('exemple.bj');

    // Une nouvelle campagne ne cible plus cette adresse.
    $next = NewsletterCampaign::factory()->create();
    expect($next->targetQuery()->count())->toBe(0);
});

it('rejoue un refus temporaire aux lots suivants, trois essais au plus', function (): void {
    NewsletterSubscriber::factory()->create();
    $campaign = NewsletterCampaign::factory()->due()->create();
    failingMailer(451);

    foreach (range(1, 3) as $pass) {
        $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();
    }

    $recipient = NewsletterCampaignRecipient::query()->firstOrFail();
    expect($recipient->attempts)->toBe(3)
        ->and($recipient->error)->toBe('smtp_451')
        ->and($recipient->sent_at)->toBeNull()
        ->and($recipient->bounced)->toBeFalse()
        ->and($campaign->refresh()->status)->toBe(CampaignStatus::Sent)
        ->and($campaign->sent_count)->toBe(0);
});

it('dépose l’e-mail dans la boîte d’envoi, avec ses en-têtes, quand aucun transport n’est disponible', function (): void {
    config(['mail.default' => 'outbox']);
    $subscriber = NewsletterSubscriber::factory()->create();
    NewsletterCampaign::factory()->due()->create();

    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $mail = MailOutbox::query()->firstOrFail();
    expect($mail->to)->toBe($subscriber->email)
        ->and($mail->headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click')
        ->and($mail->newsletter_recipient_id)->toBe(NewsletterCampaignRecipient::query()->value('id'))
        ->and($mail->template_slug)->toBe('newsletter');
});

it('démarre seulement les campagnes échues et dépose un lot de 200 au plus par minute dans la file', function (): void {
    Queue::fake();
    NewsletterSubscriber::factory()->count(5)->create();
    $later = NewsletterCampaign::factory()->scheduled(now()->addDay())->create();
    $due = NewsletterCampaign::factory()->due()->create();

    $this->artisan('newsletter:process')->assertSuccessful();

    expect($later->refresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($due->refresh()->status)->toBe(CampaignStatus::Sending);
    Queue::assertPushed(SendCampaignBatch::class, fn (SendCampaignBatch $job): bool => $job->campaignId === $due->id && $job->limit === 5);
    Queue::assertPushed(SendCampaignBatch::class, 1);
});

it('plafonne le budget de la minute à 200 e-mails pour l’ensemble des campagnes', function (): void {
    Queue::fake();
    NewsletterSubscriber::factory()->count(150)->create();
    $first = NewsletterCampaign::factory()->due()->create(['scheduled_at' => now()->subHour()]);
    $second = NewsletterCampaign::factory()->due()->create(['scheduled_at' => now()->subMinutes(30)]);

    app(ProcessCampaignQueue::class)->run();

    Queue::assertPushed(SendCampaignBatch::class, fn (SendCampaignBatch $job): bool => $job->campaignId === $first->id && $job->limit === 150);
    Queue::assertPushed(SendCampaignBatch::class, fn (SendCampaignBatch $job): bool => $job->campaignId === $second->id && $job->limit === 50);
});

it('reporte sur le destinataire un refus définitif constaté au renvoi de la boîte d’envoi, sans adresse dans les journaux', function (): void {
    config(['mail.default' => 'outbox']);
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'chantal@exemple.bj']);
    $campaign = NewsletterCampaign::factory()->due()->create();
    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    config(['mail.default' => 'smtp']);
    Mail::shouldReceive('html')->once()->andThrow(new TransportException('Expected response code "250" but got code "550", with message "550 chantal@exemple.bj unknown".'));
    $this->artisan('mail:flush-outbox')->assertFailed();

    $recipient = NewsletterCampaignRecipient::query()->firstOrFail();
    $mail = MailOutbox::query()->firstOrFail();
    expect($recipient->bounced)->toBeTrue()
        ->and($subscriber->refresh()->status)->toBe(SubscriberStatus::Bounced)
        ->and($mail->last_error)->toBe('smtp_550')
        ->and($mail->attempts)->toBe(MailOutbox::MAX_ATTEMPTS)
        ->and(MailLog::query()->get()->toJson())->not->toContain('chantal@exemple.bj');
});

it('n’envoie rien à une personne dont le compte est suspendu, supprimé ou anonymisé', function (): void {
    Mail::fake();
    $suspended = NewsletterSubscriber::factory()->create(['user_id' => App\Models\User::factory()->suspended()->create()->id]);
    $deleted = NewsletterSubscriber::factory()->create(['user_id' => tap(App\Models\User::factory()->create())->delete()->id]);
    $anonymized = NewsletterSubscriber::factory()->create(['user_id' => App\Models\User::factory()->create(['email' => 'supprime-9@anonyme.invalid'])->id]);
    $ok = NewsletterSubscriber::factory()->create(['user_id' => App\Models\User::factory()->create()->id]);
    $campaign = NewsletterCampaign::factory()->due()->create();

    // Constitution de la liste : seule la personne joignable est retenue.
    app(CampaignSender::class)->start($campaign);
    expect($campaign->refresh()->recipients_count)->toBe(1);

    // Suspension après la constitution : écartée au moment de l'envoi.
    App\Models\User::query()->whereKey($ok->user_id)->update(['status' => App\Enums\UserStatus::Suspended->value]);
    $result = app(CampaignSender::class)->sendBatch($campaign);

    expect($result['sent'])->toBe(0)->and($result['skipped'])->toBe(1);
    Mail::assertNothingSent();
    expect([$suspended, $deleted, $anonymized])->toHaveCount(3);
});

it('ne compte pas les rebonds parmi les envois réussis', function (): void {
    Mail::fake();
    $campaign = dueCampaign(2);
    app(CampaignSender::class)->start($campaign);
    $campaign->refresh();
    app(CampaignSender::class)->sendBatch($campaign, 1);
    $bounced = $campaign->recipients()->whereNotNull('sent_at')->firstOrFail();
    $bounced->forceFill(['bounced' => true])->save();
    app(CampaignSender::class)->sendBatch($campaign->refresh());

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sent)->and($campaign->sent_count)->toBe(1);
});

it('ne renvoie pas depuis la boîte d’envoi à une personne désabonnée depuis le dépôt, sans objet personnalisé dans les journaux', function (): void {
    config(['mail.default' => 'outbox']);
    $subscriber = NewsletterSubscriber::factory()->create();
    NewsletterCampaign::factory()->due()->create();
    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();
    $subscriber->unsubscribe();

    config(['mail.default' => 'smtp']);
    Mail::shouldReceive('html')->never();
    $this->artisan('mail:flush-outbox')->assertFailed();

    expect(MailOutbox::query()->firstOrFail()->last_error)->toBe('exclu');
});

it('n’expose ni en-têtes ni corps de la boîte d’envoi à la sérialisation', function (): void {
    config(['mail.default' => 'outbox']);
    NewsletterSubscriber::factory()->create();
    NewsletterCampaign::factory()->due()->create();
    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();

    $json = MailOutbox::query()->firstOrFail()->toJson();

    expect($json)->not->toContain('List-Unsubscribe')->not->toContain('/newsletter/ouverture/')->not->toContain('/newsletter/desinscription/');
});
