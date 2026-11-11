<?php

declare(strict_types=1);

use App\Application\Cases\CaseFormSheet;
use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\DirectoryRole;
use App\Livewire\Admin\Forms\Index as Forms;
use App\Models\AuditLog;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P11-02 : Fiches VBG / VDH /admin/fiches (Livewire, WithDataTable) — accès `case_forms.manage`, création liée à un dossier
 * existant (référence FR-AAAA-nnnn, consentement obligatoire, services normalisés, orientation vers l'annuaire), récit /
 * auteur·e / signataire chiffrés et absents de la liste, lecture journalisée, suppression journalisée, PDF A4 côté serveur
 * avec pied « Confidentiel » (policy `print`, audit), dossiers archivés hors périmètre.
 */

const FORMS_FACTS = 'Menaces répétées par un voisin depuis une semaine.';

beforeEach(function (): void {
    CaseFixtures::seed();
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function formsUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function formsCase(string $code = 'VBG-0001', array $overrides = []): CaseRecord
{
    return CaseRecord::factory()->create(array_replace(['code' => $code, 'kind' => str_starts_with($code, 'VDH') ? CaseKind::Vdh : CaseKind::Vbg], $overrides));
}

/** @return array<string, mixed> */
function formsPayload(string $code = 'VBG-0001', array $overrides = []): array
{
    return array_replace([
        'case_code' => $code,
        'pseudo' => 'Awa',
        'facts_date' => '2026-10-20',
        'facts_place' => 'Cotonou, marché',
        'facts_author' => 'Voisin du quartier',
        'facts_description' => FORMS_FACTS,
        'services' => ['ecoute' => true, 'juridique' => true, 'medical' => false, 'psy' => false, 'hebergement' => false, 'dignite' => false, 'securite' => false],
        'services_note' => 'Deux séances réalisées',
        'referral_contact_id' => '',
        'referral_date' => '2026-11-01',
        'referral_reason' => 'Appui juridique urgent',
        'referral_urgency' => 'haute',
        'consent_given' => true,
        'consent_mode' => 'ecrit',
        'consent_sharing' => true,
        'consent_signatory' => 'A.K.',
    ], $overrides);
}

it('sert les fiches à l’admin et au superadmin, les refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(formsUser($role))->get(route('admin.fiches'))
            ->assertOk()->assertSeeLivewire(Forms::class)->assertSee('Fiches VBG / VDH')->getContent();
        expect($html)->toMatch('/data-xm-nav="fiches"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(formsUser($role))->get(route('admin.fiches'))->assertForbidden();
    }

    $user = formsUser('moderateur');
    $user->givePermissionTo('case_forms.view_own');
    Livewire::actingAs($user)->test(Forms::class)->assertForbidden();
});

it('crée une fiche liée à un dossier : référence FR-AAAA-nnnn, identification pseudonymisée, services, orientation, audit sans contenu', function (): void {
    $admin = formsUser('admin');
    $this->actingAs($admin);
    $case = formsCase();
    $contact = DirectoryContact::factory()->create(['name' => 'Maître Dossou', 'role' => DirectoryRole::Juriste, 'is_active' => true]);

    Livewire::test(Forms::class)->call('openCreate')
        ->set('form', formsPayload('vbg 1', ['referral_contact_id' => (string) $contact->id]))
        ->call('save')->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: __('admin_data.forms.toasts.created', ['reference' => 'FR-2026-0001']))
        ->assertDispatched('xm-modal', id: 'xmFormEditor', action: 'hide')
        ->assertSet('detailId', CaseForm::query()->value('id'));

    $form = CaseForm::query()->sole();
    expect($form->reference)->toBe('FR-2026-0001')
        ->and($form->case_id)->toBe($case->id)
        ->and($form->created_by)->toBe($admin->id)
        ->and($form->identification)->toMatchArray(['pseudo' => 'Awa', 'sexe' => $case->sex->value, 'age' => $case->age_range->value])
        ->and($form->services)->toMatchArray(['ecoute' => true, 'juridique' => true, 'medical' => false, 'autre' => false])
        ->and($form->referral_contact_id)->toBe($contact->id)
        ->and($form->referral_label)->toContain('Maître Dossou')
        ->and($form->referral_urgency)->toBe('haute')
        ->and($form->consent_mode)->toBe('ecrit')
        ->and($form->facts_description)->toBe(FORMS_FACTS);

    // Récit, auteur·e des faits, signataire : chiffrés au repos.
    $raw = DB::table('case_forms')->first();
    expect($raw->facts_description)->not->toContain('voisin')->and($raw->facts_author)->not->toContain('Voisin')->and($raw->consent_signatory)->not->toContain('A.K.');

    $audit = AuditLog::query()->where('action', 'case_forms.created')->sole();
    expect($audit->detail)->toBe('fiche=FR-2026-0001 ; dossier=VBG-0001');
});

it('numérote les fiches par année et refuse un doublon de référence', function (): void {
    $this->actingAs(formsUser('admin'));
    $case = formsCase();

    $component = Livewire::test(Forms::class);
    $component->call('openCreate')->set('form', formsPayload())->call('save');
    $component->call('openCreate')->set('form', formsPayload())->call('save');
    $this->travelTo(now()->setDate(2027, 1, 5));
    $component->call('openCreate')->set('form', formsPayload('VBG-0001', ['facts_date' => '2026-12-31']))->call('save');

    expect(CaseForm::query()->orderBy('id')->pluck('reference')->all())->toBe(['FR-2026-0001', 'FR-2026-0002', 'FR-2027-0001'])
        ->and($case->forms()->count())->toBe(3);
});

it('exige le consentement, les faits (20 caractères), un dossier actif et refuse les valeurs hors listes', function (): void {
    $this->actingAs(formsUser('admin'));
    formsCase();
    formsCase('VBG-0002', ['status' => CaseStatus::Archived, 'archived_at' => now()]);

    $component = Livewire::test(Forms::class)->call('openCreate')
        ->set('form', formsPayload('VBG-0001', ['consent_given' => false, 'facts_description' => 'court', 'facts_date' => '2026-12-31', 'referral_urgency' => 'extrême', 'consent_mode' => 'tacite']))
        ->call('save')->assertHasErrors(['form.consent_given', 'form.facts_description', 'form.facts_date', 'form.referral_urgency', 'form.consent_mode']);

    $component->set('form', formsPayload('VBG-0404'))->call('save')->assertHasErrors('form.case_code');
    $component->set('form', formsPayload('VBG-0002'))->call('save')->assertHasErrors('form.case_code');
    $component->set('form', formsPayload('VBG-0001', ['referral_contact_id' => '99999']))->call('save')->assertHasErrors('form.referral_contact_id');
    expect(CaseForm::query()->count())->toBe(0);

    // Un contact inactif de l'annuaire ne reçoit pas d'orientation.
    $inactive = DirectoryContact::factory()->create(['is_active' => false]);
    $component->set('form', formsPayload('VBG-0001', ['referral_contact_id' => (string) $inactive->id]))->call('save')->assertHasErrors('form.referral_contact_id');

    // Règle `min_len_20` désactivée dans l'administration : un récit court est accepté.
    CaseFixtures::disable('min_len_20');
    $component->set('form', formsPayload('VBG-0001', ['facts_description' => 'court']))->call('save')->assertHasNoErrors();
    expect(CaseForm::query()->count())->toBe(1);
});

it('ne garde que les services du catalogue, tous à faux par défaut', function (): void {
    $this->actingAs(formsUser('admin'));
    formsCase();

    Livewire::test(Forms::class)->call('openCreate')
        ->set('form', formsPayload('VBG-0001', ['services' => ['ecoute' => true, 'piratage' => true]]))
        ->call('save')->assertHasNoErrors();

    $services = CaseForm::query()->sole()->services;
    expect($services)->not->toHaveKey('piratage')->and($services['ecoute'])->toBeTrue()->and($services['securite'])->toBeFalse()->and(array_keys($services))->toBe(CaseForm::SERVICES);
});

it('liste les fiches sans récit, cherche sur la référence et le dossier mais jamais sur le récit', function (): void {
    $this->actingAs(formsUser('admin'));
    $a = formsCase('VBG-0001');
    $b = formsCase('VDH-0001');
    CaseForm::factory()->create(['case_id' => $a->id, 'reference' => 'FR-2026-0001', 'facts_description' => FORMS_FACTS, 'facts_author' => 'Voisin du quartier']);
    CaseForm::factory()->create(['case_id' => $b->id, 'reference' => 'FR-2026-0002']);
    CaseForm::factory()->create(['case_id' => formsCase('VBG-0009', ['status' => CaseStatus::Archived, 'archived_at' => now()])->id, 'reference' => 'FR-2026-0003']);

    Livewire::test(Forms::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->total() === 2)
        ->assertSee(['FR-2026-0001', 'FR-2026-0002'])
        ->assertDontSee(['FR-2026-0003', FORMS_FACTS, 'Voisin du quartier'])
        ->set('search', 'voisin')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', 'menaces')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 0)
        ->set('search', 'FR-2026-0002')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('reference')->all() === ['FR-2026-0002'])
        ->set('search', 'VBG-0001')->assertViewHas('rows', fn ($rows): bool => $rows->pluck('reference')->all() === ['FR-2026-0001'])
        ->set('search', '')->set('kind', 'VDH')->assertViewHas('rows', fn ($rows): bool => $rows->total() === 1);

    Livewire::withQueryParams(['dossier' => 'VBG-0001'])->test(Forms::class)
        ->assertViewHas('rows', fn ($rows): bool => $rows->pluck('reference')->all() === ['FR-2026-0001'])
        ->call('openCreate')->assertSet('form.case_code', 'VBG-0001');
});

