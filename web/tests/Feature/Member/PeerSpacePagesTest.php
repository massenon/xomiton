<?php

declare(strict_types=1);

use App\Application\Alerts\OnCallRoster;
use App\Enums\CaseStatus;
use App\Enums\DirectoryRole;
use App\Models\Alert;
use App\Models\CaseFollowup;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use App\Models\ValidationRule;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * Écrans Vue de l'espace pair (lot espace-pair, P9-03, P9-04, P9-08 ; prototype espace-pair.php, collecte.php,
 * admin/suivi.php) : tableau de bord `/espace-pair`, collecte `/collecte`, dossiers `/espace-pair/dossiers` et
 * `/espace-pair/dossiers/{code}`. Accès réservé au compte actif détenant `peer_space.access` (RG-03 : un·e pair·e en
 * attente de validation reste membre, 403 comme l'API) ; dossiers toujours dans le scope `visibleTo`.
 */

/** Valeurs d'une colonne d'une liste de props Inertia (`where('a.*.b')` n'existe pas dans AssertableInertia). */
function pluckProp(Assert $page, string $path, string $key): array
{
    return collect(data_get($page->toArray()['props'], $path))->pluck($key)->all();
}

beforeEach(function (): void {
    C::seed();
    $this->peer = C::peer();
    $this->peer->forceFill(['status' => App\Enums\UserStatus::Active])->save();
});

it('réserve les quatre écrans au compte actif détenant peer_space.access : 403 pour un·e membre, une pair·e en attente ou suspendu·e', function (string $uri): void {
    $member = F::member();
    $pending = C::peer();
    $pending->forceFill(['status' => App\Enums\UserStatus::Pending])->save();
    $suspended = C::peer();
    $suspended->forceFill(['status' => App\Enums\UserStatus::Suspended])->save();
    $case = CaseRecord::factory()->collectedBy($this->peer)->create();
    $uri = str_replace('{code}', $case->code, $uri);

    $this->actingAs($member)->get($uri)->assertForbidden();
    $this->actingAs($pending)->get($uri)->assertForbidden();
    $this->actingAs($suspended)->get($uri)->assertForbidden();
    $this->actingAs($this->peer)->get($uri)->assertOk();
})->with(['/espace-pair', '/collecte', '/espace-pair/dossiers', '/espace-pair/dossiers/{code}']);

it('renvoie un·e visiteur·se sans compte vers la connexion', function (string $uri): void {
    auth()->logout();
    $this->get($uri)->assertRedirect();
})->with(['/espace-pair', '/collecte', '/espace-pair/dossiers', '/espace-pair/dossiers/VBG-0001']);

it('sert le tableau de bord : KPI de ses dossiers, tâches, alertes routées vers ses fiches seulement', function (): void {
    CaseRecord::factory()->collectedBy($this->peer)->count(2)->create(['status' => CaseStatus::Open]);
    CaseRecord::factory()->collectedBy($this->peer)->inProgress()->create();
    CaseRecord::factory()->collectedBy($this->peer)->closed()->create();
    CaseRecord::factory()->count(3)->create(); // dossiers d'un autre pair : jamais comptés
    $contact = DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->linkedTo($this->peer)->create();
    $mine = Alert::factory()->routedTo($contact)->create(['description' => 'Récit confidentiel', 'contact' => '+22901000000']);
    Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo(C::peer())->create())->create();

    $this->actingAs($this->peer)->get('/espace-pair')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PeerSpace/Home')
            ->where('title', 'Espace pair')
            ->where('kpi', ['collected' => 4, 'this_month' => 4, 'in_progress' => 3, 'closed' => 1])
            ->where('tasks', [['key' => 'alerts_to_take', 'count' => 1], ['key' => 'cases_to_contact', 'count' => 2]])
            ->where('alerts.open', 1)
            ->where('alerts.new', 1)
            ->has('alerts.items', 1)
            ->where('alerts.items.0.id', $mine->ulid)
            ->missing('alerts.items.0.description')
            ->missing('alerts.items.0.contact')
            ->where('on_duty', false)
            ->where('poll_seconds', 0));
});

