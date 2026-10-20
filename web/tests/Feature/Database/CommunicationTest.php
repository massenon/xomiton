<?php

declare(strict_types=1);

use App\Enums\CampaignStatus;
use App\Enums\MailTransport;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Enums\TemplateCategory;
use App\Models\DatabaseNotification;
use App\Models\EmailTemplate;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterSubscriberInterest;
use App\Models\NewsletterSubscriberTag;
use App\Models\NewsletterTag;
use App\Models\PushSubscription;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\NewsletterTaxonomySeeder;
use Illuminate\Database\QueryException;

/*
 * Lot 10 « communication » : une création par factory et une règle métier par table
 * (DATABASE_DICTIONARY.md §9). Tourne avec RefreshDatabase (tests/Pest.php).
 */

it('crée un template, détecte ses variables et refuse un slug en double', function (): void {
    $template = EmailTemplate::factory()->system()->inCategory(TemplateCategory::Authentication)->create([
        'slug' => 'code-verification',
        'subject' => 'Votre code {{site_name}} : {{otp_code}}',
        'preheader' => 'Code valable {{ otp_expiry }}.',
        'body' => '<p>Bonjour {{user_first_name}},</p><h1>{{otp_code}}</h1>',
    ]);

    expect($template->fresh()->variables)->toBe(['site_name', 'otp_code', 'otp_expiry', 'user_first_name'])
        ->and($template->fresh()->category)->toBe(TemplateCategory::Authentication)
        ->and($template->isSystem())->toBeTrue()
        ->and($template->canRenameSlug())->toBeFalse()
        ->and($template->updater)->not->toBeNull()
        ->and(EmailTemplate::query()->active()->system()->count())->toBe(1)
        ->and(EmailTemplate::query()->whereSlug('code-verification')->first()?->id)->toBe($template->id)
        ->and(TemplateCategory::fromPrototype('Communauté'))->toBe(TemplateCategory::Community);

    expect(fn () => EmailTemplate::factory()->create(['slug' => 'code-verification']))->toThrow(QueryException::class);
});

it('seed les 8 templates du prototype et mot-de-passe-modifie sans doublon et sans écraser les textes édités', function (): void {
    $this->seed(EmailTemplatesSeeder::class);

    EmailTemplate::query()->whereSlug('bienvenue')->firstOrFail()->update(['subject' => 'Sujet modifié', 'is_active' => false]);

    $this->seed(EmailTemplatesSeeder::class);

    $otp = EmailTemplate::query()->whereSlug('code-verification')->firstOrFail();
    $welcome = EmailTemplate::query()->whereSlug('bienvenue')->firstOrFail();

    // 8 du prototype + `mot-de-passe-modifie` (P2-05) + `compte-supprime` et `export-donnees` (P6-06) + `rapport-genere` (P10-07).
    expect(EmailTemplate::query()->count())->toBe(12)
        ->and(EmailTemplate::query()->system()->count())->toBe(9)
        ->and($otp->category)->toBe(TemplateCategory::Authentication)
        ->and($otp->variables)->toBe(['site_name', 'otp_code', 'otp_expiry', 'user_first_name'])
        ->and($welcome->subject)->toBe('Sujet modifié')
        ->and($welcome->is_active)->toBeFalse()
        ->and($welcome->variables)->toBe(['user_first_name', 'login_link'])
        ->and(EmailTemplate::query()->inCategory(TemplateCategory::Newsletter)->value('slug'))->toBe('newsletter-confirmation');
});

it('journalise un envoi sans updated_at et retrouve les lignes à purger', function (): void {
    $log = MailLog::factory()->create(['template_slug' => 'bienvenue']);
    MailLog::factory()->failed()->create(['template_slug' => 'code-verification']);
    MailLog::factory()->invalidAddress()->expired()->create(['template_slug' => null]);

    expect($log->fresh()->transport)->toBe(MailTransport::Smtp)
        ->and($log->isSuccessful())->toBeTrue()
        ->and($log->updated_at)->toBeNull()
        ->and(MailLog::query()->successful()->count())->toBe(1)
        ->and(MailLog::query()->failed()->count())->toBe(2)
        ->and(MailLog::query()->expired()->count())->toBe(1)
        ->and(MailLog::query()->viaTransport(MailTransport::None)->count())->toBe(1)
        ->and(MailLog::query()->forTemplate('bienvenue')->count())->toBe(1)
        ->and(MailTransport::fromPrototype('-'))->toBe(MailTransport::None)
        ->and(MailTransport::None->isDelivering())->toBeFalse();
});