it('lit la fiche complète dans le détail seulement, journalise la lecture sans contenu et vide l’état à la fermeture', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = CaseForm::factory()->create([
        'case_id' => formsCase()->id,
        'facts_description' => FORMS_FACTS,
        'facts_author' => 'Voisin du quartier',
        'consent_signatory' => 'A.K.',
    ]);

    $component = Livewire::test(Forms::class)->assertDontSee(FORMS_FACTS)
        ->call('openDetail', $form->id)
        ->assertSee([FORMS_FACTS, 'Voisin du quartier', 'A.K.'])
        ->assertDispatched('xm-modal', id: 'xmFormDetail', action: 'show');

    expect(AuditLog::query()->where('action', 'case_forms.viewed')->sole()->detail)->toBe('fiche='.$form->reference);

    $component->call('closeDetail')->assertDontSee(FORMS_FACTS)->assertSet('detailId', 0);

    $component->call('openDetail', 99999)->assertDispatched('toast', type: 'error');
});

it('supprime une fiche avec une ligne d’audit, le dossier reste', function (): void {
    $this->actingAs(formsUser('admin'));
    $case = formsCase();
    $form = CaseForm::factory()->create(['case_id' => $case->id, 'reference' => 'FR-2026-0001']);

    Livewire::test(Forms::class)->call('delete', $form->id)
        ->assertDispatched('toast', type: 'success', message: __('admin_data.forms.toasts.deleted', ['reference' => 'FR-2026-0001']));

    expect(CaseForm::query()->count())->toBe(0)->and(CaseRecord::query()->count())->toBe(1);
    expect(AuditLog::query()->where('action', 'case_forms.deleted')->sole()->detail)->toBe('fiche=FR-2026-0001 ; dossier=VBG-0001');
});

