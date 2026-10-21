<?php

declare(strict_types=1);

use App\Application\Cases\FollowupBoard;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Livewire\Admin\Followups\Index as Followups;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\User;
use App\Support\CsvExport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P11-03 : Suivi des victimes /admin/suivi (Livewire, WithDataTable) — accès `case_followups.manage` (403 modérateur,
 * pair·e, membre), KPI recalculés après « Marquer réalisé », frise d'un dossier (commentaires lus ici seulement, lecture
 * journalisée), ajout d'étape idempotent, dossiers archivés hors listes et KPI, recherche sur colonnes en clair (un mot
 * du commentaire chiffré ne filtre pas, ADR-33), export CSV sans commentaire.
 */

const FOLLOWUPS_COMMENT = 'Commentaire confidentiel sur la situation familiale';

beforeEach(function (): void {
    CaseFixtures::seed();
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function followupsUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** @param  array<string, mixed>  $overrides */
function followupsCase(string $code, string $occurredOn = '2026-10-01', array $overrides = []): CaseRecord
{
    return CaseRecord::factory()->create(array_replace(['code' => $code, 'occurred_on' => $occurredOn], $overrides));
}

/** @param  array<string, mixed>  $overrides */
function followupsStep(CaseRecord $case, array $overrides = []): CaseFollowup
{
    return CaseFollowup::factory()->create(array_replace([
        'case_id' => $case->id,
        'comment' => FOLLOWUPS_COMMENT,
        'outcome' => FollowupOutcome::Done,
        'next_on' => null,
    ], $overrides));
}

it('sert le suivi à l’admin et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(followupsUser($role))->get(route('admin.suivi'))
            ->assertOk()->assertSeeLivewire(Followups::class)->assertSee('Suivi des victimes')->getContent();
        expect($html)->toMatch('/data-xm-nav="suivi"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(followupsUser($role))->get(route('admin.suivi'))->assertForbidden();
    }

    $user = followupsUser('moderateur');
    $user->givePermissionTo('case_followups.view_own');
    Livewire::actingAs($user)->test(Followups::class)->assertForbidden();
});

it('calcule les KPI : % réalisés, retards, délais, dossiers suivis, suivis à venir', function (): void {
    $this->actingAs(followupsUser('admin'));
    $a = followupsCase('VBG-0001', '2026-10-01');
    $b = followupsCase('VBG-0002', '2026-10-05');
    followupsStep($a, ['date' => '2026-10-03', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-10-20']);
    followupsStep($a, ['date' => '2026-10-10', 'outcome' => FollowupOutcome::Done, 'next_on' => '2026-11-10']);
    followupsStep($a, ['date' => '2026-10-12', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-11-05']);
    followupsStep($b, ['date' => '2026-10-05', 'outcome' => FollowupOutcome::Done]);
    // Les étapes d'un dossier archivé ne comptent pas.
    followupsStep(followupsCase('VBG-0003', '2026-09-01', ['status' => CaseStatus::Archived, 'archived_at' => now()]), ['date' => '2026-09-02', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-09-10']);

    expect(app(FollowupBoard::class)->kpis())->toBe([
        'total' => 4, 'done' => 2, 'percent' => 50, 'overdue' => 1, 'upcoming' => 1, 'gap_days' => 24, 'first_days' => 1, 'cases' => 2,
    ]);

    Livewire::test(Followups::class)->assertSee(['Suivis réalisés', 'Suivis en retard', 'Délai moyen entre suivis', 'Survivant·es suivi·es']);
});

it('liste les étapes sans commentaire, hors dossiers archivés, et ne cherche jamais le commentaire', function (): void {
    $this->actingAs(followupsUser('admin'));
    $peer = User::factory()->create(['pseudo' => 'Awa-Suivi']);
    $a = followupsCase('VBG-0001');
    followupsStep($a, ['step' => 'Appui juridique', 'by_user_id' => $peer->id]);
    followupsStep($a, ['step' => 'Écoute']);
    followupsStep(followupsCase('VBG-0009', '2026-09-01', ['status' => CaseStatus::Archived, 'archived_at' => now()]), ['step' => 'Hébergement']);

    Livewire::test(Followups::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->assertSee(['VBG-0001', 'Appui juridique'])
        ->assertDontSee('VBG-0009')
        ->assertDontSee(FOLLOWUPS_COMMENT)
        ->set('search', 'confidentiel')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', 'Awa-Suivi')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', 'juridique')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', 'VBG-0001')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->set('search', '')
        ->set('outcome', 'planifie')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0);
});

it('filtre les étapes en retard et les met en évidence', function (): void {
    $this->actingAs(followupsUser('admin'));
    $a = followupsCase('VBG-0001');
    followupsStep($a, ['step' => 'Accueil', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-10-20']);
    followupsStep($a, ['step' => 'Écoute', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-11-20']);
    followupsStep($a, ['step' => 'Clôture', 'outcome' => FollowupOutcome::Done, 'next_on' => '2026-10-02']);

    Livewire::test(Followups::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 3)
        ->set('late', true)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('step')->all() === ['Accueil'])
        ->assertSee('En retard (1)');
});

it('affiche la frise d’un dossier avec ses commentaires et journalise l’ouverture sans le contenu', function (): void {
    $this->actingAs(followupsUser('admin'));
    $case = followupsCase('VBG-0001');
    followupsStep($case, ['step' => 'Accueil', 'date' => '2026-10-02']);
    followupsStep($case, ['step' => 'Écoute', 'date' => '2026-10-09', 'outcome' => FollowupOutcome::Planned, 'next_on' => '2026-10-20']);

    $component = Livewire::test(Followups::class)->assertDontSee(FOLLOWUPS_COMMENT)
        ->set('caseInput', 'vbg 1')->call('showCase')
        ->assertSet('dossier', 'VBG-0001')
        ->assertSee('Frise de suivi du dossier VBG-0001')
        ->assertSee(FOLLOWUPS_COMMENT)
        ->assertSee('En retard');

    $log = AuditLog::query()->where('action', 'case_followups.viewed')->sole();
    expect($log->detail)->toBe('dossier=VBG-0001')->not->toContain('confidentiel');

    $component->call('closeCase')->assertSet('dossier', '')->assertDontSee(FOLLOWUPS_COMMENT);

    // Code inconnu, ou dossier archivé : aucune frise.
    followupsCase('VBG-0002', '2026-09-01', ['status' => CaseStatus::Archived, 'archived_at' => now()]);
    $component->set('caseInput', 'VBG-0099')->call('showCase')->assertHasErrors('caseInput')->assertSet('dossier', '');
    $component->set('caseInput', 'VBG-0002')->call('showCase')->assertHasErrors('caseInput');

    // Ouverture directe par l'adresse (?dossier=) : lue puis journalisée.
    Livewire::withQueryParams(['dossier' => 'VBG-0001'])->test(Followups::class)->assertSee(FOLLOWUPS_COMMENT);
    expect(AuditLog::query()->where('action', 'case_followups.viewed')->count())->toBe(2);
});

it('ajoute une étape au nom de l’admin, chiffre le commentaire et journalise sans le contenu', function (): void {
    $admin = followupsUser('admin');
    $this->actingAs($admin);
    followupsCase('VBG-0001');

    Livewire::test(Followups::class)
        ->set('form.case_code', 'VBG-0001')->set('form.step', 'Appui juridique')->set('form.date', '2026-11-01')
        ->set('form.outcome', 'planifie')->set('form.next_on', '2026-11-20')->set('form.comment', FOLLOWUPS_COMMENT)
        ->call('add')->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: __('admin_data.followups.toasts.added', ['code' => 'VBG-0001']))
        ->assertSet('dossier', 'VBG-0001');

    $step = CaseFollowup::query()->sole();
    expect($step->by_user_id)->toBe($admin->id)->and($step->comment)->toBe(FOLLOWUPS_COMMENT)->and($step->outcome)->toBe(FollowupOutcome::Planned);
    expect(DB::table('case_followups')->value('comment'))->not->toContain('confidentiel');

    $audit = AuditLog::query()->where('action', 'case_followups.created')->sole();
    expect($audit->detail)->toContain('VBG-0001')->not->toContain('confidentiel');
});

it('valide l’étape : champs obligatoires, date future, prochaine date antérieure, dossier inconnu, code mal formé', function (): void {
    $this->actingAs(followupsUser('admin'));
    followupsCase('VBG-0001');

    $component = Livewire::test(Followups::class)
        ->set('form.case_code', 'VBG-0001')->set('form.date', '2026-12-31')->set('form.next_on', '2026-10-01')
        ->call('add')->assertHasErrors(['form.date', 'form.next_on']);

    $component->set('form.date', '')->set('form.step', 'Inconnue')->call('add')->assertHasErrors(['form.date', 'form.step']);
    $component->set('form.case_code', 'VBG-0404')->call('add')->assertHasErrors('form.case_code');
    // Règle `case_code` active : la forme tolérante « vbg 1 » est refusée (strict) ; désactivée, elle est relue.
    $component->set('form.case_code', 'vbg 1')->set('form.step', 'Accueil')->set('form.date', '2026-11-01')->set('form.next_on', '')->call('add')->assertHasErrors('form.case_code');
    CaseFixtures::disable('case_code');
    $component->call('add')->assertHasNoErrors();
    expect(CaseFollowup::query()->count())->toBe(1);
});

it('rejoue l’ajout sans doublon (clé d’idempotence) et refuse la même clé pour une autre saisie', function (): void {
    $this->actingAs(followupsUser('admin'));
    followupsCase('VBG-0001');

    $component = Livewire::test(Followups::class)
        ->set('form.case_code', 'VBG-0001')->set('form.date', '2026-11-01');
    $uuid = $component->get('clientUuid');
    $component->call('add');
    expect(CaseFollowup::query()->count())->toBe(1)->and($component->get('clientUuid'))->not->toBe($uuid);

    // Renvoi de la même saisie avec la même clé (réponse perdue) : l'étape d'origine.
    $component->set('clientUuid', $uuid)->set('form.case_code', 'VBG-0001')->set('form.date', '2026-11-01')->set('form.step', 'Accueil')->set('form.outcome', 'realise')->call('add')
        ->assertDispatched('toast', type: 'info', message: __('admin_data.followups.toasts.replayed', ['code' => 'VBG-0001']));
    expect(CaseFollowup::query()->count())->toBe(1);

    // Même clé, autre saisie : refusée.
    $component->set('clientUuid', $uuid)->set('form.step', 'Écoute')->call('add')
        ->assertDispatched('toast', type: 'error', message: __('admin_data.followups.errors.uuid'));
    expect(CaseFollowup::query()->count())->toBe(1);
});

it('marque une étape réalisée et recalcule les KPI', function (): void {
    $admin = followupsUser('admin');
    $this->actingAs($admin);
    $case = followupsCase('VBG-0001');
    $step = followupsStep($case, ['outcome' => FollowupOutcome::Planned, 'next_on' => '2026-10-20', 'date' => '2026-10-10']);
    Cache::forget(Followups::STATS_CACHE_KEY);

    $component = Livewire::test(Followups::class)->set('caseInput', 'VBG-0001')->call('showCase');
    expect($component->instance()->stats())->toMatchArray(['percent' => 0, 'overdue' => 1]);

    $component->call('markDone', $step->id)->assertDispatched('toast', type: 'success', message: __('admin_data.followups.toasts.done'));
    expect($step->fresh()->outcome)->toBe(FollowupOutcome::Done);
    unset($component->instance()->stats);
    expect($component->instance()->stats())->toMatchArray(['percent' => 100, 'overdue' => 0]);
    expect(AuditLog::query()->where('action', 'case_followups.updated')->sole()->detail)->toContain('outcome')->not->toContain('confidentiel');

    // Étape d'un dossier archivé ou inexistante : refusée sans effet.
    $archived = followupsStep(followupsCase('VBG-0002', '2026-09-01', ['status' => CaseStatus::Archived, 'archived_at' => now()]), ['outcome' => FollowupOutcome::Planned]);
    $component->call('markDone', $archived->id)->assertDispatched('toast', type: 'error');
    $component->call('markDone', 99999)->assertDispatched('toast', type: 'error');
    expect($archived->fresh()->outcome)->toBe(FollowupOutcome::Planned);
});

it('exporte en CSV les étapes filtrées sans commentaire, par lien signé, et journalise l’export', function (): void {
    $admin = followupsUser('admin');
    $this->actingAs($admin);
    $case = followupsCase('VBG-0001');
    followupsStep($case, ['step' => 'Accueil']);
    followupsStep($case, ['step' => 'Écoute', 'outcome' => FollowupOutcome::Planned]);

    $url = Livewire::withQueryParams(['res' => 'realise'])->test(Followups::class)->call('export')->effects['redirect'] ?? null;
    expect($url)->toBeString()->toContain('/admin/export?');

    $csv = $this->get($url)->assertOk()->assertDownload(CsvExport::filename('suivi'))->streamedContent();

    expect($csv)->toStartWith("\u{FEFF}Dossier;Étape;Date;Par;Résultat;\"Prochaine échéance\"\n")
        ->and($csv)->toContain('VBG-0001;Accueil')
        ->and($csv)->not->toContain('Écoute')
        ->and($csv)->not->toContain('confidentiel');
    expect(AuditLog::query()->where('action', 'export.csv')->sole()->detail)->toBe('Export CSV suivi, 1 ligne');
});
