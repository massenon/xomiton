<?php

declare(strict_types=1);

use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceStatus;
use App\Application\Platform\ScheduleMaintenance;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/*
 * P13-08 : comportement des espaces pendant la maintenance — bandeau d'annonce préalable (public, connexion, membre
 * Vue, administration) visible puis absent, visite Inertia refusée en JSON `maintenance` (écran XmMaintenance), contrat
 * API `{ message, code, retry_after, ends_at, maintenance_message }`, `GET /api/v1/status` public et servi pendant la
 * maintenance, SOS anonyme accepté (RG-01), administration jamais bloquée, tâches planifiées hors HTTP.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
    Notification::fake();
});

function spacesUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function scheduleAnnounced(int $announceHours = 24): MaintenanceWindow
{
    // 21 h à 22 h 30 UTC : de 22 h à 23 h 30 au Bénin (UTC+1, heures affichées).
    return app(ScheduleMaintenance::class)(spacesUser('admin'), now()->setTime(21, 0), now()->setTime(22, 30), 'Mise à jour de la messagerie.', $announceHours, 'Plan');
}

describe('annonce préalable', function (): void {
    it('affiche le bandeau sur les gabarits public et connexion pendant le délai d’annonce, puis plus du tout', function (): void {
        scheduleAnnounced(6);

        $this->get('/')->assertOk()->assertDontSee('data-xm-maintenance-notice', false);

        $this->travelTo(now()->setTime(17, 0));
        $this->get('/')->assertOk()
            ->assertSee('data-xm-maintenance-notice', false)
            ->assertSee('Maintenance prévue')
            ->assertSee("Une maintenance est prévue aujourd’hui de 22\u{00A0}h à 23\u{00A0}h\u{00A0}30.")
            ->assertSee('Mise à jour de la messagerie.')
            ->assertSee('data-xm-dismiss-key="maint-', false)
            ->assertSee('data-bs-dismiss="alert"', false);
        // Annonce planifiée : lue poliment (status), jamais de façon assertive (alert : erreurs et SOS).
        expect($this->get('/')->getContent())->toMatch('/data-xm-dismiss-key="maint-[^"]+" role="status"/');
        $this->get('/connexion')->assertOk()->assertSee('data-xm-maintenance-notice', false);

        $this->travelTo(now()->setTime(21, 0));
        $this->get('/')->assertStatus(503);

        $this->travelTo(now()->setTime(22, 31));
        $this->get('/')->assertOk()->assertDontSee('data-xm-maintenance-notice', false);
    });

    it('n’annonce pas une fenêtre sans délai d’annonce ni une fenêtre annulée', function (): void {
        $window = scheduleAnnounced(0);
        $this->get('/')->assertOk()->assertDontSee('data-xm-maintenance-notice', false);

        $window->update(['announce_hours' => 24, 'is_active' => false, 'cancelled_at' => now()]);
        $this->get('/')->assertOk()->assertDontSee('data-xm-maintenance-notice', false);
    });

    it('partage l’annonce avec l’espace membre Vue (prop maintenance_notice) et l’affiche dans l’administration', function (): void {
        scheduleAnnounced();
        $member = spacesUser('membre');

        $this->actingAs($member)->get('/fil')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('maintenance_notice.title', 'Maintenance prévue')
                ->where('maintenance_notice.message', 'Mise à jour de la messagerie.')
                ->where('maintenance_notice.starts_at', '2026-11-02T21:00:00+00:00')
                ->where('maintenance_notice.text', fn (string $text): bool => str_contains($text, 'aujourd’hui de 22'))
                ->where('maintenance_notice.key', fn (string $key): bool => str_starts_with($key, 'maint-')));

        $this->actingAs(spacesUser('admin'))->get(route('admin.dashboard'))->assertOk()
            ->assertSee('data-xm-maintenance-notice', false)
            ->assertDontSee('data-xm-maintenance-banner', false);
    });

    it('n’envoie aucune annonce à l’espace membre sans fenêtre annoncée', function (): void {
        $this->actingAs(spacesUser('membre'))->get('/fil')->assertOk()->assertInertia(fn ($page) => $page->where('maintenance_notice', null));
    });
});

describe('pendant la maintenance', function (): void {
    it('répond à une visite Inertia de l’espace membre par le JSON `maintenance` (écran XmMaintenance), jamais une page HTML', function (): void {
        $member = spacesUser('membre');
        app(EnableMaintenance::class)(spacesUser('admin'), 'Mise à jour de sécurité.', now()->addMinutes(90), 'Sécurité');

        $this->actingAs($member)
            ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
            ->get('/fil')
            ->assertStatus(503)
            ->assertHeader('X-Xm-Maintenance', '1')
            ->assertHeader('Retry-After', '3600')
            ->assertJson([
                'code' => 'maintenance',
                'message' => 'Plateforme en maintenance. Réessayer dans quelques minutes.',
                'retry_after' => 3600,
                'ends_at' => '2026-11-02T11:30:00+00:00',
                'maintenance_message' => 'Mise à jour de sécurité.',
            ]);
        $this->assertGuest();
    });

    it('documente le contrat API pour le mobile : message, code, retry_after, ends_at, overdue, timezone, maintenance_message', function (): void {
        Settings::set('maintenance', true);
        Settings::set('maint_msg', 'Nous revenons vite.');

        $this->getJson('/api/v1/me')
            ->assertStatus(503)
            ->assertHeader('X-Xm-Maintenance', '1')
            ->assertExactJson([
                'message' => 'Plateforme en maintenance. Réessayer dans quelques minutes.',
                'code' => 'maintenance',
                'retry_after' => 600,
                'ends_at' => null,
                'overdue' => false,
                'timezone' => 'Africa/Porto-Novo',
                'maintenance_message' => 'Nous revenons vite.',
            ]);
    });

    it('accepte un SOS anonyme et un signalement anonyme pendant la maintenance (RG-01)', function (): void {
        Settings::set('maintenance', true);

        $this->withHeader(HandleIdempotencyKey::HEADER, (string) Str::uuid())
            ->postJson('/api/v1/sos', ['message' => 'Je suis en danger.'])
            ->assertCreated()
            ->assertJsonPath('data.type', 'sos');

        expect(Alert::query()->where('type', 'sos')->where('is_anonymous', true)->exists())->toBeTrue();
        $this->get('/sos')->assertOk();
        $this->get('/signaler')->assertOk();
        $this->getJson('/api/v1/urgences')->assertOk();
    });

    it('laisse travailler l’administration (pages et écran maintenance) et poursuit les tâches planifiées', function (): void {
        Settings::set('maintenance', true);

        $this->actingAs(spacesUser('admin'))->get(route('admin.alertes'))->assertOk();
        $this->actingAs(spacesUser('superadmin'))->get(route('admin.maintenance'))->assertOk();

        $this->artisan('heartbeat:ping')->assertSuccessful();
        $this->artisan('mail:flush-outbox')->assertSuccessful();
    });
});

describe('GET /api/v1/status', function (): void {
    it('décrit une plateforme ouverte, sans compte, avec les numéros d’urgence et l’heure du serveur', function (): void {
        $this->getJson('/api/v1/status')
            ->assertOk()
            ->assertHeader('Cache-Control', 'max-age=15, public')
            ->assertJsonPath('data.status', 'ok')
            ->assertJsonPath('data.maintenance.active', false)
            ->assertJsonPath('data.maintenance.message', null)
            ->assertJsonPath('data.announcement', null)
            ->assertJsonPath('data.emergency_numbers.0.number', '117')
            ->assertJsonPath('data.timezone', 'Africa/Porto-Novo')
            ->assertJsonPath('data.server_time', '2026-11-02T10:00:00+00:00');
    });

    it('ne pose aucun cookie, même appelé depuis le site (Referer de même origine, Sanctum « stateful »)', function (): void {
        Settings::set('maintenance', true);
        $origin = rtrim((string) config('app.url'), '/');

        foreach ([[], ['Referer' => $origin.'/ressources', 'Origin' => $origin]] as $headers) {
            $response = $this->withHeaders($headers)->getJson('/api/v1/status')->assertOk()->assertHeader('Cache-Control', 'max-age=15, public');

            expect($response->headers->getCookies())->toBe([])
                ->and($response->headers->has('Set-Cookie'))->toBeFalse();
        }

        // Même règle pour les autres lectures publiques en cache partagé (référentiels du signalement, urgences).
        $this->seed(ReferenceDataSeeder::class);
        foreach (['/api/v1/zones', '/api/v1/violence-types', '/api/v1/needs', '/api/v1/urgences'] as $uri) {
            $response = $this->withHeaders(['Referer' => $origin.'/signaler'])->getJson($uri)->assertOk();
            expect($response->headers->getCookies())->toBe([], $uri)
                ->and((string) $response->headers->get('Cache-Control'))->toContain('public');
        }

        // Témoin : une route du même groupe sans exclusion ouvre bien la session quand la requête est « stateful ».
        $this->withHeaders(['Referer' => $origin.'/ressources'])->getJson('/api/v1/health')->assertCookie((string) config('session.cookie'));
    });

    it('signale une reprise annoncée dépassée (overdue), maintenance toujours active', function (): void {
        app(EnableMaintenance::class)(spacesUser('admin'), null, now()->addMinutes(30), 'Migration');
        $this->travelTo(now()->addHour());
        MaintenanceStatus::forget();

        $this->getJson('/api/v1/status')
            ->assertJsonPath('data.maintenance.active', true)
            ->assertJsonPath('data.maintenance.ends_at', null)
            ->assertJsonPath('data.maintenance.overdue', true);
        $this->getJson('/api/v1/me')->assertStatus(503)->assertJsonPath('overdue', true)->assertJsonPath('timezone', 'Africa/Porto-Novo');
    });

    it('reste servi pendant la maintenance : actif, message, dates, retry_after', function (): void {
        app(EnableMaintenance::class)(spacesUser('admin'), 'Mise à jour de sécurité.', now()->addMinutes(30), 'Sécurité');

        $this->getJson('/api/v1/status')
            ->assertOk()
            ->assertHeader('Retry-After', '1800')
            ->assertJsonPath('data.status', 'maintenance')
            ->assertJsonPath('data.maintenance.active', true)
            ->assertJsonPath('data.maintenance.message', 'Mise à jour de sécurité.')
            ->assertJsonPath('data.maintenance.starts_at', '2026-11-02T10:00:00+00:00')
            ->assertJsonPath('data.maintenance.ends_at', '2026-11-02T10:30:00+00:00')
            ->assertJsonPath('data.maintenance.retry_after', 1800);
    });

    it('annonce une fenêtre planifiée et suit chaque écriture malgré le cache court', function (): void {
        $window = scheduleAnnounced();

        $this->getJson('/api/v1/status')
            ->assertJsonPath('data.announcement.starts_at', '2026-11-02T21:00:00+00:00')
            ->assertJsonPath('data.announcement.ends_at', '2026-11-02T22:30:00+00:00')
            ->assertJsonPath('data.announcement.message', 'Mise à jour de la messagerie.');

        $window->update(['is_active' => false, 'cancelled_at' => now()]);
        $this->getJson('/api/v1/status')->assertJsonPath('data.announcement.starts_at', '2026-11-02T21:00:00+00:00'); // cache 15 s
        MaintenanceStatus::forget();
        $this->getJson('/api/v1/status')->assertJsonPath('data.announcement', null);

        Settings::set('maintenance', true);
        MaintenanceStatus::forget();
        $this->getJson('/api/v1/status')->assertJsonPath('data.maintenance.active', true);
    });
});