it('garde un e-mail en file d’attente et le marque envoyé ou en échec', function (): void {
    $pending = MailOutbox::factory()->create(['template_slug' => 'bienvenue']);
    MailOutbox::factory()->sent()->create();
    MailOutbox::factory()->exhausted()->create();

    expect($pending->getTable())->toBe('mail_outbox')
        ->and(EmailTemplate::factory()->create(['slug' => 'bienvenue'])->outboxMails()->count())->toBe(1)
        ->and($pending->canRetry())->toBeTrue()
        ->and(MailOutbox::query()->pending()->count())->toBe(2)
        ->and(MailOutbox::query()->flushable()->count())->toBe(1);

    $pending->markFailed('Connection refused');
    $pending->markSent();

    expect($pending->fresh()->attempts)->toBe(1)
        ->and($pending->fresh()->isSent())->toBeTrue()
        ->and($pending->fresh()->last_error)->toBeNull()
        ->and(MailOutbox::query()->sent()->count())->toBe(2);
});

it('crée un tag et un centre d’intérêt avec slug dérivé et unique', function (): void {
    $tag = NewsletterTag::factory()->create(['label' => 'Pair·e', 'slug' => null]);
    $interest = NewsletterInterest::factory()->create(['label' => 'Sécurité numérique', 'slug' => null]);

    expect($tag->slug)->toBe('paire')
        ->and($interest->slug)->toBe('securite-numerique')
        ->and(NewsletterTag::query()->whereSlug('paire')->first()?->id)->toBe($tag->id);

    expect(fn () => NewsletterTag::factory()->create(['slug' => 'paire']))->toThrow(QueryException::class);
    expect(fn () => NewsletterInterest::factory()->create(['slug' => 'securite-numerique']))->toThrow(QueryException::class);
});

it('seed les 5 tags et 6 centres d’intérêt du prototype sans doublon', function (): void {
    $this->seed(NewsletterTaxonomySeeder::class);
    $this->seed(NewsletterTaxonomySeeder::class);

    expect(NewsletterTag::query()->count())->toBe(5)
        ->and(NewsletterTag::query()->pluck('slug')->sort()->values()->all())->toBe(['bailleur', 'membre', 'pair', 'partenaire', 'presse'])
        ->and(NewsletterInterest::query()->count())->toBe(6)
        ->and(NewsletterInterest::query()->whereSlug('bien-etre')->value('label'))->toBe('Bien-être')
        ->and(NewsletterInterest::query()->whereSlug('securite-numerique')->value('label'))->toBe('Sécurité numérique');
});

it('abonne une adresse unique avec jeton caché, puis la confirme et la désabonne', function (): void {
    $subscriber = NewsletterSubscriber::factory()->pending()->create(['email' => 'Awa.Kpadonou@Exemple.bj', 'token' => null]);

    expect($subscriber->email)->toBe('awa.kpadonou@exemple.bj')
        ->and($subscriber->token)->toHaveLength(NewsletterSubscriber::TOKEN_LENGTH)
        ->and($subscriber->toArray())->not->toHaveKey('token')
        ->and($subscriber->fresh()->status)->toBe(SubscriberStatus::Pending)
        ->and($subscriber->fresh()->source)->toBe(SubscriberSource::Footer)
        ->and(NewsletterSubscriber::query()->whereToken($subscriber->token)->first()?->id)->toBe($subscriber->id);

    $subscriber->confirm();
    expect($subscriber->fresh()->isActive())->toBeTrue()->and($subscriber->fresh()->confirmed_at)->not->toBeNull();

    $subscriber->unsubscribe('Trop d’e-mails');
    expect($subscriber->fresh()->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->fresh()->unsubscribe_reason)->toBe('Trop d’e-mails')
        ->and(NewsletterSubscriber::query()->active()->count())->toBe(0);

    expect(fn () => NewsletterSubscriber::factory()->create(['email' => 'awa.kpadonou@exemple.bj']))->toThrow(QueryException::class);
});

