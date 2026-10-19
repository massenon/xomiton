<?php

declare(strict_types=1);

use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceStatus;
use App\Enums\OtpPurpose;
use App\Http\Middleware\EnsureNotInMaintenance;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * Lot pairs-maintenance (P2-11, RG-14, Q28 b) : mode maintenance applicatif, middleware EnsureNotInMaintenance,
 * cas d'usage EnableMaintenance / DisableMaintenance, page 503 sobre.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

/** Compte de test avec un rôle du seeder réel. */
function maintenanceUser(string $role, string $email, string $password): User
{
    $user = User::factory()->create(['email' => $email, 'password' => $password]);
    $user->syncRoles([$role]);

    return $user;
}

/** Code OTP `login` en attente pour l'adresse, forcé à une valeur connue. */
function maintenanceLoginOtp(string $email, string $code = '135790'): OtpCode
{
    $otp = OtpCode::query()->forEmail($email, OtpPurpose::Login)->valid()->latest('id')->firstOrFail();
    $otp->forceFill(['code_hash' => OtpCode::hash($code)])->save();

    return $otp;
}

it('laisse tout passer quand la maintenance est inactive', function (): void {
    expect(MaintenanceStatus::active())->toBeFalse();

    $this->get('/')->assertOk();
    $this->get('/connexion')->assertOk();
    $this->getJson('/api/v1/health')->assertOk();
});

it('renvoie une page 503 sobre au visiteur·se et garde SOS, signalement, urgences et newsletter ouverts (RG-01, Q28 b)', function (): void {
    Settings::set('maintenance', true);
    Settings::set('maint_msg', 'Nous améliorons la plateforme pour toi.');

    $this->get('/')
        ->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertSee('<title>Maintenance en cours – Xomiton</title>', false)
        ->assertSee('Nous améliorons la plateforme pour toi.')
        ->assertSee('Nous n’avons pas encore d’heure de reprise à annoncer.')
        ->assertSee('Le SOS, le signalement et les contacts d’urgence restent disponibles')
        ->assertSee('href="'.route('sos', absolute: false).'"', false)
        ->assertSee('href="'.route('signaler', absolute: false).'"', false)
        ->assertSee('117')
        ->assertDontSee('Pour protéger votre compte, votre session a été fermée.');

    $this->get('/ressources')->assertStatus(503);
    $this->get('/sos')->assertOk();
    $this->get('/signaler')->assertOk();
    $this->get('/urgences')->assertOk();
    $this->get('/newsletter')->assertOk();
    $this->get('/connexion')->assertOk();
    $this->get('/up')->assertOk();
});

it('protège l’API sauf les parcours garantis et répond en JSON avec le code maintenance', function (): void {
    Settings::set('maintenance', true);

    $this->getJson('/api/v1/health')->assertOk();

    $this->getJson('/api/v1/me')
        ->assertStatus(503)
        ->assertHeader('Retry-After')
        ->assertJson(['message' => 'Plateforme en maintenance. Réessayer dans quelques minutes.', 'code' => 'maintenance'])
        ->assertJsonStructure(['retry_after']);

    // Un jeton de membre n'est pas révoqué, mais la ressource est indisponible.
    $member = maintenanceUser('membre', 'membre@exemple.bj', 'Membre2026!!');
    $token = $member->createToken('test')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me')->assertStatus(503)->assertJsonPath('code', 'maintenance');
    expect($member->tokens()->count())->toBe(1);
});

it('refuse la connexion d’un·e membre avec le message de maintenance, après vérification du mot de passe', function (): void {
    maintenanceUser('membre', 'awa@exemple.bj', 'Xomiton2026!');
    Settings::set('maintenance', true);

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'mauvais'])
        ->assertRedirect('/connexion')
        ->assertSessionHasErrors(['identifier' => 'Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.']);

    $this->from('/connexion')->post('/connexion', ['identifier' => 'awa@exemple.bj', 'password' => 'Xomiton2026!'])
        ->assertRedirect('/connexion')
        ->assertSessionHasErrors(['identifier' => 'La plateforme est en maintenance. Réessaie dans quelques instants.']);

    $this->assertGuest();
    expect(AuditLog::query()->where('action', 'auth.refused_maintenance')->exists())->toBeTrue();
});

