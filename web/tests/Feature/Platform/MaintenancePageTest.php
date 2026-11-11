<?php

declare(strict_types=1);

use App\Application\Platform\MaintenancePage;
use App\Application\Platform\MaintenanceStatus;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Vite;

/*
 * P13-07 : page publique de maintenance « premium » — document autonome (CSS en ligne < 30 Ko, aucune dépendance au
 * manifest Vite), 503 + Retry-After + noindex, accès prioritaires SOS · Signaler · Urgences en tête (RG-01, D8),
 * numéros d'urgence depuis la même source que /urgences, fin prévue en clair sans JavaScript, vérification de la
 * réouverture (GET /api/v1/status), aperçu de l'administration identique.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
});

/** Contenu `.xmm` de la page (du bloc jusqu'au pied), pour comparer la page publique et ses aperçus. */
function maintenanceMain(string $html): string
{
    $start = strpos($html, '<main class="xmm-wrap xmm-main"');
    $end = strpos($html, '</main>', (int) $start);

    expect($start)->not->toBeFalse()->and($end)->not->toBeFalse();

    return (string) preg_replace('/\s+/', ' ', substr($html, (int) $start, (int) $end - (int) $start));
}

it('répond 503 avec Retry-After, noindex (balise et en-tête) et jamais en cache', function (): void {
    Settings::set('maintenance', true);

    $this->get('/ressources')
        ->assertStatus(503)
        ->assertHeader('Retry-After', '600')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('X-Xm-Maintenance', '1')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<html lang="fr">', false);
});

it('met les accès SOS · Signaler · Urgences en tête, avant le titre, et le lien discret vers l’administration', function (): void {
    Settings::set('maintenance', true);

    $html = $this->get('/')->assertStatus(503)->getContent();
    $help = strpos($html, 'class="xmm-help"');
    $title = strpos($html, '<h1');

    expect($help)->toBeLessThan($title)
        ->and($html)->toContain('<a class="xmm-btn xmm-btn--sos" href="/sos">')
        ->toContain('<a class="xmm-btn xmm-btn--report" href="/signaler">')
        ->toContain('<a class="xmm-btn xmm-btn--help" href="/urgences">')
        ->toContain('href="/connexion"')
        ->toContain('Espace administration')
        ->and(substr_count($html, '<h1'))->toBe(1);
});

it('affiche les numéros d’urgence de la même source que /urgences, en liens tel:', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte VBG', 'number' => '+229 01 02 03 04']]);
    Settings::set('maintenance', true);

    $this->get('/')->assertStatus(503)
        ->assertSee('Ligne verte VBG')
        ->assertSee('href="tel:+22901020304"', false)
        ->assertDontSee('Police secours');

    expect(MaintenanceStatus::emergencyNumbers())->toBe([['label' => 'Ligne verte VBG', 'number' => '+229 01 02 03 04']]);
    $this->get('/urgences')->assertOk()->assertSee('+229 01 02 03 04');
});

it('s’affiche sans manifest Vite : CSS et script en ligne, aucune ressource du build', function (): void {
    Vite::useBuildDirectory('build-absent-'.uniqid());
    Settings::set('maintenance', true);
    MaintenanceWindow::factory()->ongoing()->create(['message' => 'Mise à jour de la base.']);

    $html = $this->get('/')->assertStatus(503)->getContent();

    expect($html)->toContain('Mise à jour de la base.')
        ->toContain('<style nonce=')
        ->toContain('.xmm{')
        ->toContain('data-status-url="/api/v1/status"')
        ->not->toContain('/build/')
        ->not->toContain('<link rel="stylesheet"')
        ->and($html)->toMatch('/font-family:"Montserrat";[^}]*src:local\("Montserrat ExtraBold"\),local\("Montserrat-ExtraBold"\);/');
});