it('génère le PDF A4 côté serveur avec le pied « Confidentiel », journalise et refuse les autres comptes', function (): void {
    $form = CaseForm::factory()->create(['case_id' => formsCase()->id, 'reference' => 'FR-2026-0001', 'facts_description' => FORMS_FACTS]);

    $response = $this->actingAs(formsUser('admin'))->get(route('admin.fiches.pdf', $form))->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('Content-Disposition'))->toContain('FR-2026-0001.pdf')
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex')
        ->and(substr((string) $response->getContent(), 0, 4))->toBe('%PDF');

    expect(AuditLog::query()->where('action', 'case_forms.printed')->sole()->detail)->toBe('fiche=FR-2026-0001 ; dossier=VBG-0001');

    // Le gabarit porte le pied « Confidentiel » et les cinq sections.
    $html = view('pdf.case-form', ['sheet' => app(CaseFormSheet::class)($form->fresh()), 'format' => App\Support\LocaleFormatter::make(), 'siteName' => 'Xomiton'])->render();
    expect($html)->toContain('<footer>')->toContain('Confidentiel')->toContain('1. Identification pseudonymisée')->toContain('5. Consentement')->toContain(FORMS_FACTS);

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(formsUser($role))->get(route('admin.fiches.pdf', $form))->assertForbidden();
    }

    $this->actingAs(formsUser('admin'))->get('/admin/fiches/99999/pdf')->assertNotFound();
});

