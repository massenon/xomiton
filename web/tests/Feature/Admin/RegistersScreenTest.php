<?php

declare(strict_types=1);

use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Livewire\Admin\Registers\Index as Registers;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\Population;
use App\Models\User;
use App\Models\ViolenceType;
use App\Models\Zone;
use App\Support\CsvExport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P11-01 : Registres numériques /admin/registres (Livewire, WithDataTable) — accès par permission (`cases.view_all`) et
 * par périmètre (CaseRecordScope), liste sans notes ni recherche sur le récit chiffré (ADR-33), création idempotente
 * (CreateCase, code RG-06, avertissements), modification dans la même modale (notes jamais pré-remplies), changement
 * de statut, archivage logique (sort de la liste et des KPI, restaurable), détail journalisé, export CSV scopé sans notes.
 */

const REGISTERS_RECIT = 'Le récit confidentiel de la voisine qui rôde la nuit';

beforeEach(function (): void {
    CaseFixtures::seed();
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function registersUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

/** @param  array<string, mixed>  $overrides */
function registersCase(array $overrides = []): CaseRecord
{
    $zone = CaseFixtures::zone();

    return CaseRecord::factory()->create(array_replace([
        'kind' => CaseKind::Vbg,
        'zone_id' => $zone->id,
        'commune_id' => CaseFixtures::commune($zone)->id,
        'population_id' => Population::query()->orderBy('position')->value('id'),
        'violence_type_id' => ViolenceType::query()->orderBy('position')->value('id'),
        'notes' => REGISTERS_RECIT,
    ], $overrides));
}

/** @return list<string> messages des toasts d'un type (succès, info, avertissement, erreur) */
function registersToasts(mixed $component, string $type): array
{
    return collect($component->effects['dispatches'] ?? [])
        ->filter(fn (array $event): bool => $event['name'] === 'toast' && ($event['params']['type'] ?? '') === $type)
        ->map(fn (array $event): string => (string) $event['params']['message'])
        ->values()->all();
}

/** @return array<string, mixed> */
function registersForm(array $overrides = []): array
{
    $payload = CaseFixtures::payload();

    return array_replace([
        'kind' => 'VBG',
        'occurred_on' => '2026-10-30',
        'zone_id' => (string) $payload['zone_id'],
        'commune_id' => (string) $payload['commune_id'],
        'population_id' => (string) $payload['population_id'],
        'sex' => 'femme',
        'age_range' => '18-24',
        'violence_type_id' => (string) $payload['violence_type_id'],
        'referral' => 'Psychologue',
        'notes' => 'Accueil réalisé, orientation vers une psychologue.',
        'status' => 'ouvert',
    ], $overrides);
}

it('sert les registres à l’admin et au superadmin, les refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(registersUser($role))->get(route('admin.registres'))
            ->assertOk()
            ->assertSeeLivewire(Registers::class)
            ->assertSee('Registres numériques')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="registres"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(registersUser($role))->get(route('admin.registres'))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('admin.registres'))->assertRedirect();
});

it('refuse le composant à un compte d’administration sans `cases.view_all`, même monté directement', function (): void {
    $user = registersUser('moderateur');
    $user->givePermissionTo('cases.create');

    Livewire::actingAs($user)->test(Registers::class)->assertForbidden();
});

it('liste les dossiers non archivés, sans jamais montrer ni chercher les notes', function (): void {
    $this->actingAs(registersUser('admin'));
    $open = registersCase(['code' => 'VBG-0001']);
    registersCase(['code' => 'VBG-0002', 'status' => CaseStatus::Closed]);
    registersCase(['code' => 'VDH-0001', 'kind' => CaseKind::Vdh]);
    registersCase(['code' => 'VBG-0003'])->update(['status' => CaseStatus::Archived, 'archived_at' => now()]);

    Livewire::test(Registers::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 3)
        ->assertSee(['VBG-0001', 'VBG-0002', 'VDH-0001'])
        ->assertDontSee('VBG-0003')
        ->assertDontSee(REGISTERS_RECIT)
        // ADR-33 : un mot du récit chiffré ne filtre jamais la liste.
        ->set('search', 'voisine')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', 'VDH')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1)
        ->set('search', '')
        ->set('kind', 'VBG')
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->set('kind', '')
        ->call('setStatus', 'archive')
        ->assertSee('VBG-0003')
        ->assertDontSee('VBG-0001');

    expect($open->fresh()->notes)->toBe(REGISTERS_RECIT);
});

