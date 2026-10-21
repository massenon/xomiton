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