/*
 * Modification d'une fiche (UpdateCaseForm) : même modale, mêmes règles, colonnes chiffrées jamais pré-remplies
 * (vide = inchangé), dossier et référence immuables, journal sans contenu.
 */
const FORMS_AUTHOR = 'Voisin du quartier';
const FORMS_NOTE = 'Deux séances réalisées';
const FORMS_SIGNATORY = 'A.K.';

function formsStored(array $overrides = []): CaseForm
{
    return CaseForm::factory()->create(array_replace([
        'case_id' => isset($overrides['case_id']) ? $overrides['case_id'] : formsCase()->id,
        'reference' => 'FR-2026-0001',
        'identification' => ['pseudo' => 'Awa', 'sexe' => 'femme', 'age' => '18-24', 'population' => 'Femmes', 'zone' => 'Littoral', 'commune' => 'Cotonou'],
        'facts_date' => '2026-10-20',
        'facts_place' => 'Cotonou, marché',
        'facts_author' => FORMS_AUTHOR,
        'facts_description' => FORMS_FACTS,
        'services' => CaseForm::normalizeServices(['ecoute' => true]),
        'services_note' => FORMS_NOTE,
        'referral_contact_id' => null,
        'referral_label' => null,
        'referral_date' => '2026-11-01',
        'referral_reason' => 'Appui juridique',
        'referral_urgency' => 'normale',
        'consent_given' => true,
        'consent_mode' => 'oral',
        'consent_sharing' => false,
        'consent_signatory' => FORMS_SIGNATORY,
    ], $overrides));
}

it('ouvre la fiche dans la même modale sans jamais recopier les colonnes chiffrées, ni dans l’état ni dans le HTML', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();

    $component = Livewire::test(Forms::class)->call('openEdit', $form->id)
        ->assertSet('editId', $form->id)
        ->assertSet('form.case_code', 'VBG-0001')
        ->assertSet('form.pseudo', 'Awa')
        ->assertSet('form.facts_place', 'Cotonou, marché')
        ->assertSet('form.facts_date', '2026-10-20')
        ->assertSet('form.services.ecoute', true)
        ->assertSet('form.consent_given', true)
        ->assertSet('form.consent_mode', 'oral')
        ->assertSet('form.facts_description', '')
        ->assertSet('form.facts_author', '')
        ->assertSet('form.services_note', '')
        ->assertSet('form.consent_signatory', '')
        ->assertDispatched('xm-modal', id: 'xmFormEditor', action: 'show')
        ->assertSee('Modifier la fiche FR-2026-0001')
        ->assertDontSee([FORMS_FACTS, FORMS_AUTHOR, FORMS_NOTE, FORMS_SIGNATORY])
        ->assertSeeHtml('id="xmFormClearAuthor"');

    expect(json_encode($component->snapshot ?? []))->not->toContain('voisin')->not->toContain('Voisin')->not->toContain('Menaces')->not->toContain('séances');
    expect(AuditLog::query()->where('action', 'case_forms.viewed')->exists())->toBeFalse();

    // « Nouvelle fiche » repart d'un formulaire vierge.
    $component->call('openCreate')->assertSet('editId', 0)->assertSet('form.pseudo', '')->assertSet('form.case_code', '');
});