it('laisse l’administration se connecter (OTP) et travailler dans /admin pendant la maintenance (RG-14)', function (): void {
    $admin = maintenanceUser('admin', 'admin@exemple.bj', 'Admin2026!!');
    Settings::set('maintenance', true);

    $this->post('/connexion', ['identifier' => 'admin@exemple.bj', 'password' => 'Admin2026!!'])
        ->assertRedirect(route('otp', ['purpose' => 'login']));

    $this->get(route('otp', ['purpose' => 'login']))->assertOk();
    maintenanceLoginOtp('admin@exemple.bj');

    $this->post('/code-de-verification', ['purpose' => 'login', 'code' => '135790'])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($admin);
    $this->get(route('admin.dashboard'))->assertOk();
    $this->get('/fil')->assertOk();
    $this->post('/deconnexion')->assertRedirect(route('home'));
    $this->assertGuest();
});

it('exempte aussi superadmin et déconnecte proprement un·e membre ou modérateur·rice déjà connecté·e', function (): void {
    $superadmin = maintenanceUser('superadmin', 'super@exemple.bj', 'Super2026!!!');
    $member = maintenanceUser('membre', 'membre@exemple.bj', 'Membre2026!!');
    $moderator = maintenanceUser('moderateur', 'modo@exemple.bj', 'Modo2026!!!');
    Settings::set('maintenance', true);

    $this->actingAs($superadmin)->get('/fil')->assertOk();
    $this->actingAs($superadmin)->get(route('admin.dashboard'))->assertOk();

    $this->actingAs($member)->get('/fil')
        ->assertStatus(503)
        ->assertSee('Pour protéger votre compte, votre session a été fermée.');
    $this->assertGuest();

    // Action du système (fermeture automatique), pas un geste du membre : ni compte auteur, ni IP, ni navigateur.
    $audit = AuditLog::query()->where('action', 'maintenance.session_closed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit?->user_id)->toBeNull()
        ->and($audit?->actor)->toBe(AuditLog::ACTOR_SYSTEM)
        ->and($audit?->ip)->toBeNull()
        ->and($audit?->user_agent)->toBeNull()
        ->and($audit?->subject_id)->toBe($member->id);

    // admin.access ne suffit pas : RG-14 ne retient que admin et superadmin.
    $this->actingAs($moderator)->get(route('admin.dashboard'))->assertStatus(503);
    $this->assertGuest();
});

it('s’active par une fenêtre maintenance_windows en cours et affiche son message et ses dates', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(14, 0));

    MaintenanceWindow::factory()->create([
        'starts_at' => now()->subMinutes(30),
        'ends_at' => now()->addHours(2)->addMinutes(30),
        'message' => 'Mise à jour de sécurité en cours.',
    ]);

    expect(MaintenanceStatus::active())->toBeTrue()
        ->and(Settings::bool('maintenance'))->toBeFalse();

    $response = $this->get('/')->assertStatus(503)
        ->assertSee('Mise à jour de sécurité en cours.')
        // 13 h 30 et 16 h 30 UTC : heures du Bénin (UTC+1, xomiton.display_timezone).
        ->assertSee("Maintenance commencée aujourd’hui à 14\u{00A0}h\u{00A0}30.")
        ->assertSee("Reprise prévue aujourd’hui à 17\u{00A0}h\u{00A0}30.");

    expect((int) $response->headers->get('Retry-After'))->toBe(3600);

    $this->get('/sos')->assertOk();
});

it('ignore une fenêtre inactive ou passée', function (): void {
    MaintenanceWindow::factory()->ongoing()->inactive()->create();
    MaintenanceWindow::factory()->past()->create();
    MaintenanceWindow::factory()->create(); // à venir

    expect(MaintenanceStatus::active())->toBeFalse();
    $this->get('/')->assertOk();
});