it('garde un CSS en ligne de moins de 30 Ko, sans hexadécimal hors des blocs de jetons', function (): void {
    $css = MaintenancePage::pageCss();

    expect(strlen($css))->toBeGreaterThan(5000)->toBeLessThan(30 * 1024)
        // Minifié (aucun commentaire livré) et jetons sombres écrits une fois, la préférence du système en est dérivée.
        ->and($css)->not->toContain('/*')
        ->and(substr_count((string) file_get_contents(resource_path('views/maintenance/content.css')), '--xm-primary-subtle:#1B2347'))->toBe(1)
        ->and($css)->toContain('@media (prefers-color-scheme:dark){:root:not([data-bs-theme="light"]) .xmm:not([data-xm-theme="light"]){--xm-primary:var(--xm-blue-dark-accent)')
        // Aucune boucle infinie (WCAG 2.2.2) ; le rouge n'est plus décoratif (badge de réassurance).
        ->not->toContain('infinite')
        ->not->toContain('xmm-badge--alert')
        // Dépassement : masque le compte à rebours même quand le script l'a affiché (.xmm.xmm-js .xmm-js-only).
        ->toContain('.xmm.xmm-overdue .xmm-countdown');

    // Hors des déclarations de jetons (--xm-…), aucune couleur en dur.
    $rules = (string) preg_replace('/--xm-[a-z0-9-]+:[^;}]*[;}]/i', '', $css);
    expect(preg_match('/#[0-9a-f]{3,8}\b/i', $rules))->toBe(0)
        ->and($css)->toContain('@media (prefers-reduced-motion:reduce)')
        ->toContain('@media (prefers-color-scheme:dark)')
        ->toContain('[data-bs-theme="dark"] .xmm');
});

it('écrit la fin prévue en clair (sans JavaScript) et prépare le compte à rebours et la barre d’avancement', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(14, 0));
    MaintenanceWindow::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'message' => null]);

    $html = $this->get('/')->assertStatus(503)->getContent();

    // 15 h UTC = 16 h au Bénin (xomiton.display_timezone) ; data-ends-at reste en ISO UTC.
    expect($html)->toContain("Reprise prévue aujourd’hui à 16\u{00A0}h.")
        ->toContain("<strong class=\"xmm-status__big\" data-xmm-ends>16\u{00A0}h</strong>")
        ->toContain('lundi 2 novembre 2026')
        ->toContain('data-ends-at="2026-11-02T15:00:00+00:00"')
        ->toContain('data-xmm-countdown')
        ->toContain('aria-valuenow="50"')
        ->toContain('aria-live="polite"')
        ->toContain('Xomiton est en maintenance. Nous revenons dès que possible.')
        ->toContain('data-announce="60,30,15,5"')
        ->toContain('xmm-nojs-only');
});

it('affiche le dépassement de la reprise annoncée au lieu d’une heure passée (activation immédiate)', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(4, 0));
    app(App\Application\Platform\EnableMaintenance::class)($admin, null, now()->addMinutes(90), 'Migration');
    $this->travelTo(now()->addHours(2));

    $html = $this->get('/')->assertStatus(503)->getContent();

    expect($html)->toContain('class="xmm xmm-overdue"')
        ->toContain('<span data-xmm-until>La reprise prend un peu plus de temps que prévu. Merci de votre patience.</span>')
        ->toContain('<strong class="xmm-status__big" data-xmm-ends>Bientôt</strong>')
        ->not->toContain('data-xmm-countdown aria-hidden')
        ->not->toContain('role="progressbar"')
        ->not->toContain('Reprise prévue aujourd’hui')
        ->toContain("Maintenance commencée aujourd’hui à 5\u{00A0}h.");
});