it('segmente les abonné·es par tags et centres d’intérêt via les tables de liaison', function (): void {
    $this->seed(NewsletterTaxonomySeeder::class);

    $pair = NewsletterSubscriber::factory()->withTags(['pair'])->withInterests(['sante', 'droits'])->create();
    $member = NewsletterSubscriber::factory()->withTags(['membre'])->withInterests(['evenements'])->create();
    NewsletterSubscriber::factory()->unsubscribed()->withTags(['pair'])->create();

    expect($pair->fresh()->tags)->toBe(['pair'])
        ->and($pair->fresh()->interests)->toBe(['sante', 'droits'])
        ->and($pair->tagModels()->count())->toBe(1)
        ->and($pair->interestModels()->count())->toBe(2)
        ->and(NewsletterSubscriberTag::query()->count())->toBe(3)
        ->and(NewsletterSubscriberInterest::query()->count())->toBe(3)
        ->and(NewsletterSubscriber::query()->inSegment(['tags' => ['pair']])->pluck('id')->all())->toBe([$pair->id])
        ->and(NewsletterSubscriber::query()->inSegment(['interests' => ['evenements', 'droits']])->count())->toBe(2)
        ->and(NewsletterSubscriber::query()->inSegment([])->count())->toBe(2)
        ->and(NewsletterTag::query()->whereSlug('pair')->first()?->subscribers()->count())->toBe(2);

    $pair->syncTags(['membre', 'inconnu']);
    expect($pair->fresh()->tags)->toBe(['membre'])->and($pair->tagModels()->pluck('slug')->all())->toBe(['membre']);

    expect(fn () => NewsletterSubscriberTag::factory()->create(['subscriber_id' => $member->id, 'tag_id' => NewsletterTag::query()->whereSlug('membre')->value('id')]))
        ->toThrow(QueryException::class);

    $member->delete();
    expect(NewsletterSubscriberInterest::query()->where('subscriber_id', $member->id)->count())->toBe(0);
});

