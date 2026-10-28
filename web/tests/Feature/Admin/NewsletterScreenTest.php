<?php

declare(strict_types=1);

use App\Application\Admin\Newsletter\NewsletterStatistics;
use App\Enums\CampaignStatus;
use App\Enums\SubscriberStatus;
use App\Jobs\SendCampaignBatch;
use App\Livewire\Admin\Newsletter\Index as NewsletterScreen;
use App\Models\AuditLog;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\NewsletterTaxonomySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

/*
 * P14-02 : /admin/newsletter (Livewire) — KPI, statistiques (taux et alerte sous la cible), abonné·es (liste, filtres,
 * actions groupées, import CSV attesté, suppression RGPD), campagnes (RG-11, RG-12, campagne en cours verrouillée,
 * programmation, test par la boîte d'envoi). Aucun SMTP dans la requête.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, NewsletterTaxonomySeeder::class, EmailTemplatesSeeder::class]);
});

function nlUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function nlScreen(string $role = 'admin', ?string $tab = null)
{
    test()->actingAs(nlUser($role));

    return Livewire::test(NewsletterScreen::class, $tab === null ? [] : ['tab' => $tab]);
}

function campaignForm(array $override = []): array
{
    return $override + [
        'subject' => 'Les nouvelles du mois',
        'from_name' => 'Xomiton',
        'reply_to' => 'contact@xomiton.bj',
        'preheader' => 'Au sommaire',
        'body' => '<p>Bonjour {{user_first_name}}, voici les nouvelles de la communauté.</p><script>x()</script>',
        'seg_tags' => [],
        'seg_interests' => [],
        'seg_since' => '',
        'seg_until' => '',
        'mode' => 'draft',
        'scheduled_at' => now()->addDay()->format('Y-m-d\TH:i'),
    ];
}

it('réserve /admin/newsletter à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(nlUser($role))->get('/admin/newsletter')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('affiche les KPI permanents, les cibles et l’onglet Statistiques', function (): void {
    NewsletterSubscriber::factory()->count(2)->create();
    NewsletterSubscriber::factory()->pending()->create();
    $this->actingAs(nlUser())->get('/admin/newsletter')->assertOk()->assertSee('Abonné·es actifs')->assertSee('Taux d’ouverture')->assertSee('Statistiques');

    nlScreen()->assertSee('Cible')->assertSee('Inscriptions');
});

it('calcule les taux et signale ceux du mauvais côté de leur cible', function (): void {
    $campaign = NewsletterCampaign::factory()->sent()->create();
    $subscribers = NewsletterSubscriber::factory()->count(10)->create();
    foreach ($subscribers as $i => $subscriber) {
        $recipient = NewsletterCampaignRecipient::factory()->forCampaign($campaign)->forSubscriber($subscriber)->sent()->create([
            'opened_at' => $i < 4 ? now() : null,
            'bounced' => $i === 9,
            'unsubscribed' => $i === 8,
        ]);
        if ($i < 2) {
            NewsletterClick::factory()->create(['recipient_id' => $recipient->id]);
        }
    }

    $totals = app(NewsletterStatistics::class)->totals();

    // 10 envoyés, 1 rebond → 9 délivrés ; 4 ouvertures (dont 0 rebond) ; 2 clics ; 1 désabonnement.
    expect($totals['sent'])->toBe(10)->and($totals['delivered'])->toBe(9)
        ->and($totals['open_rate'])->toBe(44.4)->and($totals['click_rate'])->toBe(22.2)
        ->and($totals['unsubscribe_rate'])->toBe(11.1)->and($totals['bounce_rate'])->toBe(10.0)
        ->and(NewsletterStatistics::belowTarget('open', 44.4, 9))->toBeFalse()
        ->and(NewsletterStatistics::belowTarget('click', 22.2, 9))->toBeFalse()
        ->and(NewsletterStatistics::belowTarget('unsubscribe', 11.1, 9))->toBeTrue()
        ->and(NewsletterStatistics::belowTarget('bounce', 10.0, 10))->toBeTrue()
        ->and(NewsletterStatistics::belowTarget('open', 12.0, 9))->toBeTrue()
        ->and(NewsletterStatistics::belowTarget('open', 0.0, 0))->toBeFalse();

    nlScreen()->assertSee('Sous la cible')->assertSee('44,4');
});

it('liste, filtre et recherche les abonné·es', function (): void {
    NewsletterSubscriber::factory()->create(['email' => 'alpha@exemple.bj']);
    NewsletterSubscriber::factory()->pending()->create(['email' => 'beta@exemple.bj']);
    NewsletterSubscriber::factory()->create(['email' => 'gamma@exemple.bj'])->syncTags(['presse']);

    nlScreen('admin', 'abonnes')->assertSee('alpha@exemple.bj')->assertSee('beta@exemple.bj')
        ->set('status', 'en_attente')->assertSee('beta@exemple.bj')->assertDontSee('alpha@exemple.bj')
        ->set('status', '')->set('tagFilter', 'presse')->assertSee('gamma@exemple.bj')->assertDontSee('alpha@exemple.bj')
        ->set('tagFilter', '')->set('search', 'alp')->assertSee('alpha@exemple.bj')->assertDontSee('gamma@exemple.bj');
});

it('ajoute un·e abonné·e (double opt-in par la boîte d’envoi) et refuse une adresse déjà inscrite', function (): void {
    Mail::fake();

    nlScreen('admin', 'abonnes')->call('newSubscriber')
        ->set('subForm.email', 'Nouvelle@Exemple.bj')->set('subForm.name', 'Awa')->set('subForm.status', 'en_attente')->set('subForm.send_confirmation', true)
        ->call('saveSubscriber')->assertHasNoErrors()
        ->call('newSubscriber')->set('subForm.email', 'nouvelle@exemple.bj')->call('saveSubscriber')->assertHasErrors('subForm.email');

    $subscriber = NewsletterSubscriber::query()->whereEmail('nouvelle@exemple.bj')->firstOrFail();
    expect($subscriber->status)->toBe(SubscriberStatus::Pending)->and($subscriber->source->value)->toBe('admin')
        ->and(MailOutbox::query()->where('to', 'nouvelle@exemple.bj')->where('template_slug', 'newsletter-confirmation')->count())->toBe(1);
    Mail::assertNothingSent();
    expect(AuditLog::query()->where('action', 'newsletter.subscriber_added')->firstOrFail()->detail)->toBe('abonné='.$subscriber->id);
});

it('désabonne, renvoie la confirmation et supprime définitivement (RGPD) sans écrire l’adresse au journal', function (): void {
    $active = NewsletterSubscriber::factory()->create(['email' => 'actif@exemple.bj']);
    $pending = NewsletterSubscriber::factory()->pending()->create(['email' => 'attente@exemple.bj']);

    nlScreen('admin', 'abonnes')->call('unsubscribeSubscriber', $active->id)->call('resendConfirmation', $pending->id)->call('deleteSubscriber', $pending->id);

    expect($active->refresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and(NewsletterSubscriber::query()->whereKey($pending->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'like', 'newsletter.%')->get()->toJson())->not->toContain('exemple.bj');
});

it('applique une action groupée à la sélection', function (): void {
    $subscribers = NewsletterSubscriber::factory()->count(3)->create();

    nlScreen('admin', 'abonnes')->set('selected', [(string) $subscribers[0]->id, (string) $subscribers[1]->id])->set('bulkAction', 'unsubscribe')->call('applyBulk');
    expect(NewsletterSubscriber::query()->withStatus(SubscriberStatus::Unsubscribed)->count())->toBe(2);

    nlScreen('admin', 'abonnes')->set('selected', [(string) $subscribers[2]->id])->set('bulkAction', 'tag:presse')->call('applyBulk');
    expect($subscribers[2]->refresh()->tags)->toBe(['presse']);

    nlScreen('admin', 'abonnes')->set('selected', [(string) $subscribers[2]->id])->set('bulkAction', 'delete')->call('applyBulk');
    expect(NewsletterSubscriber::query()->count())->toBe(2);
});

it('importe un CSV seulement avec l’attestation de consentement, ignore doublons et lignes invalides', function (): void {
    NewsletterSubscriber::factory()->create(['email' => 'deja@exemple.bj']);
    $csv = UploadedFile::fake()->createWithContent('abonnes.csv', "E-mail;Nom;Tags;Centres d’intérêt\nnouveau@exemple.bj;Awa Test;presse;\ndeja@exemple.bj;Doublon;;\nn-importe-quoi;X;;\n");

    $screen = nlScreen('admin', 'abonnes')->call('openImport')->set('csv', $csv)->set('importOptin', false)->call('import');
    $screen->assertHasErrors('importAttested');
    expect(NewsletterSubscriber::query()->count())->toBe(1);

    $screen->set('csv', UploadedFile::fake()->createWithContent('abonnes.csv', "E-mail;Nom;Tags;Centres d’intérêt\nnouveau@exemple.bj;Awa Test;presse;\ndeja@exemple.bj;Doublon;;\nn-importe-quoi;X;;\n"))
        ->set('importAttested', true)->call('import')->assertHasNoErrors();

    $new = NewsletterSubscriber::query()->whereEmail('nouveau@exemple.bj')->firstOrFail();
    expect($screen->get('importReport'))->toBe(['imported' => 1, 'duplicates' => 1, 'invalid' => 1])
        ->and($new->status)->toBe(SubscriberStatus::Active)->and($new->source->value)->toBe('import')->and($new->tags)->toBe(['presse']);
});

it('importe en double opt-in : personnes en attente et e-mail de confirmation déposé dans la boîte d’envoi', function (): void {
    $csv = UploadedFile::fake()->createWithContent('abonnes.csv', "nouveau@exemple.bj;Awa\n");

    nlScreen('admin', 'abonnes')->call('openImport')->set('csv', $csv)->set('importAttested', true)->set('importOptin', true)->call('import')->assertHasNoErrors();

    expect(NewsletterSubscriber::query()->whereEmail('nouveau@exemple.bj')->firstOrFail()->status)->toBe(SubscriberStatus::Pending)
        ->and(MailOutbox::query()->where('to', 'nouveau@exemple.bj')->count())->toBe(1);
});

it('exporte les abonné·es filtré·es en CSV par lien signé', function (): void {
    NewsletterSubscriber::factory()->create(['email' => 'alpha@exemple.bj']);

    $screen = nlScreen('admin', 'abonnes')->call('export');

    $screen->assertRedirectContains('/admin/export');
    $url = $screen->effects['redirect'];
    $response = $this->get($url)->assertOk();
    expect($response->streamedContent())->toContain('alpha@exemple.bj')->and(AuditLog::query()->where('action', 'export.csv')->count())->toBe(1);
});

it('enregistre un brouillon : corps nettoyé, segment assaini', function (): void {
    NewsletterSubscriber::factory()->create()->syncTags(['presse']);

    nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['seg_tags' => ['presse', 'inconnu']]))->call('saveCampaign')->assertHasNoErrors();

    $campaign = NewsletterCampaign::query()->firstOrFail();
    expect($campaign->status)->toBe(CampaignStatus::Draft)->and($campaign->body)->not->toContain('script')->toContain('{{user_first_name}}')
        ->and($campaign->segment)->toBe(['tags' => ['presse']])
        ->and(AuditLog::query()->where('action', 'newsletter.campaign_saved')->firstOrFail()->detail)->toBe('campagne='.$campaign->id.' ; destinataires=1');
});

it('refuse de programmer ou lancer une campagne sans destinataire actif (RG-12) ou à une date passée', function (): void {
    NewsletterSubscriber::factory()->unsubscribed()->create();

    nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['mode' => 'now']))->call('saveCampaign')->assertHasErrors('campForm.subject');
    expect(NewsletterCampaign::query()->count())->toBe(0);

    NewsletterSubscriber::factory()->create();
    nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['mode' => 'schedule', 'scheduled_at' => now()->subHour()->format('Y-m-d\TH:i')]))->call('saveCampaign')->assertHasErrors('campForm.scheduled_at');
});

it('programme une campagne puis annule la programmation : elle repasse en brouillon', function (): void {
    NewsletterSubscriber::factory()->create();

    nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['mode' => 'schedule', 'scheduled_at' => now()->addDays(2)->format('Y-m-d\TH:i')]))->call('saveCampaign')->assertHasNoErrors();
    $campaign = NewsletterCampaign::query()->firstOrFail();
    expect($campaign->status)->toBe(CampaignStatus::Scheduled)->and($campaign->scheduled_at->isFuture())->toBeTrue();

    nlScreen('admin', 'campagnes')->call('cancelSchedule', $campaign->id);

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Draft)->and($campaign->scheduled_at)->toBeNull();
});

it('lance une campagne sans aucun SMTP dans la requête : planifiée maintenant, lots déposés dans la file', function (): void {
    Mail::fake();
    Queue::fake();
    NewsletterSubscriber::factory()->count(3)->create();

    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['mode' => 'now']))->call('saveCampaign')->assertHasNoErrors();
    $campaign = NewsletterCampaign::query()->firstOrFail();
    expect($campaign->status)->toBe(CampaignStatus::Scheduled);

    $screen->call('processQueue');

    expect($campaign->refresh()->status)->toBe(CampaignStatus::Sending);
    Queue::assertPushed(SendCampaignBatch::class, 1);
    Mail::assertNothingSent();
});

it('verrouille une campagne « En cours » : ni modifiable ni supprimable', function (): void {
    NewsletterSubscriber::factory()->create();
    $campaign = NewsletterCampaign::factory()->sending()->create();

    $screen = nlScreen('admin', 'campagnes')->call('editCampaign', $campaign->id)->assertSee('Campagne envoyée')
        ->set('campForm', campaignForm())->call('saveCampaign')->assertHasErrors('campForm.subject');
    $screen->call('deleteCampaign', $campaign->id);

    expect($campaign->refresh()->subject)->not->toBe('Les nouvelles du mois')
        ->and(NewsletterCampaign::query()->whereKey($campaign->id)->exists())->toBeTrue();
});

it('duplique une campagne en brouillon et supprime une campagne terminée', function (): void {
    $sent = NewsletterCampaign::factory()->sent()->create(['subject' => 'Octobre']);

    nlScreen('admin', 'campagnes')->call('duplicateCampaign', $sent->id)->call('deleteCampaign', $sent->id);

    $copy = NewsletterCampaign::query()->firstOrFail();
    expect($copy->subject)->toBe('Octobre (copie)')->and($copy->status)->toBe(CampaignStatus::Draft)
        ->and(NewsletterCampaign::withTrashed()->count())->toBe(2);
});

it('prévisualise la campagne pour une personne fictive et dépose le test dans la boîte d’envoi', function (): void {
    Mail::fake();

    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm())->call('setCampPane', 'preview');
    expect($screen->get('previewHtml'))->toContain('Bonjour Awa')->toContain('/newsletter/desinscription/')->not->toContain('/newsletter/ouverture/');

    $screen->set('testTo', 'test@exemple.bj')->call('sendCampaignTest')->assertHasNoErrors();
    $mail = MailOutbox::query()->where('template_slug', 'newsletter-test')->firstOrFail();
    expect($mail->subject)->toStartWith('[TEST] ')->and($mail->headers['List-Unsubscribe-Post'])->toBe('List-Unsubscribe=One-Click');
    Mail::assertNothingSent();
});

it('n’insère un témoignage qu’avec le consentement attesté', function (): void {
    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm(['body' => '']))->call('insertSection', 'testimonial')->assertHasErrors('campForm.body');
    expect($screen->get('campForm')['body'])->toBe('');

    $screen->set('testimonialConsent', true)->call('insertSection', 'testimonial')->call('insertSection', 'emergency')->assertHasNoErrors();
    expect($screen->get('campForm')['body'])->toContain('Témoignage')->toContain('117');
});

it('compte en direct les abonné·es actifs visés par le segment', function (): void {
    NewsletterSubscriber::factory()->count(2)->create()->each->syncTags(['presse']);
    NewsletterSubscriber::factory()->create();
    NewsletterSubscriber::factory()->unsubscribed()->create()->syncTags(['presse']);

    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->assertSet('campForm.seg_tags', []);
    expect($screen->get('segmentCount'))->toBe(3);

    $screen->set('campForm.seg_tags', ['presse']);
    expect($screen->get('segmentCount'))->toBe(2);
});

it('rejoue la permission d’envoi : sans `newsletter.send`, ni programmer, ni lancer, ni traiter la file', function (): void {
    NewsletterSubscriber::factory()->create();
    $role = Spatie\Permission\Models\Role::findOrCreate('redacteur-nl');
    foreach (['admin.access', 'newsletter.manage_campaigns', 'newsletter.manage_subscribers'] as $permission) {
        Permission::findOrCreate($permission);
        $role->givePermissionTo($permission);
    }
    $user = User::factory()->create();
    $user->assignRole($role);
    $this->actingAs($user);

    Livewire::test(NewsletterScreen::class, ['tab' => 'campagnes'])->call('newCampaign')->set('campForm', campaignForm(['mode' => 'now']))->call('saveCampaign')->assertForbidden();
    Livewire::test(NewsletterScreen::class, ['tab' => 'campagnes'])->call('processQueue')->assertForbidden();
    expect(NewsletterCampaign::query()->count())->toBe(0);

    Livewire::test(NewsletterScreen::class, ['tab' => 'campagnes'])->call('newCampaign')->set('campForm', campaignForm())->call('saveCampaign')->assertHasNoErrors();
    expect(NewsletterCampaign::query()->count())->toBe(1);
});

it('garde le double opt-in public intact : une inscription reste en attente jusqu’à la confirmation', function (): void {
    $this->post('/newsletter', ['email' => 'public@exemple.bj', 'consent' => '1', 'website' => ''])->assertRedirect();

    expect(NewsletterSubscriber::query()->whereEmail('public@exemple.bj')->first()?->status)->toBe(SubscriberStatus::Pending);
    $this->artisan('newsletter:process', ['--sync' => true])->assertSuccessful();
    expect(NewsletterSubscriber::query()->whereEmail('public@exemple.bj')->first()->status)->toBe(SubscriberStatus::Pending);
});

it('refuse une variable non autorisée dans une campagne et dans son envoi de test', function (): void {
    NewsletterSubscriber::factory()->create();
    $form = campaignForm(['body' => '<p>Bonjour {{user_first_name}}, votre code {{otp_code}}</p>']);

    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', $form)->call('saveCampaign');
    expect(NewsletterCampaign::query()->count())->toBe(0);

    $screen->set('testTo', 'a@exemple.bj')->call('sendCampaignTest')->assertHasErrors('testTo');
    expect(MailOutbox::query()->count())->toBe(0);
});

it('plafonne les envois de test de campagne à 20 par jour et par compte', function (): void {
    $screen = nlScreen('admin', 'campagnes')->call('newCampaign')->set('campForm', campaignForm())->set('testTo', 'a@exemple.bj');

    foreach (range(1, 20) as $i) {
        Illuminate\Support\Facades\RateLimiter::hit('newsletter-test-day:'.auth()->id(), 86400);
    }

    $screen->call('sendCampaignTest')->assertHasErrors('testTo');
    expect(MailOutbox::query()->count())->toBe(0);
});