it('nomme chaque numéro « Appeler … » en gardant le texte visible (WCAG 2.5.3), logo en ligne sans image', function (): void {
    Settings::set('maintenance', true);

    $html = $this->get('/')->assertStatus(503)->getContent();

    expect($html)->toMatch('#<a class="xmm-number" href="tel:117">\s*<span class="xmm-vh">Appeler </span>\s*<span class="xmm-number__label">Police secours</span>\s*<strong class="xmm-number__value">117</strong>#')
        ->not->toContain('aria-label="Appeler')
        ->toContain('<svg class="xmm-logo__svg"')
        ->not->toContain('logo-sombre.svg')
        ->not->toContain('logo-couleur.svg')
        ->not->toContain('xmm-chip')
        ->toContain('<p class="xmm-eyebrow"><span class="xmm-pulse" aria-hidden="true"></span>Maintenance en cours</p>')
        ->toContain('Une pause pour <span class="xmm-mark">mieux vous accueillir</span>.')
        ->toContain('La mise à jour respecte vos réglages de confidentialité.')
        ->not->toContain('Rien n’est perdu');
});

it('sans heure de reprise : « Bientôt », pas de compte à rebours ni de barre', function (): void {
    Settings::set('maintenance', true);

    $html = $this->get('/')->assertStatus(503)->getContent();

    expect($html)->toContain('<strong class="xmm-status__big" data-xmm-ends>Bientôt</strong>')
        ->not->toContain('data-xmm-countdown')
        ->not->toContain('role="progressbar"')
        ->toContain('Nous n’avons pas encore d’heure de reprise à annoncer.');
});

it('vouvoie le message d’une session fermée (un seul registre sur la page)', function (): void {
    $member = User::factory()->create();
    $member->syncRoles(['membre']);
    Settings::set('maintenance', true);

    $this->actingAs($member)->get('/fil')->assertStatus(503)
        ->assertSee('Pour protéger votre compte, votre session a été fermée.')
        ->assertDontSee(' ton compte');
});

it('rend la page 503 générique (abort) sans compte à rebours ni vérification de réouverture', function (): void {
    $html = view('errors.503', ['exception' => null])->render();

    expect($html)->toContain('Nous faisons le nécessaire pour rétablir le service au plus vite.')
        ->toContain('Xomiton est <span class="xmm-mark">momentanément indisponible</span>.')
        ->toContain('Si le SOS en ligne ne répond pas, appelez directement le 117 ou le 118.')
        ->toContain('href="tel:118"')
        ->not->toContain('<section class="xmm-card xmm-reassure"')
        ->not->toContain('<span class="xmm-pulse"')
        ->not->toContain('Maintenance en cours')
        ->toContain('href="/sos"')
        ->not->toContain('data-status-url')
        ->not->toContain('<script nonce="'."\n");
});

it('montre à l’administration un aperçu identique à la page publique (voir comme un visiteur)', function (): void {
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(14, 0));
    MaintenanceWindow::factory()->create(['starts_at' => now()->subHour(), 'ends_at' => now()->addHour(), 'message' => 'Migration en cours.']);

    $public = $this->get('/')->assertStatus(503)->getContent();
    $preview = $this->actingAs($admin)->get(route('admin.maintenance.preview'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('Aperçu de la page publique')
        ->getContent();

    $strip = fn (string $html): string => (string) preg_replace('/href="(http[^"]*|#)"/', '', $html);
    expect(maintenanceMain($strip($preview)))->toBe(maintenanceMain($strip($public)))
        ->and($preview)->not->toContain('data-status-url');

    // Brouillon de message : affiché dans l'aperçu, jamais enregistré.
    $this->get(route('admin.maintenance.preview', ['message' => 'Brouillon du message.', 'minutes' => 30]))
        ->assertOk()->assertSee('Brouillon du message.');
    expect(MaintenanceStatus::snapshot()->message)->toBe('Migration en cours.');
});

it('réserve l’aperçu à platform.maintenance', function (): void {
    $member = User::factory()->create();
    $member->syncRoles(['membre']);
    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);

    $this->actingAs($member)->get(route('admin.maintenance.preview'))->assertForbidden();
    $this->actingAs($moderator)->get(route('admin.maintenance.preview'))->assertForbidden();
});
