<?php

declare(strict_types=1);

use App\Application\Import\ImportContext;
use App\Application\Import\ImportKeys;
use App\Application\Import\PrototypeImporter;
use App\Enums\CaseKind;
use App\Enums\FollowupOutcome;
use App\Enums\GroupPrivacy;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\EmailTemplate;
use App\Models\Event;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\GeneratedReport;
use App\Models\Group;
use App\Models\Invitation;
use App\Models\ModerationReport;
use App\Models\NewsletterCampaign;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterClick;
use App\Models\NewsletterSubscriber;
use App\Models\Page;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\Profile;
use App\Models\Setting;
use App\Models\SocialChannel;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/*
 * P1-13 : import du dossier réel prototype_social/data dans les tables de la phase 1 (SQLite mémoire,
 * RefreshDatabase via tests/Pest.php). Les référentiels, templates, indicateurs, réseaux et règles sont
 * d'abord semés : l'import les met à jour sans les dupliquer.
 */

const PROTOTYPE_DATA = __DIR__.'/../../../../prototype_social/data';

/**
 * @return array<string, array{created: int, updated: int, rejected: int, skipped: string|null}>
 */
function importPrototype(bool $withDemo = true, bool $dryRun = false, array $only = []): array
{
    return (new PrototypeImporter)
        ->import(directory: PROTOTYPE_DATA, only: $only, dryRun: $dryRun, withDemo: $withDemo)
        ->rows();
}

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(DatabaseSeeder::class);
});