it('modifie les champs en clair : dossier, référence et auteur·e de la fiche inchangés, colonnes chiffrées conservées', function (): void {
    $admin = formsUser('admin');
    $this->actingAs($admin);
    $form = formsStored();
    $other = formsCase('VBG-0002');

    Livewire::test(Forms::class)->call('openEdit', $form->id)
        ->set('form.pseudo', 'Awa K.')
        ->set('form.facts_place', 'Porto-Novo')
        ->set('form.facts_date', '2026-10-25')
        ->set('form.services.psy', true)->set('form.services.ecoute', false)
        ->set('form.referral_reason', 'Suivi psychologique')->set('form.referral_urgency', 'haute')
        ->set('form.consent_mode', 'ecrit')->set('form.consent_sharing', true)
        ->set('form.case_code', 'VBG-0002')
        ->call('save')->assertHasNoErrors()
        ->assertDispatched('toast', type: 'success', message: __('admin_data.forms.toasts.updated', ['reference' => 'FR-2026-0001']))
        ->assertDispatched('xm-modal', id: 'xmFormEditor', action: 'hide')
        ->assertSet('editId', 0);

    $fresh = $form->fresh();
    expect($fresh->reference)->toBe('FR-2026-0001')->and($fresh->case_id)->toBe($form->case_id)->and($fresh->case_id)->not->toBe($other->id)
        ->and($fresh->created_by)->toBe($form->created_by)
        ->and($fresh->identification)->toMatchArray(['pseudo' => 'Awa K.', 'sexe' => 'femme', 'zone' => 'Littoral', 'commune' => 'Cotonou'])
        ->and($fresh->facts_place)->toBe('Porto-Novo')->and($fresh->facts_date->format('Y-m-d'))->toBe('2026-10-25')
        ->and($fresh->services)->toMatchArray(['psy' => true, 'ecoute' => false, 'medical' => false])
        ->and($fresh->referral_reason)->toBe('Suivi psychologique')->and($fresh->referral_urgency)->toBe('haute')
        ->and($fresh->consent_mode)->toBe('ecrit')->and($fresh->consent_sharing)->toBeTrue()
        // Vide = inchangé.
        ->and($fresh->facts_description)->toBe(FORMS_FACTS)->and($fresh->facts_author)->toBe(FORMS_AUTHOR)
        ->and($fresh->services_note)->toBe(FORMS_NOTE)->and($fresh->consent_signatory)->toBe(FORMS_SIGNATORY);

    $audit = AuditLog::query()->where('action', 'case_forms.updated')->sole();
    expect($audit->user_id)->toBe($admin->id)->and($audit->detail)->toStartWith('fiche=FR-2026-0001 ; dossier=VBG-0001 ; champs=')
        ->and($audit->detail)->toContain('pseudo')->toContain('facts_place')->toContain('services')
        ->and($audit->detail)->not->toContain('Porto-Novo')->not->toContain('Awa K.')->not->toContain('Suivi psychologique');
});