it('cherche sur la commune, le département et la pair·e (colonnes en clair)', function (): void {
    $this->actingAs(registersUser('admin'));
    $peer = User::factory()->create(['pseudo' => 'Awa-Pair']);
    $other = User::factory()->create(['pseudo' => 'Koffi-Pair']);
    $zone = Zone::query()->where('name', 'Zou')->firstOrFail();
    $commune = Commune::factory()->for($zone)->create(['name' => 'Abomey-Test']);
    registersCase(['code' => 'VBG-0010', 'collected_by' => $peer->id, 'zone_id' => $zone->id, 'commune_id' => $commune->id]);
    registersCase(['code' => 'VBG-0011', 'collected_by' => $other->id]);

    Livewire::test(Registers::class)
        ->set('search', 'Awa-Pair')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0010'])
        ->set('search', 'Abomey-Test')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0010'])
        ->set('search', 'Zou')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0010'])
        ->set('search', 'Koffi')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0011']);
});

it('trie sur des colonnes en clair et ignore un tri ou un filtre mal formé', function (): void {
    $this->actingAs(registersUser('admin'));
    registersCase(['code' => 'VBG-0001', 'occurred_on' => '2026-10-01']);
    registersCase(['code' => 'VBG-0002', 'occurred_on' => '2026-10-20']);

    Livewire::test(Registers::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0002', 'VBG-0001'])
        ->call('sortBy', 'code')
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0001', 'VBG-0002'])
        ->call('sortBy', 'notes')
        ->set('zone', 'abc')->set('status', 'inconnu')->set('from', 'pas-une-date')
        ->assertOk();

    Livewire::withQueryParams(['du' => '2026-10-10', 'statut' => ''])->test(Registers::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('code')->all() === ['VBG-0002']);

    Livewire::withQueryParams(['du' => '2026-10-30', 'au' => '2026-10-01'])->test(Registers::class)
        ->assertSee('Période invalide');
});

it('compte les KPI hors dossiers archivés', function (): void {
    $this->actingAs(registersUser('admin'));
    registersCase(['status' => CaseStatus::Open]);
    registersCase(['status' => CaseStatus::InProgress]);
    registersCase()->update(['status' => CaseStatus::Closed, 'closed_at' => now()]);
    registersCase()->update(['status' => CaseStatus::Archived, 'archived_at' => now()]);

    $component = Livewire::test(Registers::class);
    $counts = $component->instance()->counts();

    expect($counts)->toMatchArray(['ouvert' => 1, 'en_cours' => 1, 'clos' => 1, 'archive' => 1]);
    $component->assertSee(['Dossiers actifs', 'Ouverts', 'En cours', 'Clos']);
});

it('crée un dossier : code attribué par le serveur, statut ouvert, collecté au nom de l’admin, audit sans notes', function (): void {
    $admin = registersUser('admin');
    $this->actingAs($admin);

    Livewire::test(Registers::class)
        ->call('openCreate')
        ->set('form', registersForm())
        ->call('save')
        ->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Dossier VBG-0001 enregistré.')
        ->assertDispatched('xm-modal', id: 'xmCaseForm', action: 'hide');

    $case = CaseRecord::query()->sole();
    expect($case->code)->toBe('VBG-0001')
        ->and($case->status)->toBe(CaseStatus::Open)
        ->and($case->collected_by)->toBe($admin->id)
        ->and($case->notes)->toBe('Accueil réalisé, orientation vers une psychologue.');

    $audit = AuditLog::query()->where('action', 'cases.created')->sole();
    expect($audit->detail)->toContain('VBG-0001')->not->toContain('psychologue');
    // Le récit est chiffré au repos.
    expect(DB::table('cases')->value('notes'))->not->toContain('Accueil réalisé');
});

it('applique les règles du catalogue : champs obligatoires, date future, commune hors département, notes courtes', function (): void {
    $this->actingAs(registersUser('admin'));
    $other = Zone::factory()->create();
    $strange = Commune::factory()->for($other)->create();

    $component = Livewire::test(Registers::class)->call('openCreate')
        ->set('form', registersForm(['occurred_on' => '2026-12-31', 'commune_id' => (string) $strange->id, 'notes' => 'trop court']))
        ->call('save')
        ->assertHasErrors(['form.occurred_on', 'form.commune_id', 'form.notes']);

    expect(CaseRecord::query()->count())->toBe(0);

    $component->set('form', registersForm(['zone_id' => '', 'population_id' => '', 'sex' => '', 'age_range' => '', 'violence_type_id' => '']))
        ->call('save')
        ->assertHasErrors(['form.zone_id', 'form.population_id', 'form.sex', 'form.age_range', 'form.violence_type_id']);

    // Règle `date_not_future` désactivée dans l'administration : la date future est acceptée.
    CaseFixtures::disable('date_not_future');
    $component->set('form', registersForm(['occurred_on' => '2026-12-31']))->call('save')->assertHasNoErrors();
    expect(CaseRecord::query()->count())->toBe(1);
});

it('rejoue une saisie sans doublon (clé d’idempotence) et refuse la même clé pour une autre saisie', function (): void {
    $this->actingAs(registersUser('admin'));

    $component = Livewire::test(Registers::class)->call('openCreate')->set('form', registersForm())->call('save');
    expect(CaseRecord::query()->count())->toBe(1);

    // Renvoi de la même saisie (réponse perdue, double clic) : le dossier d'origine, aucun doublon.
    $component->call('save')
        ->assertDispatched('toast', type: 'info', message: __('admin_data.registers.toasts.replayed', ['code' => 'VBG-0001']));
    expect(CaseRecord::query()->count())->toBe(1);

    // Même clé, autre saisie : refusée, rien n'est créé.
    $component->set('form.sex', 'homme')->call('save')
        ->assertDispatched('toast', type: 'error', message: __('admin_data.registers.errors.uuid'));
    expect(CaseRecord::query()->count())->toBe(1);

    // Rouvrir le formulaire donne une nouvelle clé : nouveau dossier.
    $component->call('openCreate')->set('form', registersForm(['sex' => 'homme']))->call('save');
    expect(CaseRecord::query()->count())->toBe(2)->and(CaseRecord::query()->orderByDesc('id')->value('code'))->toBe('VBG-0002');
});

it('avertit d’un doublon suspect et d’une date ancienne sans bloquer l’enregistrement', function (): void {
    $this->actingAs(registersUser('admin'));
    registersCase(['code' => 'VBG-0001', 'occurred_on' => '2026-10-30', 'sex' => 'femme', 'age_range' => '18-24']);
    $form = registersForm(['occurred_on' => '2026-10-30']);

    $component = Livewire::test(Registers::class)->call('openCreate')->set('form', $form)->call('save');
    expect(CaseRecord::query()->count())->toBe(2);

    $warnings = registersToasts($component, 'warning');
    expect($warnings)->toHaveCount(1)->and($warnings[0])->toContain('VBG-0001');

    $old = Livewire::test(Registers::class)->call('openCreate')->set('form', registersForm(['occurred_on' => '2015-01-01']))->call('save');
    expect(registersToasts($old, 'warning'))->toHaveCount(1)->and(registersToasts($old, 'warning')[0])->toContain('5 ans');
});

it('modifie un dossier dans la même modale : nature immuable, notes jamais pré-remplies, vide = inchangées', function (): void {
    $this->actingAs(registersUser('admin'));
    // Sexe fixé : une valeur tirée au hasard égale à « homme » rendait la première sauvegarde sans effet (test instable).
    $case = registersCase(['code' => 'VBG-0001', 'sex' => 'femme']);

    $component = Livewire::test(Registers::class)->call('openEdit', $case->id);

    // Aucune donnée déchiffrée dans l'état de la page ni dans le HTML.
    expect($component->get('form.notes'))->toBe('')
        ->and($component->get('form.kind'))->toBe('VBG');
    $component->assertDontSee(REGISTERS_RECIT);
    expect(json_encode($component->snapshot ?? []))->not->toContain('voisine');

    $component->set('form.sex', 'homme')->set('form.kind', 'VDH')
        ->call('save')->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: 'Dossier VBG-0001 mis à jour.');

    $fresh = $case->fresh();
    expect($fresh->sex->value)->toBe('homme')
        ->and($fresh->kind)->toBe(CaseKind::Vbg)
        ->and($fresh->code)->toBe('VBG-0001')
        ->and($fresh->notes)->toBe(REGISTERS_RECIT);

    // Remplacer, puis effacer les notes.
    $component->call('openEdit', $case->id)->set('form.notes', 'Nouvelle note pseudonymisée, suivi à 30 jours.')->call('save')->assertHasNoErrors();
    expect($case->fresh()->notes)->toBe('Nouvelle note pseudonymisée, suivi à 30 jours.');

    $component->call('openEdit', $case->id)->set('clearNotes', true)->call('save')->assertHasNoErrors();
    expect($case->fresh()->notes)->toBeNull();

    $audit = AuditLog::query()->where('action', 'cases.updated')->get();
    expect($audit)->toHaveCount(3)->and($audit->pluck('detail')->implode(' '))->not->toContain('Nouvelle note');

    // Commune d'un autre département : refusée (règle commune_in_zone).
    $strange = Commune::factory()->for(Zone::factory()->create())->create();
    $component->call('openEdit', $case->id)->set('form.commune_id', (string) $strange->id)->call('save')->assertHasErrors(['form.commune_id']);
});