it('importe le dossier réel du prototype avec la démonstration et respecte les comptages attendus', function (): void {
    $report = importPrototype();

    expect(array_filter($report, static fn (array $row): bool => $row['skipped'] !== null))->toBe([])
        // users.json : 20 comptes + les deux pair·es des registres créées à la volée.
        ->and(User::count())->toBeGreaterThanOrEqual(20)
        ->and(User::where('email', 'esther@exemple.bj')->first()?->hasRole('pair'))->toBeTrue()
        ->and(User::where('email', 'mireille@exemple.bj')->first()?->profile?->member_type->value)->toBe('pair')
        ->and(DirectoryContact::where('name', 'Esther KOTO')->value('user_id'))->toBe(User::where('email', 'esther@exemple.bj')->value('id'))
        ->and(AuditLog::ofAction(ImportContext::AUDIT_ACCOUNT_CREATED)->count())->toBe(2)
        // cases.json : 72 dossiers, 49 VBG et 23 VDH renumérotés (RG-06), renumérotation journalisée.
        ->and(CaseRecord::count())->toBe(72)
        ->and(CaseRecord::ofKind(CaseKind::Vbg)->where('code', 'like', 'VBG-%')->count())->toBe(49)
        ->and(CaseRecord::ofKind(CaseKind::Vdh)->where('code', 'like', 'VDH-%')->count())->toBe(23)
        ->and(CaseRecord::where('client_uuid', ImportKeys::caseUuid('VBG-0001'))->value('code'))->toBe('VDH-0001')
        ->and(AuditLog::ofAction(ImportContext::AUDIT_CASE_RENUMBERED)->count())->toBe(23)
        ->and(CaseRecord::whereNull('collected_by')->count())->toBe(0)
        // suivi.json : 30 étapes rattachées par la correspondance des codes, « En attente » → planifié.
        ->and(CaseFollowup::count())->toBe(30)
        ->and(CaseFollowup::whereNull('by_user_id')->count())->toBe(0)
        ->and(CaseFollowup::where('outcome', FollowupOutcome::Planned->value)->count())->toBe(11)
        // alertes.json : 24 alertes routées vers l'annuaire, code de suivi généré.
        ->and(Alert::count())->toBe(24)
        ->and(Alert::whereNull('routed_to')->count())->toBe(0)
        ->and(Alert::whereNull('tracking_code')->count())->toBe(0)
        ->and(Alert::where('is_anonymous', true)->count())->toBe(17)
        // posts.json : 5 publications dont un sondage éclaté en options et 8 votes.
        ->and(Post::count())->toBe(5)
        ->and(Post::tagged(PostTag::Poll)->count())->toBe(1)
        ->and(Poll::count())->toBe(1)
        ->and(PollOption::count())->toBe(4)
        ->and(PollVote::count())->toBe(8)
        ->and(Poll::first()?->voters_count)->toBe(8)
        ->and(PollOption::where('label', 'Bien-être et santé mentale')->value('votes_count'))->toBe(4)
        ->and(Post::first()?->visibility)->toBe(PostVisibility::Public)
        // Réseau : forums, groupes, pages, événements, invitations.
        ->and(ForumTopic::count())->toBe(6)
        ->and(ForumReply::count())->toBe(6)
        ->and(ForumTopic::where('title', 'Comment parler à ma famille sans me mettre en danger ?')->value('replies_count'))->toBe(2)
        ->and(Group::count())->toBe(6)
        ->and(Group::where('slug', 'cercle-des-soeurs-cotonou')->value('privacy'))->toBe(GroupPrivacy::Private)
        ->and(Page::count())->toBe(4)
        ->and(Event::count())->toBe(6)
        ->and(Event::where('slug', 'webinaire-connaitre-ses-droits')->value('is_online'))->toBeTrue()
        ->and(Event::where('slug', 'atelier-dire-non-sans-culpabiliser')->value('organizer_id'))->toBe(User::where('email', 'pair@xomiton.bj')->value('id'))
        ->and(Invitation::count())->toBe(2)
        // Communication : 42 abonné·es avec liaisons, 5 campagnes avec destinataires et clics, 8 templates, 6 réseaux.
        ->and(NewsletterSubscriber::count())->toBe(42)
        ->and(DB::table('newsletter_subscriber_tags')->count())->toBe(35)
        ->and(DB::table('newsletter_subscriber_interests')->count())->toBe(74)
        ->and(NewsletterCampaign::count())->toBe(5)
        ->and(NewsletterCampaignRecipient::count())->toBe(108)
        ->and(NewsletterCampaignRecipient::where('bounced', true)->count())->toBe(6)
        ->and(NewsletterCampaignRecipient::whereNotNull('opened_at')->count())->toBe(47)
        ->and(NewsletterClick::count())->toBe(21)
        ->and($report['email_templates']['created'] + $report['email_templates']['updated'])->toBe(8)
        ->and(EmailTemplate::whereIn('slug', ['reinitialisation-mot-de-passe', 'activation-compte', 'code-verification', 'bienvenue', 'compte-valide', 'alerte-routee', 'rappel-evenement', 'newsletter-confirmation'])->count())->toBe(8)
        ->and(EmailTemplate::where('slug', 'bienvenue')->value('updated_by'))->toBe(User::where('email', 'admin@xomiton.bj')->value('id'))
        ->and(SocialChannel::count())->toBe(6)
        ->and(SocialChannel::connected()->pluck('network')->map(fn ($n) => $n->value)->sort()->values()->all())->toBe(['facebook', 'telegram'])
        // Plateforme : rapports, signalements, paramètres, appareils (3 libellés sans compte rejetés), conflits.
        ->and(GeneratedReport::count())->toBe(1)
        ->and(ModerationReport::count())->toBe(8)
        ->and(Setting::count())->toBeGreaterThan(11)
        ->and(SyncDevice::count())->toBe(2)
        ->and($report['sync_devices']['rejected'])->toBe(3)
        ->and(SyncConflict::count())->toBe(3)
        ->and(SyncConflict::whereNull('subject_id')->count())->toBe(0)
        // Chaque rejet est journalisé en audit avec sa raison.
        ->and(AuditLog::ofAction(ImportContext::AUDIT_REJECTED)->count())->toBe(array_sum(array_column($report, 'rejected')))
        ->and(AuditLog::ofAction(ImportContext::AUDIT_REJECTED)->where('detail', 'like', 'sync_devices #3 : %Rodrigue S.%')->exists())->toBeTrue()
        ->and(file_exists(storage_path(PrototypeImporter::LOG_FILE)))->toBeTrue()
        // Avatars copiés sur le disque public (index 0 → avatar1.png).
        ->and(Storage::disk('public')->exists('avatars/avatar1.png'))->toBeTrue()
        ->and(Storage::disk('public')->exists('avatars/personnage-1.png'))->toBeTrue()
        ->and(Profile::whereHas('user', fn ($q) => $q->where('email', 'membre@xomiton.bj'))->value('avatar_path'))->toBe('avatars/avatar3.png')
        ->and(Profile::whereHas('user', fn ($q) => $q->where('email', 'rosine@exemple.bj'))->value('avatar_path'))->toBe('avatars/personnage-1.png');
});