it('planifie une campagne, résout son segment et retrouve celles à envoyer', function (): void {
    $active = NewsletterSubscriber::factory()->count(2)->create()->all();
    NewsletterSubscriber::factory()->bounced()->create();

    $due = NewsletterCampaign::factory()->due()->targeting($active)->create();
    NewsletterCampaign::factory()->scheduled(now()->addDay())->create();
    NewsletterCampaign::factory()->sent()->create();

    expect($due->fresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($due->fresh()->segment)->toBe([])
        ->and($due->isDue())->toBeTrue()
        ->and($due->isEditable())->toBeTrue()
        ->and($due->recipients_count)->toBe(2)
        ->and($due->recipients()->count())->toBe(2)
        ->and($due->subscribers()->count())->toBe(2)
        ->and($due->targetQuery()->count())->toBe(2)
        ->and($due->segmentLabel())->toBe('Tous les abonné·es actifs')
        ->and(NewsletterCampaign::factory()->segment(['tags' => ['pair'], 'since' => '2026-08-01'])->make()->segmentLabel())
        ->toBe('tags : pair · inscrit·es depuis le 01/08/2026')
        ->and($active[0]->campaigns()->count())->toBe(1)
        ->and($due->creator)->not->toBeNull()
        ->and(NewsletterCampaign::query()->due()->count())->toBe(1)
        ->and(NewsletterCampaign::query()->scheduled()->count())->toBe(2)
        ->and(NewsletterCampaign::query()->sent()->count())->toBe(1)
        ->and(CampaignStatus::Sent->isFinal())->toBeTrue();

    $due->delete();

    expect(NewsletterCampaign::query()->count())->toBe(2)
        ->and(NewsletterCampaign::withTrashed()->count())->toBe(3)
        ->and(NewsletterCampaignRecipient::query()->where('campaign_id', $due->id)->count())->toBe(2);
});

it('refuse deux fois le même destinataire et calcule les livrés et ouvertures', function (): void {
    $recipient = NewsletterCampaignRecipient::factory()->sent()->create();
    $campaign = $recipient->campaign;

    expect(fn () => NewsletterCampaignRecipient::factory()->forCampaign($campaign)->forSubscriber($recipient->subscriber)->create())
        ->toThrow(QueryException::class);

    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->opened()->create();
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->bounced()->create();
    NewsletterCampaignRecipient::factory()->forCampaign($campaign)->create();

    $recipient->markOpened();

    expect($recipient->fresh()->hasOpened())->toBeTrue()
        ->and($recipient->subscriber->fresh()->last_opened_at)->not->toBeNull()
        ->and($campaign->recipients()->sent()->count())->toBe(3)
        ->and($campaign->recipients()->delivered()->count())->toBe(2)
        ->and($campaign->recipients()->opened()->count())->toBe(2)
        ->and($campaign->recipients()->bounced()->count())->toBe(1)
        ->and($campaign->recipients()->pending()->count())->toBe(1);
});

it('compte les clics uniques par URL et cumule les clics répétés', function (): void {
    $click = NewsletterClick::factory()->create(['url' => 'https://xomiton.org/events.php', 'url_hash' => null]);
    $recipient = $click->recipient;

    expect($click->url_hash)->toBe(hash('sha256', 'https://xomiton.org/events.php'))
        ->and($click->updated_at)->toBeNull()
        ->and($click->clicks_count)->toBe(1);

    $again = $recipient->recordClick('https://xomiton.org/events.php');
    $other = $recipient->recordClick('https://xomiton.org/thematiques.php');

    expect($again->id)->toBe($click->id)
        ->and($again->clicks_count)->toBe(2)
        ->and($other->id)->not->toBe($click->id)
        ->and($recipient->clicks()->count())->toBe(2)
        ->and(NewsletterClick::query()->forCampaign($recipient->campaign)->count())->toBe(2)
        ->and(NewsletterClick::query()->forUrl('https://xomiton.org/thematiques.php')->count())->toBe(1);

    expect(fn () => NewsletterClick::factory()->forRecipient($recipient)->onUrl('https://xomiton.org/events.php')->create())
        ->toThrow(QueryException::class);
});

it('stocke une notification en base avec catégorie et drapeau sensible', function (): void {
    $user = User::factory()->create();
    $notification = DatabaseNotification::factory()->forUser($user)->create();
    DatabaseNotification::factory()->forUser($user)->sensitive()->read()->create();

    expect($notification->fresh()->data)->toHaveKey('title')
        ->and($notification->fresh()->category)->toBe(DatabaseNotification::CATEGORY_COMMUNITY)
        ->and($notification->isSensitive())->toBeFalse()
        ->and($notification->isMandatory())->toBeFalse()
        ->and($notification->notifiable->is($user))->toBeTrue()
        ->and($user->notifications()->count())->toBe(2)
        ->and($user->unreadNotifications()->count())->toBe(1)
        ->and(DatabaseNotification::query()->sensitive()->first()?->isMandatory())->toBeTrue()
        ->and(DatabaseNotification::query()->inCategory(DatabaseNotification::CATEGORY_SECURITY)->count())->toBe(1);
});

it('enregistre un abonnement push unique par endpoint et cache ses secrets', function (): void {
    $subscription = PushSubscription::factory()->create(['endpoint' => 'https://push.example/abc', 'endpoint_hash' => 'x']);
    PushSubscription::factory()->fcm()->forUser($subscription->user)->create();

    expect($subscription->fresh()->endpoint_hash)->toBe(hash('sha256', 'https://push.example/abc'))
        ->and($subscription->toArray())->not->toHaveKey('auth_token')
        ->and($subscription->toArray())->not->toHaveKey('endpoint')
        ->and($subscription->isWebPush())->toBeTrue()
        ->and(PushSubscription::query()->webPush()->count())->toBe(1)
        ->and(PushSubscription::query()->fcm()->count())->toBe(1)
        ->and(PushSubscription::query()->forEndpoint('https://push.example/abc')->first()?->id)->toBe($subscription->id);

    expect(fn () => PushSubscription::factory()->create(['endpoint' => 'https://push.example/abc']))->toThrow(QueryException::class);

    $subscription->user->delete();
    $subscription->user->forceDelete();

    expect(PushSubscription::query()->count())->toBe(0);
});