it('change le statut ouvert / en cours / clos par les transitions de CaseStatus et refuse le reste', function (): void {
    $this->actingAs(registersUser('admin'));
    $case = registersCase(['code' => 'VBG-0001']);

    $component = Livewire::test(Registers::class)->call('openDetail', $case->id);
    $component->set('detailStatus', 'en_cours')->call('changeStatus')->assertHasNoErrors();
    expect($case->fresh()->status)->toBe(CaseStatus::InProgress);

    $component->set('detailStatus', 'clos')->call('changeStatus');
    $closed = $case->fresh();
    expect($closed->status)->toBe(CaseStatus::Closed)->and($closed->closed_at)->not->toBeNull();

    // Clos → ouvert : transition interdite ; archive : bouton dédié.
    $component->set('detailStatus', 'ouvert')->call('changeStatus')->assertHasErrors('detailStatus');
    $component->set('detailStatus', 'archive')->call('changeStatus')->assertHasErrors('detailStatus');
    expect($case->fresh()->status)->toBe(CaseStatus::Closed);
});

it('archive un dossier (hors liste et KPI) et le restaure ; un dossier anonymisé ne se restaure pas', function (): void {
    $this->actingAs(registersUser('admin'));
    $case = registersCase(['code' => 'VBG-0001']);

    $component = Livewire::test(Registers::class)->call('archive', $case->id)
        ->assertDispatched('toast', type: 'success', message: 'Dossier VBG-0001 archivé.')
        ->assertDontSee('VBG-0001');

    $archived = $case->fresh();
    expect($archived->status)->toBe(CaseStatus::Archived)->and($archived->archived_at)->not->toBeNull()->and($archived->closed_at)->not->toBeNull();
    expect($component->instance()->counts())->toMatchArray(['ouvert' => 0, 'archive' => 1]);

    // Archivé = lecture seule : ni modification, ni nouvelle archive.
    $component->call('openEdit', $case->id)->assertDispatched('toast', type: 'error', message: __('admin_data.registers.errors.locked'));
    $component->call('archive', $case->id)->assertDispatched('toast', type: 'error');

    $component->call('setStatus', 'archive')->assertSee('VBG-0001')
        ->call('restore', $case->id)->assertDispatched('toast', type: 'success', message: 'Dossier VBG-0001 restauré.');
    $restored = $case->fresh();
    expect($restored->status)->toBe(CaseStatus::Closed)->and($restored->archived_at)->toBeNull();

    $component->call('restore', $case->id)->assertDispatched('toast', type: 'error');

    $anonymous = registersCase(['code' => 'VBG-0002']);
    $anonymous->update(['status' => CaseStatus::Archived, 'archived_at' => now(), 'anonymized_at' => now(), 'notes' => null]);
    $component->call('restore', $anonymous->id)->assertDispatched('toast', type: 'error');
    expect($anonymous->fresh()->status)->toBe(CaseStatus::Archived);

    expect(AuditLog::query()->whereIn('action', ['cases.archived', 'cases.restored'])->pluck('action')->all())
        ->toBe(['cases.archived', 'cases.restored']);
});