it('active et lève la maintenance par les cas d’usage, avec journal d’audit', function (): void {
    $admin = maintenanceUser('admin', 'admin@exemple.bj', 'Admin2026!!');
    $this->actingAs($admin);

    $window = app(EnableMaintenance::class)($admin, 'Sauvegarde en cours.', now()->addHour());

    expect($window)->toBeInstanceOf(MaintenanceWindow::class)
        ->and($window?->created_by)->toBe($admin->id)
        ->and(MaintenanceStatus::active())->toBeTrue()
        ->and(Settings::bool('maintenance'))->toBeTrue()
        ->and(Settings::string('maint_msg'))->toBe('Sauvegarde en cours.');

    $snapshot = MaintenanceStatus::snapshot();
    expect($snapshot->active)->toBeTrue()
        ->and($snapshot->message)->toBe('Sauvegarde en cours.')
        ->and($snapshot->endsAt?->equalTo($window?->ends_at))->toBeTrue()
        ->and($snapshot->retryAfterSeconds())->toBeLessThanOrEqual(3600);

    $enabled = AuditLog::query()->where('action', 'maintenance.enabled')->firstOrFail();
    expect($enabled->user_id)->toBe($admin->id)
        ->and($enabled->subject_id)->toBe($window?->id)
        ->and($enabled->detail)->toContain('Sauvegarde en cours.');

    $closed = app(DisableMaintenance::class)($admin);

    expect($closed)->toBe(1)
        ->and(MaintenanceStatus::active())->toBeFalse()
        ->and($window?->fresh()?->is_active)->toBeFalse()
        ->and(Settings::get('maint_end'))->toBeNull()
        ->and(AuditLog::query()->where('action', 'maintenance.disabled')->where('user_id', $admin->id)->exists())->toBeTrue();

    $this->get('/')->assertOk();
});

it('sans reprise annoncée, EnableMaintenance lève seulement la bascule et garde le message par défaut', function (): void {
    $admin = maintenanceUser('admin', 'admin@exemple.bj', 'Admin2026!!');

    expect(app(EnableMaintenance::class)($admin))->toBeNull()
        ->and(MaintenanceWindow::query()->count())->toBe(0)
        ->and(MaintenanceStatus::active())->toBeTrue();

    $this->get('/')->assertStatus(503)->assertSee('Xomiton est en maintenance. Nous revenons dès que possible.');
});

it('sert pendant la maintenance les référentiels du formulaire de signalement (RG-01 : category et needs obligatoires)', function (): void {
    $this->seed(Database\Seeders\ReferenceDataSeeder::class);
    Settings::set('maintenance', true);

    foreach (['/api/v1/zones', '/api/v1/violence-types', '/api/v1/needs'] as $uri) {
        $this->getJson($uri)->assertOk()->assertJsonStructure(['data'])->assertHeaderMissing(EnsureNotInMaintenance::HEADER);
    }

    $this->getJson('/api/v1/me')->assertStatus(503);
});

it('garde « mot de passe oublié » ouvert pendant la maintenance sans ouvrir de session à un·e membre (anti-verrouillage)', function (): void {
    $member = maintenanceUser('membre', 'membre@exemple.bj', 'Membre2026!!');
    Settings::set('maintenance', true);

    $this->get('/mot-de-passe-oublie')->assertOk();
    $this->get('/nouveau-mot-de-passe')->assertStatus(302);
    $this->postJson('/api/v1/auth/password/forgot', ['email' => $member->email])->assertSuccessful();
    $this->assertGuest();

    foreach (['/mot-de-passe-oublie', '/nouveau-mot-de-passe', '/api/v1/auth/password/forgot', '/api/v1/auth/password/reset'] as $uri) {
        expect(EnsureNotInMaintenance::isAlwaysAllowed(Illuminate\Http\Request::create($uri, 'POST')))->toBeTrue($uri);
    }

    // La connexion qui suivrait une réinitialisation reste refusée aux rôles non exemptés (AuthenticateUser).
    $this->from('/connexion')->post('/connexion', ['identifier' => 'membre@exemple.bj', 'password' => 'Membre2026!!'])
        ->assertSessionHasErrors(['identifier' => 'La plateforme est en maintenance. Réessaie dans quelques instants.']);
    $this->assertGuest();
});

