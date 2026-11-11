<?php

declare(strict_types=1);

use App\Application\Retention\PurgeAuditLogs;
use App\Livewire\Admin\Journal\Index as Journal;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\CsvExport;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
 * P13-05 : journal d'audit /admin/journal (Livewire, écran témoin de P10-02) — lecture `audit_logs.view` (admin,
 * superadmin ; 403 modérateur et membre), KPI, familles d'actions, période au fuseau du Bénin, export CSV des filtres
 * actifs par lien signé (contenu lu : BOM, « ; », formule neutralisée) journalisé, purge des entrées de plus de N jours
 * réservée au superadmin (403 admin), saisie « PURGER » revérifiée côté serveur, bornes 7 à 3650 jours, champ « jours »
 * vidé sans erreur, purge journalisée, paramètres d'URL mal formés ignorés (200).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function journalUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('sert le journal à l’admin et au superadmin, le refuse au modérateur et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(journalUser($role))->get(route('admin.journal'))
            ->assertOk()
            ->assertSeeLivewire(Journal::class)
            ->assertSee('Journal d’audit')
            ->assertSee('Indicateurs clés')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="journal"\s+aria-current="page"/');
    }

    $this->actingAs(journalUser('moderateur'))->get(route('admin.journal'))->assertForbidden();
    $this->actingAs(journalUser('membre'))->get(route('admin.journal'))->assertForbidden();
});

it('affiche les KPI, le compte, la famille et le code de chaque action', function (): void {
    $this->actingAs(journalUser('admin'));
    AuditLog::factory()->create(['action' => 'alerts.acknowledged', 'actor' => 'agent@xomiton.test', 'created_at' => now()->subHour()]);
    AuditLog::factory()->system()->create(['action' => 'maintenance.enabled', 'created_at' => now()->subDays(3)]);
    AuditLog::factory()->anonymous()->create(['action' => 'auth.lockout', 'created_at' => now()->subDays(4)]);

    Livewire::test(Journal::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 3)
        ->assertSee(['Entrées', 'Aujourd’hui', 'Comptes distincts', 'Résultats du filtre'])
        ->assertSee(['agent@xomiton.test', 'système', 'anonyme'])
        ->assertSee(['Alerte', 'Maintenance', 'Connexion'])
        ->assertSee('alerts.acknowledged')
        ->assertSeeHtml('data-label="Action"');

    expect(Journal::family('post.deleted_by_moderation'))->toBe('moderation')
        ->and(Journal::family('group.words_flagged'))->toBe('moderation')
        ->and(Journal::family('case_followups.created'))->toBe('cases')
        ->and(Journal::family('backup.restored'))->toBe('backup')
        ->and(Journal::family('settings.updated'))->toBe('settings')
        ->and(Journal::family('optimization.sw'))->toBe('update')
        ->and(Journal::family('update.published'))->toBe('update')
        ->and(Journal::family('sync.conflict_resolved'))->toBe('sync')
        ->and(Journal::family('xyz'))->toBe('other')
        ->and(Journal::familyColor('alerts'))->toBe('danger');
});

it('filtre la période dans le fuseau d’affichage (journée du Bénin) et signale une période inversée', function (): void {
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
    $this->actingAs(journalUser('admin'));
    // 1er novembre 23 h 30 UTC = 2 novembre 00 h 30 à Porto-Novo (UTC+1).
    AuditLog::factory()->create(['action' => 'auth.logout', 'created_at' => '2026-11-01 23:30:00']);
    AuditLog::factory()->create(['action' => 'auth.logout', 'created_at' => '2026-11-01 22:30:00']);

    Livewire::withQueryParams(['du' => '2026-11-02'])->test(Journal::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('until', '2026-11-01')
        ->assertSee('Période invalide')
        ->set('from', 'pas-une-date')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1);
});

it('exporte en CSV les filtres actifs par lien signé, sans pagination, et journalise l’export', function (): void {
    $admin = journalUser('admin');
    $this->actingAs($admin);
    AuditLog::factory()->count(29)->create(['action' => 'auth.authenticated', 'created_at' => now()->subHour()]);
    AuditLog::factory()->create(['action' => 'auth.authenticated', 'actor' => 'agent@xomiton.test', 'detail' => '=HYPERLINK("http://piege.invalid")', 'created_at' => now()->subMinute()]);
    AuditLog::factory()->create(['action' => 'maintenance.enabled', 'detail' => 'Hors filtre']);

    // L'action Livewire ne télécharge rien elle-même : elle renvoie vers un lien signé de courte durée.
    $url = Livewire::withQueryParams(['ac' => 'auth.authenticated'])->test(Journal::class)
        ->call('export')
        ->assertNoFileDownloaded()
        ->effects['redirect'] ?? null;

    expect($url)->toBeString()->toContain('/admin/export?')->toContain('signature=')->not->toContain('auth.authenticated');
    expect(AuditLog::query()->where('action', 'export.csv')->count())->toBe(0);

    $response = $this->get($url)->assertOk()->assertDownload(CsvExport::filename('journal'));
    $csv = $response->streamedContent();
    $lines = explode("\n", trim($csv));

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($csv)->toStartWith("\u{FEFF}Date;Compte;Action;Détail\n")
        ->and($lines)->toHaveCount(31)
        ->and($lines[1])->toContain(';agent@xomiton.test;auth.authenticated;"\'=HYPERLINK(""http://piege.invalid"")"')
        ->and($csv)->not->toContain('Hors filtre');

    expect(AuditLog::query()->where('action', 'export.csv')->sole()->detail)->toBe('Export CSV journal, 30 lignes');

    // Lien lié au compte qui l'a demandé, et signé : un autre compte ou une URL retouchée reçoivent 403.
    $this->actingAs(journalUser('admin'))->get($url)->assertForbidden();
    $this->actingAs($admin)->get(str_replace('signature=', 'signature=0', $url))->assertForbidden();
});

