<?php

declare(strict_types=1);

use App\Enums\MailTransport;
use App\Models\MailLog;
use App\Models\MailOutbox;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * `mail:flush-outbox` (P1-16, P3-01) : envoi des e-mails en attente avec le mailer par défaut quand il
 * soit (y compris `log` et `array`, B1) ; tentatives et dernière erreur tenues ; 50 au plus par exécution.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
});

/** Mailer « réel » du point de vue de la commande, mais capturé en mémoire par le transport array. */
function useCapturingMailer(): void
{
    config([
        'mail.mailers.capture' => ['transport' => 'array'],
        'mail.default' => 'capture',
    ]);
}

it('passe par le mailer configuré même pour log ou array (B1) : le contenu atteint le transport', function (string $mailer): void {
    config(['mail.default' => $mailer]);
    $pending = MailOutbox::factory()->create(['html' => '<p>Contenu-B1-visible</p>']);

    $this->artisan('mail:flush-outbox')->assertSuccessful();

    expect($pending->fresh()->sent_at)->not->toBeNull();

    if ($mailer === 'array') {
        expect(Mail::mailer('array')->getSymfonyTransport()->messages())->toHaveCount(1)
            ->and((string) Mail::mailer('array')->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody())->toContain('Contenu-B1-visible');
    }
})->with(['log', 'array']);

it('envoie les e-mails en attente dans l’ordre de dépôt, les marque envoyés et les journalise', function (): void {
    useCapturingMailer();

    $first = MailOutbox::factory()->create(['to' => 'awa@example.test', 'subject' => 'Bienvenue', 'text' => 'Bonjour Awa', 'template_slug' => 'bienvenue']);
    $second = MailOutbox::factory()->create(['to' => 'chantal@example.test', 'reply_to' => null, 'text' => null]);
    $alreadySent = MailOutbox::factory()->sent()->create();
    $exhausted = MailOutbox::factory()->exhausted()->create();

    $this->artisan('mail:flush-outbox')
        ->expectsOutputToContain('2 e-mails envoyés via « capture », 0 échec ; 0 e-mail encore en attente')
        ->assertSuccessful();

    $messages = Mail::getSymfonyTransport()->messages();
    $firstMessage = $messages->first()->getOriginalMessage();

    expect($messages)->toHaveCount(2)
        ->and($firstMessage->getTo()[0]->getAddress())->toBe('awa@example.test')
        ->and($firstMessage->getSubject())->toBe('Bienvenue')
        ->and($firstMessage->getFrom()[0]->getAddress())->toBe('no-reply@xomiton.bj')
        ->and($firstMessage->getFrom()[0]->getName())->toBe('Xomiton')
        ->and($firstMessage->getReplyTo()[0]->getAddress())->toBe('contact@xomiton.bj')
        ->and($firstMessage->getHtmlBody())->toBe($first->html)
        ->and($firstMessage->getTextBody())->toBe('Bonjour Awa')
        ->and($messages->last()->getOriginalMessage()->getTo()[0]->getAddress())->toBe('chantal@example.test');

    expect($first->fresh()->sent_at)->not->toBeNull()
        ->and($first->fresh()->last_error)->toBeNull()
        ->and($second->fresh()->sent_at)->not->toBeNull()
        ->and($alreadySent->fresh()->attempts)->toBe(1)
        ->and($exhausted->fresh()->sent_at)->toBeNull()
        ->and($exhausted->fresh()->attempts)->toBe(MailOutbox::MAX_ATTEMPTS);

    $log = MailLog::query()->where('to', 'awa@example.test')->first();

    expect(MailLog::query()->count())->toBe(2)
        ->and($log->ok)->toBeTrue()
        ->and($log->transport)->toBe(MailTransport::Smtp)
        ->and($log->context)->toBe('outbox')
        ->and($log->template_slug)->toBe('bienvenue');

    // Deuxième passage : plus rien à envoyer, rien n'est renvoyé.
    $this->artisan('mail:flush-outbox')
        ->expectsOutputToContain('Boîte d’envoi vide')
        ->assertSuccessful();

    expect(Mail::getSymfonyTransport()->messages())->toHaveCount(2);
});

it('respecte la limite par exécution', function (): void {
    useCapturingMailer();
    MailOutbox::factory()->count(3)->create();

    $this->artisan('mail:flush-outbox', ['--limit' => 2])
        ->expectsOutputToContain('2 e-mails envoyés via « capture », 0 échec ; 1 e-mail encore en attente')
        ->assertSuccessful();

    expect(MailOutbox::query()->pending()->count())->toBe(1);

    $this->artisan('mail:flush-outbox', ['--limit' => 0])->assertFailed();
});

it('compte la tentative et conserve l’erreur quand l’envoi échoue', function (): void {
    config([
        'mail.mailers.injoignable' => ['transport' => 'smtp', 'host' => '127.0.0.1', 'port' => 1, 'timeout' => 1],
        'mail.default' => 'injoignable',
    ]);
    $mail = MailOutbox::factory()->create();

    $this->artisan('mail:flush-outbox')
        ->expectsOutputToContain('0 e-mail envoyé via « injoignable », 1 échec ; 1 e-mail encore en attente')
        ->assertFailed();

    $mail->refresh();

    expect($mail->sent_at)->toBeNull()
        ->and($mail->attempts)->toBe(1)
        ->and($mail->last_error)->not->toBeNull()
        ->and(mb_strlen($mail->last_error))->toBeLessThanOrEqual(255)
        ->and(MailLog::query()->failed()->count())->toBe(1);
});

it('Q57 : après un envoi réussi, plus aucun corps, texte, en-tête ni lien signé en base', function (): void {
    useCapturingMailer();
    $mail = MailOutbox::factory()->create([
        'to' => 'awa@example.test',
        'html' => '<a href="https://xomiton.test/signed?signature=abc123">Lien</a>',
        'text' => 'https://xomiton.test/signed?signature=abc123',
        'headers' => ['List-Unsubscribe' => '<https://xomiton.test/desabonnement?signature=abc123>'],
        'template_slug' => 'otp',
    ]);

    $this->artisan('mail:flush-outbox')->assertSuccessful();

    $row = DB::table('mail_outbox')->where('id', $mail->id)->first();
    expect($row->html)->toBe('')->and($row->text)->toBeNull()->and($row->headers)->toBeNull()
        ->and($row->to)->not->toContain('awa@example.test')->and($row->to)->toStartWith('hmac:')
        ->and($row->template_slug)->toBe('otp')->and($row->sent_at)->not->toBeNull()
        ->and(json_encode($row))->not->toContain('abc123');
    expect(MailLog::query()->where('to', 'awa@example.test')->exists())->toBeTrue();
});

it('Q57 : un e-mail en échec garde son contenu au plus N jours, puis mail:purge-logs le supprime', function (): void {
    config(['xomiton.retention.mail_outbox_failed_days' => 7]);
    $old = MailOutbox::factory()->exhausted()->create(['created_at' => now()->subDays(8)]);
    $recent = MailOutbox::factory()->exhausted()->create(['created_at' => now()->subDays(3)]);

    $this->artisan('mail:purge-logs')->assertSuccessful();

    expect(MailOutbox::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(MailOutbox::query()->whereKey($recent->id)->exists())->toBeTrue();
});