it('lit les notes dans le détail seulement, journalise la lecture sans le contenu et vide l’état à la fermeture', function (): void {
    $this->actingAs(registersUser('admin'));
    $case = registersCase(['code' => 'VBG-0001']);

    $component = Livewire::test(Registers::class)
        ->assertDontSee(REGISTERS_RECIT)
        ->call('openDetail', $case->id)
        ->assertSee(REGISTERS_RECIT)
        ->assertDispatched('xm-modal', id: 'xmCaseDetail', action: 'show');

    $log = AuditLog::query()->where('action', 'cases.viewed')->sole();
    expect($log->detail)->toBe('code=VBG-0001');

    $component->call('closeDetail')->assertDontSee(REGISTERS_RECIT);
});

it('n’ouvre pas le dossier d’une autre pair·e hors périmètre ni un identifiant inexistant', function (): void {
    $this->actingAs(registersUser('admin'));

    Livewire::test(Registers::class)
        ->call('openDetail', 99999)
        ->assertDispatched('toast', type: 'error', message: 'Ce dossier est introuvable ou hors de votre périmètre.')
        ->call('archive', 99999)->call('openEdit', 99999);

    expect(AuditLog::query()->where('action', 'cases.viewed')->count())->toBe(0);
});

it('exporte en CSV les filtres actifs par lien signé, sans notes, et journalise l’export', function (): void {
    $admin = registersUser('admin');
    $this->actingAs($admin);
    registersCase(['code' => 'VBG-0001']);
    registersCase(['code' => 'VDH-0001', 'kind' => CaseKind::Vdh]);
    registersCase(['code' => 'VBG-0009'])->update(['status' => CaseStatus::Archived, 'archived_at' => now()]);

    $url = Livewire::withQueryParams(['reg' => 'VBG'])->test(Registers::class)->call('export')->effects['redirect'] ?? null;
    // La signature du lien est aléatoire : elle peut contenir « VBG » par hasard, seuls le chemin et les paramètres comptent.
    expect($url)->toBeString()->toContain('/admin/export?')
        ->and(preg_replace('/([?&])signature=[^&]*/', '$1', $url))->not->toContain('VBG');

    $response = $this->get($url)->assertOk()->assertDownload(CsvExport::filename('registres'));
    $csv = $response->streamedContent();

    expect($csv)->toStartWith("\u{FEFF}Code;Registre;Date;Département;Commune;Population;Sexe;\"Tranche d’âge\";Violence;Statut;Pair·e;Orientation\n")
        ->and($csv)->toContain('VBG-0001')
        ->and($csv)->not->toContain('VDH-0001')
        ->and($csv)->not->toContain('VBG-0009')
        ->and($csv)->not->toContain('voisine')
        ->and($csv)->not->toContain(REGISTERS_RECIT);

    expect(AuditLog::query()->where('action', 'export.csv')->sole()->detail)->toBe('Export CSV registres, 1 ligne');
    $this->actingAs(registersUser('admin'))->get($url)->assertForbidden();
});

it('met à jour un dossier sans toucher aux étapes de suivi existantes', function (): void {
    $this->actingAs(registersUser('admin'));
    $case = registersCase(['code' => 'VBG-0001']);
    CaseFollowup::factory()->create(['case_id' => $case->id]);

    Livewire::test(Registers::class)->call('openEdit', $case->id)->set('form.sex', 'autre')->call('save')->assertHasNoErrors();

    expect($case->followups()->count())->toBe(1)->and(Str::of((string) $case->fresh()->sex->value)->toString())->toBe('autre');
});