it('interroge les alertes toutes les 10 s seulement si le compte est de garde (plage focal en cours)', function (): void {
    $this->actingAs($this->peer)->get('/espace-pair')->assertInertia(fn (Assert $page) => $page->where('on_duty', false)->where('poll_seconds', 0));

    OnCallShift::factory()->forUser($this->peer)->focal()->create();

    $this->actingAs($this->peer->fresh())->get('/espace-pair')
        ->assertInertia(fn (Assert $page) => $page->where('on_duty', true)->where('poll_seconds', 10));

    expect(app(OnCallRoster::class)->isFocalOnDuty($this->peer))->toBeTrue()
        ->and(app(OnCallRoster::class)->isFocalOnDuty(F::member()))->toBeFalse();
});

it('ne tient pas pour « de garde » une plage admin, passée ou à venir', function (): void {
    OnCallShift::factory()->forUser($this->peer)->admin()->create();
    OnCallShift::factory()->forUser($this->peer)->focal()->past()->create();
    OnCallShift::factory()->forUser($this->peer)->focal()->upcoming()->create();

    expect(app(OnCallRoster::class)->isFocalOnDuty($this->peer))->toBeFalse();
});

it('sert la collecte : listes fermées, règles actives du catalogue, jour du Bénin, derniers dossiers à soi', function (): void {
    $mine = CaseRecord::factory()->collectedBy($this->peer)->create();
    CaseRecord::factory()->create();

    $this->actingAs($this->peer)->get('/collecte')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('PeerSpace/Collecte')
            ->where('title', 'Collecte de données')
            ->where('form.kinds.0', ['value' => 'VBG', 'label' => 'Violence basée sur le genre'])
            ->has('form.zones')
            ->has('form.zones.0.communes')
            ->has('form.populations')
            ->where('form.sexes.0.value', 'femme')
            ->has('form.violence_types')
            ->where('options.referrals', ['Point focal', 'Juriste', 'Psychologue', 'Gynécologue', 'Hébergement'])
            ->where('options.notes_min_length', 20)
            ->has('options.age_ranges')
            ->where('rules.0.key', 'required')
            ->where('today', now(config('xomiton.display_timezone', 'Africa/Porto-Novo'))->format('Y-m-d'))
            ->where('draft_days', 1)
            ->has('recent', 1)
            ->where('recent.0.code', $mine->code)
            ->missing('recent.0.notes'));
});

it('retire de la collecte les règles désactivées par l’administration et les tranches d’âge hors bornes', function (): void {
    ValidationRule::query()->where('key', 'date_not_future')->update(['is_active' => false]);
    C::params('plausibility', ['age_min' => 18, 'age_max' => 60]);

    $this->actingAs($this->peer)->get('/collecte')
        ->assertInertia(function (Assert $page): void {
            $keys = collect($page->toArray()['props']['rules'])->pluck('key')->all();
            $ages = collect($page->toArray()['props']['options']['age_ranges'])->pluck('value')->all();

            expect($keys)->not->toContain('date_not_future')->toContain('required', 'min_len_20')
                ->and($ages)->not->toContain('10-14')->toContain('18-24');
        });
});

it('liste ses dossiers seulement, filtrés par statut, nature et code, jamais les notes, en no-store', function (): void {
    $open = CaseRecord::factory()->collectedBy($this->peer)->vbg()->create();
    $closed = CaseRecord::factory()->collectedBy($this->peer)->vdh()->closed()->create();
    $archived = CaseRecord::factory()->collectedBy($this->peer)->archived()->create();
    $foreign = CaseRecord::factory()->vbg()->create();

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers')->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('PeerSpace/Cases')
        ->where('filters', ['status' => null, 'kind' => null, 'q' => null])
        ->has('statuses', 3)
        ->has('kinds', 2)
        ->where('search_max', App\Http\Requests\Peer\ListCasesRequest::SEARCH_MAX)
        ->where('cases.meta.total', 2)
        ->missing('cases.data.0.notes')
        ->etc());
    $response->assertInertia(fn (Assert $page) => expect(pluckProp($page, 'cases.data', 'code'))->toBe([$closed->code, $open->code]));
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');

    $this->actingAs($this->peer)->get('/espace-pair/dossiers?status=clos')
        ->assertInertia(fn (Assert $page) => expect(pluckProp($page, 'cases.data', 'code'))->toBe([$closed->code]) && $page->where('filters.status', 'clos'));
    $this->actingAs($this->peer)->get('/espace-pair/dossiers?kind=VBG&q='.$open->code)
        ->assertInertia(fn (Assert $page) => expect(pluckProp($page, 'cases.data', 'code'))->toBe([$open->code]));
    $this->actingAs($this->peer)->get('/espace-pair/dossiers?status=archive')
        ->assertInertia(fn (Assert $page) => expect(pluckProp($page, 'cases.data', 'code'))->toBe([$archived->code]));
    $this->actingAs($this->peer)->get('/espace-pair/dossiers?q='.$foreign->code)
        ->assertInertia(fn (Assert $page) => $page->where('cases.meta.total', 0));
});