it('reprend les empreintes bcrypt du prototype et donne un mot de passe aléatoire aux comptes sans empreinte', function (): void {
    importPrototype();

    $admin = User::where('email', 'admin@xomiton.bj')->firstOrFail();
    $mariam = User::where('email', 'mariam@exemple.bj')->firstOrFail();

    expect($admin->getRawOriginal('password'))->toBe('$2y$10$oDA.w.ACIk.59uP3jybaq.5v9rbKgsOw9HjZ68exRcZMqZacO88Jq')
        ->and($admin->hasRole('superadmin'))->toBeTrue()
        ->and($admin->created_at?->toDateString())->toBe('2025-01-10')
        ->and($mariam->status->value)->toBe('en_attente')
        ->and($mariam->getRawOriginal('password'))->toStartWith('$2y$')
        ->and($mariam->zone?->name)->toBe('Littoral')
        ->and($mariam->commune_id)->toBeNull()
        ->and(User::where('email', 'rosine@exemple.bj')->first()?->status)->toBe(UserStatus::Suspended);
});

it('est idempotent : rejouer l’import ne crée aucun doublon', function (): void {
    importPrototype();

    $counts = fn (): array => [
        User::count(), CaseRecord::count(), CaseFollowup::count(), Alert::count(), Post::count(), PollVote::count(),
        ForumTopic::count(), ForumReply::count(), Group::count(), Page::count(), Event::count(), Invitation::count(),
        NewsletterSubscriber::count(), NewsletterCampaign::count(), NewsletterCampaignRecipient::count(), NewsletterClick::count(),
        EmailTemplate::count(), SocialChannel::count(), GeneratedReport::count(), ModerationReport::count(), Setting::count(),
        SyncDevice::count(), SyncConflict::count(), DirectoryContact::count(),
        DB::table('newsletter_subscriber_tags')->count(), DB::table('poll_options')->count(), DB::table('post_media')->count(),
        AuditLog::ofAction('Connexion')->count(),
    ];
    $codes = CaseRecord::orderBy('id')->pluck('code')->all();
    $before = $counts();

    $report = importPrototype();

    expect($counts())->toBe($before)
        ->and(CaseRecord::orderBy('id')->pluck('code')->all())->toBe($codes)
        ->and(array_sum(array_column($report, 'created')))->toBe(0)
        ->and(AuditLog::ofAction(ImportContext::AUDIT_ACCOUNT_CREATED)->count())->toBe(2)
        ->and(AuditLog::ofAction(ImportContext::AUDIT_CASE_RENUMBERED)->count())->toBe(23);
});

it('ne modifie rien en simulation (--dry-run) mais journalise les rejets dans le fichier', function (): void {
    $usersBefore = User::count();
    $logBefore = file_exists(storage_path(PrototypeImporter::LOG_FILE)) ? filesize(storage_path(PrototypeImporter::LOG_FILE)) : 0;

    $this->artisan('xomiton:import-prototype', ['directory' => PROTOTYPE_DATA, '--with-demo' => true, '--dry-run' => true])
        ->expectsOutputToContain('Simulation')
        ->assertSuccessful();

    clearstatcache();

    expect(User::count())->toBe($usersBefore)
        ->and(CaseRecord::count())->toBe(0)
        ->and(Alert::count())->toBe(0)
        ->and(NewsletterSubscriber::count())->toBe(0)
        ->and(AuditLog::ofAction(ImportContext::AUDIT_REJECTED)->count())->toBe(0)
        ->and(filesize(storage_path(PrototypeImporter::LOG_FILE)))->toBeGreaterThan($logBefore)
        // avatar1..4 sont posés par DemoAccountsSeeder ; personnage-1.png ne viendrait que de l'import (index 4).
        ->and(Storage::disk('public')->exists('avatars/personnage-1.png'))->toBeFalse();
});