it('remplace les colonnes chiffrées quand on les saisit, les efface seulement sur demande, et journalise sans contenu', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();
    $component = Livewire::test(Forms::class);

    $component->call('openEdit', $form->id)
        ->set('form.facts_description', 'Nouveau récit pseudonymisé des faits, mis à jour.')
        ->set('form.facts_author', 'Collègue de travail')->set('form.services_note', 'Trois séances')->set('form.consent_signatory', 'B.D.')
        ->call('save')->assertHasNoErrors();

    $fresh = $form->fresh();
    expect($fresh->facts_description)->toBe('Nouveau récit pseudonymisé des faits, mis à jour.')->and($fresh->facts_author)->toBe('Collègue de travail')
        ->and($fresh->services_note)->toBe('Trois séances')->and($fresh->consent_signatory)->toBe('B.D.');
    $raw = DB::table('case_forms')->first();
    expect($raw->facts_description)->not->toContain('Nouveau récit')->and($raw->facts_author)->not->toContain('Collègue');

    // Effacer : seuls les trois champs facultatifs, jamais le récit.
    $component->call('openEdit', $form->id)->set('clear.facts_author', true)->set('clear.services_note', true)->set('clear.consent_signatory', true)
        ->call('save')->assertHasNoErrors();
    $cleared = $form->fresh();
    expect($cleared->facts_author)->toBeNull()->and($cleared->services_note)->toBeNull()->and($cleared->consent_signatory)->toBeNull()
        ->and($cleared->facts_description)->toBe('Nouveau récit pseudonymisé des faits, mis à jour.');

    // Un nom hors liste dans `clear` est ignoré.
    $component->call('openEdit', $form->id)->set('clear.facts_description', true)->call('save')->assertHasNoErrors();
    expect($form->fresh()->facts_description)->toBe('Nouveau récit pseudonymisé des faits, mis à jour.');

    $details = AuditLog::query()->where('action', 'case_forms.updated')->pluck('detail')->implode(' | ');
    expect($details)->toContain('facts_description')->and($details)->not->toContain('Nouveau récit')->not->toContain('Collègue')->not->toContain('Trois séances')->not->toContain('B.D.');
});

it('ne journalise rien quand la modification ne change rien', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();

    Livewire::test(Forms::class)->call('openEdit', $form->id)->call('save')->assertHasNoErrors()->assertDispatched('toast', type: 'success');

    expect(AuditLog::query()->where('action', 'case_forms.updated')->exists())->toBeFalse()->and($form->fresh()->facts_description)->toBe(FORMS_FACTS);
});

it('applique les règles de la création à la modification : consentement, faits, date, listes, contact actif', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();
    $component = Livewire::test(Forms::class)->call('openEdit', $form->id);

    $component->set('form.consent_given', false)->set('form.facts_description', 'court')->set('form.facts_date', '2026-12-31')
        ->set('form.referral_urgency', 'extrême')->set('form.consent_mode', 'tacite')
        ->call('save')->assertHasErrors(['form.consent_given', 'form.facts_description', 'form.facts_date', 'form.referral_urgency', 'form.consent_mode']);

    $inactive = DirectoryContact::factory()->create(['is_active' => false]);
    $component->call('openEdit', $form->id)->set('form.referral_contact_id', (string) $inactive->id)->call('save')->assertHasErrors('form.referral_contact_id');
    $component->call('openEdit', $form->id)->set('form.referral_contact_id', '99999')->call('save')->assertHasErrors('form.referral_contact_id');
    $component->call('openEdit', $form->id)->set('form.facts_place', str_repeat('x', 121))->call('save')->assertHasErrors('form.facts_place');

    $fresh = $form->fresh();
    expect($fresh->consent_given)->toBeTrue()->and($fresh->facts_description)->toBe(FORMS_FACTS)->and($fresh->referral_contact_id)->toBeNull()
        ->and(AuditLog::query()->where('action', 'case_forms.updated')->exists())->toBeFalse();
});