it('lit les filtres avec tolérance : valeur inconnue ignorée, page invalide ramenée à 1', function (): void {
    CaseRecord::factory()->collectedBy($this->peer)->count(2)->create();

    $this->actingAs($this->peer)->get('/espace-pair/dossiers?status=nimporte&kind=zzz&page=abc&q=%20%20')->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['status' => null, 'kind' => null, 'q' => null])
            ->where('cases.meta.current_page', 1)
            ->where('cases.meta.total', 2));
});

it('pagine la liste selon xomiton.peer.cases_per_page', function (): void {
    config(['xomiton.peer.cases_per_page' => 2]);
    CaseRecord::factory()->collectedBy($this->peer)->count(5)->create();

    $this->actingAs($this->peer)->get('/espace-pair/dossiers?page=3')
        ->assertInertia(fn (Assert $page) => $page
            ->where('cases.meta', ['current_page' => 3, 'last_page' => 3, 'per_page' => 2, 'total' => 5])
            ->has('cases.data', 1));
});

it('sert la fiche d’un dossier : notes, frise, cinq sections tirées de la collecte sans fiche de référence', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create(['notes' => 'Accueil réalisé hier.', 'referral' => 'Juriste']);
    CaseFollowup::factory()->forCase($case)->planned()->create(['step' => 'Écoute', 'by_user_id' => $this->peer->id]);

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.strtolower($case->code))->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('PeerSpace/CaseShow')
        ->where('title', $case->code)
        ->where('record.code', $case->code)
        ->where('record.notes', 'Accueil réalisé hier.')
        ->where('record.can', ['update' => true, 'add_followup' => true])
        ->has('record.followups', 1)
        ->where('record.followups.0.step', 'Écoute')
        ->where('sheet.filled', false)
        ->where('sheet.reference', null)
        ->where('sheet.facts.description', 'Accueil réalisé hier.')
        ->where('sheet.orientation.referral', 'Juriste')
        ->where('sheet.services.items', [])
        ->where('sheet.consent.given', null)
        ->where('steps.0', 'Accueil')
        ->has('outcomes', 4)
        ->where('limits.comment_max', 1000)
        ->has('today')
        ->etc());
    $response->assertInertia(fn (Assert $page) => expect(pluckProp($page, 'statuses', 'value'))->toBe(['en_cours', 'clos']));
    expect((string) $response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sert la fiche de référence quand elle existe, sans l’auteur des faits ni le nom du signataire', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create();
    $form = CaseForm::factory()->shareable()->create([
        'case_id' => $case->id,
        'facts_author' => 'Voisin du quartier',
        'facts_description' => 'Récit de la fiche',
        'services' => CaseForm::normalizeServices(['ecoute' => true, 'juridique' => true]),
        'referral_label' => 'Psychologue',
        'referral_urgency' => 'haute',
    ]);

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sheet.filled', true)
        ->where('sheet.reference', $form->reference)
        ->where('sheet.facts.description', 'Récit de la fiche')
        ->where('sheet.services.items', ['Écoute / accueil', 'Appui juridique'])
        ->where('sheet.orientation.referral', 'Psychologue')
        ->where('sheet.orientation.urgency', 'haute')
        ->where('sheet.consent', ['given' => true, 'mode' => 'ecrit', 'sharing' => true])
        ->missing('sheet.facts.author')
        ->missing('sheet.consent.signatory'));
    expect($response->getContent())->not->toContain('Voisin du quartier')->not->toContain((string) $form->consent_signatory);
});

it('répond le même 404 pour le dossier d’un autre pair, un code inconnu ou illisible', function (string $code): void {
    $foreign = CaseRecord::factory()->create();
    $code = $code === 'foreign' ? $foreign->code : $code;

    $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$code)->assertNotFound();
})->with(['foreign', 'VBG-9999', 'abc', 'VBG-1;DROP']);

it('verrouille l’ajout d’étape sur un dossier archivé (can.add_followup faux)', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->archived()->create();

    $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$case->code)
        ->assertInertia(fn (Assert $page) => $page->where('record.can.add_followup', false)->where('statuses', []));
});