it('refuse l’export au-delà du plafond de lignes, à la demande comme au téléchargement', function (): void {
    $this->actingAs(journalUser('admin'));
    AuditLog::factory()->count(3)->create(['action' => 'auth.authenticated']);
    config(['xomiton.exports.max_rows' => 2]);

    Livewire::test(Journal::class)->call('export')
        ->assertNoRedirect()
        ->assertDispatched('toast', type: 'error', message: "Trop de lignes à exporter (3)\u{00A0}: affinez les filtres, 2 lignes au plus.");

    config(['xomiton.exports.max_rows' => 10]);
    $url = Livewire::test(Journal::class)->call('export')->effects['redirect'];
    config(['xomiton.exports.max_rows' => 2]);

    $this->from(route('admin.journal'))->get($url)->assertRedirect(route('admin.journal'))->assertSessionHas('toast_type', 'error');
    expect(AuditLog::query()->where('action', 'like', 'export.%')->count())->toBe(0);
});

it('ignore les paramètres d’URL mal formés au lieu d’échouer (500)', function (string $query): void {
    $this->actingAs(journalUser('admin'));
    AuditLog::factory()->count(2)->create();

    $this->get(route('admin.journal').'?'.$query)->assertOk()->assertSee('Journal d’audit');
})->with([
    'par=abc', 'par=1.5', 'par[]=10', 'q[]=x', 'q[a]=x', 'tri[]=x', 'sens[]=x', 'ac[]=x', 'du[]=2026-01-01', 'au[x]=1',
    'page=abc', 'q=%7B%22a%22%3A1%7D', 'tri=%5B1%5D',
]);

it('refuse la purge à l’admin (403) et la réserve au superadmin', function (): void {
    $this->actingAs(journalUser('admin'));

    Livewire::test(Journal::class)
        ->assertSee('Super-admin requis')
        ->assertDontSeeHtml('id="xmJournalPurge"')
        ->set('purgeWord', 'PURGER')
        ->call('purge')
        ->assertForbidden();
});

it('purge au-delà de N jours après saisie de PURGER, revérifiée côté serveur, et journalise la purge', function (): void {
    $this->actingAs(journalUser('superadmin'));
    AuditLog::factory()->count(3)->create(['created_at' => now()->subDays(120)]);
    AuditLog::factory()->count(2)->create(['created_at' => now()->subDays(10)]);

    $component = Livewire::test(Journal::class)
        ->assertSeeHtml('id="xmJournalPurge"')
        ->assertSeeHtml('data-xm-confirm-word="PURGER"')
        ->set('purgeDays', 90)
        ->assertSee('(3 entrées concernées)')
        ->set('purgeWord', 'purger')
        ->call('purge')
        ->assertHasErrors(['purgeWord' => 'in']);

    expect(AuditLog::query()->count())->toBe(5);

    $component->set('purgeDays', 3)->set('purgeWord', 'PURGER')->call('purge')->assertHasErrors(['purgeDays' => 'min']);
    $component->set('purgeDays', 90)->set('purgeWord', 'PURGER')->call('purge')
        ->assertHasNoErrors()
        ->assertSet('purgeWord', '')
        ->assertDispatched('toast', type: 'success', message: '3 entrées purgées.')
        ->assertDispatched('xm-modal', id: 'xmJournalPurge', action: 'hide');

    expect(AuditLog::query()->where('created_at', '<', now()->subDays(90))->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'audit.purged')->sole()->detail)->toBe("Purge\u{00A0}: 3 entrées de plus de 90 jours");
});

it('accepte un champ « jours » vidé sans erreur, puis le refuse à la purge', function (): void {
    $this->actingAs(journalUser('superadmin'));
    AuditLog::factory()->create(['created_at' => now()->subDays(120)]);

    Livewire::test(Journal::class)
        ->set('purgeDays', '')
        ->assertOk()
        ->assertSet('purgeDays', null)
        ->assertSee('Entre 7 et 3')
        ->set('purgeDays', 100)
        ->assertSee('(1 entrée concernée)')
        ->set('purgeDays', '')
        ->set('purgeWord', 'PURGER')
        ->call('purge')
        ->assertHasErrors(['purgeDays' => 'required']);

    expect(AuditLog::query()->count())->toBe(1);
});

it('garde la durée de la configuration si le réglage audit_log_days est hors bornes (pas de 500)', function (): void {
    $this->actingAs(journalUser('superadmin'));
    Settings::set(PurgeAuditLogs::SETTING_KEY, 2);
    config(['xomiton.retention.audit_log_days' => 60]);

    Livewire::test(Journal::class)->assertOk()->assertSet('purgeDays', 60)->assertSee('Entrées conservées 60 jours');
});

it('ne compte les entrées à purger que pour le superadmin', function (): void {
    $this->actingAs(journalUser('admin'));

    Livewire::test(Journal::class)->assertViewHas('purgeCount', 0)->assertViewHas('canPurge', false);
});

it('reprend la rétention automatique (audit_log_days) comme durée proposée', function (): void {
    $this->actingAs(journalUser('superadmin'));
    Settings::set(PurgeAuditLogs::SETTING_KEY, 30);

    Livewire::test(Journal::class)->assertSet('purgeDays', 30)->assertSee('Entrées conservées 30 jours');
});