it('gère l’orientation : nouveau contact actif, contact conservé même désactivé depuis, retrait', function (): void {
    $this->actingAs(formsUser('admin'));
    $first = DirectoryContact::factory()->create(['name' => 'Maître Dossou', 'role' => DirectoryRole::Juriste, 'is_active' => true]);
    $second = DirectoryContact::factory()->create(['name' => 'Docteure Hounkpè', 'role' => DirectoryRole::Juriste, 'is_active' => true]);
    $form = formsStored(['referral_contact_id' => $first->id, 'referral_label' => 'Maître Dossou — Juriste']);
    $component = Livewire::test(Forms::class);

    $first->forceFill(['is_active' => false])->save();
    $component->call('openEdit', $form->id)->assertSet('form.referral_contact_id', (string) $first->id)->set('form.referral_reason', 'Autre motif')->call('save')->assertHasNoErrors();
    expect($form->fresh()->referral_contact_id)->toBe($first->id)->and($form->fresh()->referral_label)->toBe('Maître Dossou — Juriste');

    $component->call('openEdit', $form->id)->set('form.referral_contact_id', (string) $second->id)->call('save')->assertHasNoErrors();
    expect($form->fresh()->referral_contact_id)->toBe($second->id)->and($form->fresh()->referral_label)->toContain('Docteure Hounkpè');

    $component->call('openEdit', $form->id)->set('form.referral_contact_id', '')->call('save')->assertHasNoErrors();
    expect($form->fresh()->referral_contact_id)->toBeNull()->and($form->fresh()->referral_label)->toBeNull();
});

it('refuse de modifier une fiche dont le dossier est archivé ou anonymisé', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();
    $component = Livewire::test(Forms::class)->call('openEdit', $form->id)->set('form.facts_place', 'Ailleurs');

    // Dossier archivé pendant que la modale est ouverte : la fiche sort du périmètre, rien n'est écrit.
    $form->case->forceFill(['status' => CaseStatus::Archived, 'archived_at' => now()])->save();
    $component->call('save')->assertDispatched('toast', type: 'error');
    expect($form->fresh()->facts_place)->toBe('Cotonou, marché')->and(AuditLog::query()->where('action', 'case_forms.updated')->exists())->toBeFalse();
    $component->call('openEdit', $form->id)->assertDispatched('toast', type: 'error', message: __('admin_data.forms.errors.not_found'));

    // Dossier anonymisé mais non archivé : refusé par la fiche elle-même.
    $anonymous = formsStored(['reference' => 'FR-2026-0002', 'case_id' => formsCase('VBG-0003', ['anonymized_at' => now()])->id]);
    $component->call('openEdit', $anonymous->id)->assertDispatched('toast', type: 'error', message: __('admin_data.forms.errors.locked_form'))->assertNotSet('editId', $anonymous->id);
});

it('ne laisse pas modifier editId depuis le navigateur', function (): void {
    $this->actingAs(formsUser('admin'));
    $form = formsStored();
    $other = formsStored(['reference' => 'FR-2026-0002', 'case_id' => formsCase('VBG-0002')->id]);
    $component = Livewire::test(Forms::class)->call('openEdit', $form->id);

    expect(fn () => $component->set('editId', $other->id))->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    $component->set('form.facts_place', 'Lieu')->call('save');
    expect($form->fresh()->facts_place)->toBe('Lieu')->and($other->fresh()->facts_place)->toBe('Cotonou, marché');
});

it('revérifie la permission à chaque action de modification', function (string $action): void {
    $admin = formsUser('admin');
    $this->actingAs($admin);
    $form = formsStored();
    $component = Livewire::test(Forms::class)->call('openEdit', $form->id)->set('form.facts_place', 'Piraté');

    $admin->syncRoles(['membre']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call($action, ...($action === 'openEdit' ? [$form->id] : []))->assertForbidden();
    expect($form->fresh()->facts_place)->toBe('Cotonou, marché');
})->with(['openEdit', 'save']);

it('refuse la modification à un compte qui ne lit que ses fiches (policy update)', function (): void {
    $form = formsStored();
    $user = formsUser('pair');
    $user->givePermissionTo('case_forms.view_own');

    expect($user->can('update', $form))->toBeFalse()->and(formsUser('admin')->can('update', $form))->toBeTrue();
});