it('n’importe ni les contacts ni les contenus de démonstration en production sans --with-demo', function (): void {
    $this->app->detectEnvironment(fn (): string => 'production');

    $report = importPrototype(withDemo: false);

    expect($report['annuaire']['skipped'])->not->toBeNull()
        ->and($report['cases']['skipped'])->not->toBeNull()
        ->and($report['newsletter_subscribers']['skipped'])->not->toBeNull()
        ->and($report['journal']['skipped'])->not->toBeNull()
        ->and($report['faqs']['skipped'])->toBeNull()
        ->and($report['email_templates']['skipped'])->toBeNull()
        ->and($report['indicateurs']['skipped'])->toBeNull()
        ->and(DirectoryContact::count())->toBe(0)
        ->and(CaseRecord::count())->toBe(0)
        ->and(Alert::count())->toBe(0)
        ->and(Post::count())->toBe(0)
        ->and(NewsletterSubscriber::count())->toBe(0)
        ->and(SyncDevice::count())->toBe(0)
        // Les comptes sans empreinte de mot de passe sont rejetés et journalisés ; les quatre comptes du prototype passent.
        ->and($report['users']['rejected'])->toBe(16)
        ->and(User::count())->toBe(4)
        ->and(AuditLog::ofAction(ImportContext::AUDIT_REJECTED)->where('detail', 'like', 'users #5 : %')->exists())->toBeTrue()
        ->and(SocialChannel::connected()->count())->toBe(2);

    // Avec --with-demo, la production importe tout.
    $full = importPrototype(withDemo: true);

    expect($full['annuaire']['created'])->toBe(10)
        ->and(CaseRecord::count())->toBe(72)
        ->and(User::count())->toBe(22);
});

it('chiffre les champs sensibles : lisibles via le modèle, illisibles en base brute', function (): void {
    importPrototype();

    $case = CaseRecord::where('client_uuid', ImportKeys::caseUuid('VBG-0001'))->firstOrFail();
    $alert = Alert::orderBy('created_at')->firstOrFail();
    $followup = CaseFollowup::whereNotNull('comment')->first();

    expect($case->notes)->toBe('Prise en charge initiale effectuée.')
        ->and($case->getRawOriginal('notes'))->not->toContain('Prise en charge')
        ->and(DB::table('cases')->where('id', $case->id)->value('notes'))->toStartWith('eyJpdiI6')
        ->and($alert->lat)->toBeString()
        ->and((float) $alert->lat)->toBeGreaterThan(6.0)
        ->and(DB::table('alerts')->where('id', $alert->id)->value('lat'))->not->toContain('6.')
        ->and($alert->description)->toBeString()
        ->and(DB::table('alerts')->where('id', $alert->id)->value('description'))->not->toContain($alert->description)
        ->and($followup === null || str_starts_with((string) $followup->getRawOriginal('comment'), 'eyJpdiI6'))->toBeTrue();
});

it('n’importe que les collections demandées avec --only et refuse une collection inconnue', function (): void {
    importPrototype(only: ['users', 'annuaire', 'cases']);

    expect(CaseRecord::count())->toBe(72)
        ->and(CaseFollowup::count())->toBe(0)
        ->and(Alert::count())->toBe(0);

    // La correspondance des codes se reconstruit depuis la base : suivi seul rattache ses 30 étapes.
    $report = importPrototype(only: ['suivi']);

    expect(CaseFollowup::count())->toBe(30)
        ->and($report['suivi']['rejected'])->toBe(0);

    $this->artisan('xomiton:import-prototype', ['directory' => PROTOTYPE_DATA, '--only' => ['inconnue']])
        ->expectsOutputToContain('inconnue')
        ->assertFailed();

    $this->artisan('xomiton:import-prototype', ['directory' => PROTOTYPE_DATA.'/nulle-part'])
        ->assertFailed();
});