it('laisse l’administration ouvrir tous les dossiers, comme l’API', function (): void {
    $admin = F::member('admin');
    $admin->forceFill(['status' => App\Enums\UserStatus::Active])->save();
    $case = CaseRecord::factory()->create();

    $this->actingAs($admin)->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $this->actingAs($admin)->get('/espace-pair/dossiers')->assertInertia(fn (Assert $page) => $page->where('cases.meta.total', 1));
});

it('masque récit et note de services d’un dossier anonymisé, même avec une fiche de référence', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create(['notes' => 'Brouillon de la collecte', 'anonymized_at' => now()->subDay()]);
    CaseForm::factory()->create([
        'case_id' => $case->id,
        'facts_description' => 'Récit de la fiche',
        'services_note' => 'Note de services de la fiche',
    ]);

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('record.anonymized', true)
        ->where('record.notes', null)
        ->where('sheet.filled', true)
        ->where('sheet.facts.description', null)
        ->where('sheet.services.note', null));
    expect($response->getContent())->not->toContain('Brouillon de la collecte')->not->toContain('Récit de la fiche')->not->toContain('Note de services de la fiche');
});

it('masque le brouillon de la collecte d’un dossier anonymisé sans fiche de référence', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create(['notes' => 'Brouillon de la collecte', 'anonymized_at' => now()->subDay()]);

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page->where('sheet.filled', false)->where('sheet.facts.description', null));
    expect($response->getContent())->not->toContain('Brouillon de la collecte');
});

it('sert la fiche de référence la plus récente quand le dossier en a plusieurs', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create();
    CaseForm::factory()->create(['case_id' => $case->id, 'facts_description' => 'Ancienne fiche']);
    $latest = CaseForm::factory()->create(['case_id' => $case->id, 'facts_description' => 'Fiche la plus récente']);

    $response = $this->actingAs($this->peer)->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sheet.reference', $latest->reference)
        ->where('sheet.facts.description', 'Fiche la plus récente'));
    expect($response->getContent())->not->toContain('Ancienne fiche');
});

it('retombe sur la collecte, sans fiche de référence, pour un rôle sans case_forms.view_own', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->create(['notes' => 'Notes de la collecte', 'referral' => 'Juriste']);
    CaseForm::factory()->create(['case_id' => $case->id, 'facts_description' => 'Récit de la fiche', 'referral_label' => 'Psychologue']);
    Spatie\Permission\Models\Role::findByName('pair', 'web')->revokePermissionTo('case_forms.view_own');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $response = $this->actingAs($this->peer->fresh())->get('/espace-pair/dossiers/'.$case->code)->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->where('sheet.filled', false)
        ->where('sheet.reference', null)
        ->where('sheet.facts.description', 'Notes de la collecte')
        ->where('sheet.orientation.referral', 'Juriste'));
    expect($response->getContent())->not->toContain('Récit de la fiche')->not->toContain('Psychologue');
});

it('sert l’accueil avec la taille de page de l’API et le total réel des alertes ouvertes', function (): void {
    config(['xomiton.api.dashboard_routed_alerts' => 3]);
    $contact = DirectoryContact::factory()->create(['user_id' => $this->peer->id]);
    Alert::factory()->count(7)->create(['routed_to' => $contact->id]);

    $this->actingAs($this->peer)->get('/espace-pair')->assertInertia(fn (Assert $page) => $page
        ->where('alerts.open', 7)
        ->has('alerts.items', 3)
        ->where('per_page', 3)
        ->where('max_per_page', 50));
});

it('recalcule « de garde » à la demande par une visite partielle, sans recharger KPI ni alertes', function (): void {
    $this->actingAs($this->peer)->get('/espace-pair')->assertInertia(fn (Assert $page) => $page->where('on_duty', false));
    OnCallShift::factory()->forUser($this->peer)->focal()->create();

    $response = $this->actingAs($this->peer->fresh())->get('/espace-pair', [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => (string) Inertia\Inertia::getVersion(),
        'X-Inertia-Partial-Component' => 'PeerSpace/Home',
        'X-Inertia-Partial-Data' => 'on_duty,poll_seconds',
    ])->assertOk();

    $props = $response->json('props');
    expect($props['on_duty'])->toBeTrue()
        ->and($props['poll_seconds'])->toBe(10)
        ->and($props)->not->toHaveKey('kpi')->not->toHaveKey('alerts');
});