it('reconnaît les parcours toujours servis par nom de route ou par chemin', function (): void {
    foreach (['/sos', '/signaler', '/urgences', '/newsletter', '/connexion', '/code-de-verification', '/api/v1/health', '/api/v1/signalements', '/api/v1/sos/declencher', '/up'] as $uri) {
        expect(EnsureNotInMaintenance::isAlwaysAllowed(Illuminate\Http\Request::create($uri)))->toBeTrue($uri);
    }

    foreach (['/', '/fil', '/inscription', '/api/v1/me', '/admin'] as $uri) {
        expect(EnsureNotInMaintenance::isAlwaysAllowed(Illuminate\Http\Request::create($uri)))->toBeFalse($uri);
    }
});

it('affiche la page 503 générique hors maintenance (errors/503) sans promettre que le SOS en ligne fonctionne', function (): void {
    $html = view('errors.503', ['exception' => null])->render();

    expect($html)->toContain('<title>Service momentanément indisponible – Xomiton</title>')
        ->toContain('Nous faisons le nécessaire pour rétablir le service au plus vite.')
        ->toContain('Si le SOS en ligne ne répond pas, appelez directement le 117 ou le 118.')
        ->toContain('href="tel:117"')
        ->not->toContain('Maintenance en cours')
        ->not->toContain('Pendant la maintenance')
        ->not->toContain('restent disponibles pendant la maintenance')
        ->not->toContain('ta session a été fermée');
});

it('écrit « 1er » pour le premier jour du mois et « demain » le lendemain dans les dates de la page', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 30)->setTime(9, 0));

    MaintenanceWindow::factory()->create([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->setDate(2026, 11, 1)->setTime(10, 0),
    ]);

    $this->get('/')->assertStatus(503)
        ->assertSee("Maintenance commencée aujourd’hui à 9\u{00A0}h.")
        ->assertSee("Reprise prévue le dimanche 1er novembre 2026 à 11\u{00A0}h.")
        ->assertSee('dimanche 1er novembre 2026');

    $this->travelTo(now()->setDate(2026, 10, 31)->setTime(9, 0));
    $this->get('/')->assertSee("Reprise prévue demain à 11\u{00A0}h.");
});

it('ignore une reprise déjà passée et borne le message à 280 caractères (EnableMaintenance)', function (): void {
    $admin = maintenanceUser('admin', 'admin@exemple.bj', 'Admin2026!!');
    $long = str_repeat('a', 300);

    expect(app(EnableMaintenance::class)($admin, $long, now()->subMinute()))->toBeNull()
        ->and(MaintenanceWindow::query()->count())->toBe(0)
        ->and(Settings::get('maint_end'))->toBeNull()
        ->and(mb_strlen(Settings::string('maint_msg')))->toBe(EnableMaintenance::MESSAGE_MAX_LENGTH)
        ->and(MaintenanceStatus::snapshot()->endsAt)->toBeNull();

    $this->get('/')->assertStatus(503)->assertSee('Nous n’avons pas encore d’heure de reprise à annoncer.');
});

it('sert les parcours garantis sans lire les fenêtres et rend la 503 générique si la base est injoignable', function (): void {
    Settings::set('maintenance', true);
    Illuminate\Support\Facades\Schema::drop('maintenance_windows');

    // La liste blanche est évaluée avant toute lecture en base : le SOS reste servi.
    $this->get('/sos')->assertOk();
    $this->get('/signaler')->assertOk();

    $html = view('errors.503', ['exception' => null])->render();
    expect($html)->toContain('Service momentanément indisponible')
        ->toContain('Si le SOS en ligne ne répond pas, appelez directement le 117 ou le 118.');
});
