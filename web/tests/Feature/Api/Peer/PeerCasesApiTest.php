<?php

declare(strict_types=1);

use App\Enums\CaseStatus;
use App\Models\CaseFollowup;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CaseFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * API `pair/cases` et suivi (P9-02, docs/API.md §22) : accès réservé au compte pair actif (403 sinon, RG-03), scope
 * `visibleTo` (un pair ne voit jamais le dossier d'un autre : même 404 neutre qu'un code inconnu, en lecture comme en
 * écriture), collecte avec code attribué par le serveur et rejeu par `client_uuid`, 422 du catalogue, statut,
 * étapes de suivi, notes et commentaires chiffrés au repos, réponses no-store.
 */

beforeEach(function (): void {
    C::seed();
    $this->peer = C::peer();
    $this->other = C::peer();
});

function peerCase(User $collector, array $attributes = []): CaseRecord
{
    return CaseRecord::factory()->collectedBy($collector)->create($attributes);
}

it('réserve l’API pair aux comptes pair actifs : 401, 403 membre, 403 pair en attente', function (): void {
    $this->getJson('/api/v1/pair/cases')->assertUnauthorized();

    F::as(F::member())->getJson('/api/v1/pair/cases')->assertForbidden()->assertJsonPath('code', 'forbidden');

    $pending = User::factory()->pending()->withProfile()->create();
    $pending->assignRole('pair');

    F::as($pending)->postJson('/api/v1/pair/cases', C::payload())->assertForbidden();
    expect(CaseRecord::query()->count())->toBe(0);
});

it('liste les seuls dossiers du pair, sans notes, archivés exclus, en no-store', function (): void {
    $mine = peerCase($this->peer, ['notes' => 'Note confidentielle du dossier.']);
    peerCase($this->peer)->forceFill(['status' => CaseStatus::Archived, 'archived_at' => now()])->save();
    $theirs = peerCase($this->other);

    $response = F::as($this->peer)->getJson('/api/v1/pair/cases')->assertOk();

    expect(array_column($response->json('data'), 'code'))->toBe([$mine->code])
        ->and($response->json('data.0'))->not->toHaveKey('notes')
        ->and($response->json('data.0.mine'))->toBeTrue()
        ->and($response->json('meta.total'))->toBe(1)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and(json_encode($response->json()))->not->toContain($theirs->code);

    F::as($this->peer)->getJson('/api/v1/pair/cases?status=archive')->assertOk()->assertJsonCount(1, 'data');
    F::as($this->peer)->getJson('/api/v1/pair/cases?status=inconnu')->assertUnprocessable();
});

it('répond le même 404 neutre au dossier d’un autre pair qu’à un code inconnu', function (): void {
    $theirs = peerCase($this->other, ['notes' => 'Récit confidentiel de l’autre pair.']);
    $as = fn () => F::as($this->peer);

    $foreign = $as()->getJson('/api/v1/pair/cases/'.$theirs->code)->assertNotFound();
    $unknown = $as()->getJson('/api/v1/pair/cases/VBG-9999')->assertNotFound();

    expect($foreign->json())->toBe($unknown->json())
        ->and($foreign->json('code'))->toBe('not_found');

    $as()->patchJson('/api/v1/pair/cases/'.$theirs->code, ['status' => 'clos'])->assertNotFound();
    $as()->getJson('/api/v1/pair/cases/'.$theirs->code.'/followups')->assertNotFound();
    $as()->postJson('/api/v1/pair/cases/'.$theirs->code.'/followups', ['date' => now()->format('Y-m-d'), 'step' => 'Accueil', 'outcome' => 'realise'])->assertNotFound();
    $as()->getJson('/api/v1/pair/cases/n-importe-quoi')->assertNotFound();

    expect($theirs->fresh()->status)->toBe(CaseStatus::Open)
        ->and(CaseFollowup::query()->count())->toBe(0);
});

it('laisse l’administration voir tous les dossiers (visibleTo)', function (): void {
    $theirs = peerCase($this->other);

    F::as(F::member('admin'))->getJson('/api/v1/pair/cases/'.strtolower($theirs->code))->assertOk()
        ->assertJsonPath('data.code', $theirs->code)
        ->assertJsonPath('data.mine', false)
        ->assertJsonMissingPath('data.client_uuid');
});

it('collecte un dossier : 201, code serveur, Location, notes chiffrées, puis rejeu 200 sans doublon', function (): void {
    $payload = C::payload(['notes' => 'Accueil réalisé, orientation vers une psychologue.']);

    $created = F::as($this->peer)->postJson('/api/v1/pair/cases', $payload)->assertCreated()
        ->assertJsonPath('data.code', 'VBG-0001')
        ->assertJsonPath('data.status', 'ouvert')
        ->assertJsonPath('data.notes', 'Accueil réalisé, orientation vers une psychologue.')
        ->assertJsonPath('data.client_uuid', $payload['client_uuid'])
        ->assertJsonPath('data.can.update', true)
        ->assertJsonPath('meta.duplicate', false)
        ->assertJsonPath('meta.warnings', []);

    expect($created->headers->get('Location'))->toEndWith('/api/v1/pair/cases/VBG-0001')
        ->and($created->headers->get('Cache-Control'))->toContain('no-store')
        ->and(DB::table('cases')->value('notes'))->not->toContain('psychologue');

    F::as($this->peer)->postJson('/api/v1/pair/cases', $payload)->assertOk()
        ->assertJsonPath('data.code', 'VBG-0001')
        ->assertJsonPath('meta.duplicate', true);

    F::as($this->other)->postJson('/api/v1/pair/cases', $payload)->assertUnprocessable()
        ->assertJsonPath('code', 'client_uuid_conflict')
        ->assertJsonMissingPath('data');

    expect(CaseRecord::query()->count())->toBe(1);
});

it('refuse une collecte invalide avec les erreurs par champ du catalogue', function (): void {
    $response = F::as($this->peer)->postJson('/api/v1/pair/cases', C::payload([
        'occurred_on' => now()->addDays(3)->format('Y-m-d'),
        'notes' => 'Trop court',
        'age_range' => '99',
        'kind' => 'XYZ',
    ]))->assertUnprocessable()->assertJsonPath('code', 'validation_failed');

    expect(array_keys($response->json('errors')))->toEqualCanonicalizing(['occurred_on', 'notes', 'age_range', 'kind'])
        ->and($response->json('errors.occurred_on.0'))->toBe('Le champ date des faits ne peut pas être dans le futur.');

    C::disable('date_not_future');

    F::as($this->peer)->postJson('/api/v1/pair/cases', C::payload(['occurred_on' => now()->addDays(3)->format('Y-m-d')]))->assertCreated();
});

it('renvoie les avertissements de plausibilité et de doublons dans meta.warnings', function (): void {
    F::as($this->peer)->postJson('/api/v1/pair/cases', C::payload())->assertCreated();

    F::as($this->peer)->postJson('/api/v1/pair/cases', C::payload())->assertCreated()
        ->assertJsonPath('meta.warnings.0.code', 'duplicate_suspected')
        ->assertJsonPath('meta.warnings.0.codes', ['VBG-0001']);
});

it('modifie un dossier, ferme puis refuse l’archivage au pair', function (): void {
    $case = peerCase($this->peer);

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['status' => 'clos', 'referral' => 'Juriste'])->assertOk()
        ->assertJsonPath('data.status', 'clos')
        ->assertJsonPath('data.referral', 'Juriste');

    expect($case->fresh()->closed_at)->not->toBeNull();

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['status' => 'archive'])->assertUnprocessable()
        ->assertJsonValidationErrors('status');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['kind' => 'VDH'])->assertUnprocessable()
        ->assertJsonValidationErrors('kind');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['occurred_on' => null])->assertUnprocessable()
        ->assertJsonValidationErrors('occurred_on');
});

it('vérifie la commune avec le département du dossier quand seul l’un change (RG-08)', function (): void {
    $zone = C::zone();
    $case = peerCase($this->peer, ['zone_id' => $zone->id, 'commune_id' => C::commune($zone)->id]);
    $otherZone = C::zone('Zou');

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['zone_id' => $otherZone->id])->assertUnprocessable()
        ->assertJsonValidationErrors('commune_id');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['commune_id' => C::commune($otherZone)->id])->assertUnprocessable()
        ->assertJsonValidationErrors('commune_id');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['zone_id' => $otherZone->id, 'commune_id' => C::commune($otherZone)->id])->assertOk()
        ->assertJsonPath('data.zone.id', $otherZone->id);
});

it('refuse la modification d’un dossier archivé (422 case_locked)', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->peer)->archived()->create();

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['referral' => 'Juriste'])->assertUnprocessable()
        ->assertJsonPath('code', 'case_locked')
        ->assertJsonPath('data.can.update', null);
});

it('refuse le rejeu d’un client_uuid avec un autre contenu : 422 idempotency_key_reused (dossier et étape)', function (): void {
    $payload = C::payload();
    F::as($this->peer)->postJson('/api/v1/pair/cases', $payload)->assertCreated();

    F::as($this->peer)->postJson('/api/v1/pair/cases', ['notes' => 'Autre récit, saisi par erreur sous la même clé.'] + $payload)
        ->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused')
        ->assertJsonValidationErrors('client_uuid')
        ->assertJsonMissingPath('data');

    $case = CaseRecord::query()->sole();
    $step = ['date' => now()->format('Y-m-d'), 'step' => 'Écoute', 'outcome' => 'realise', 'client_uuid' => (string) Str::uuid()];
    F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', $step)->assertCreated();
    F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', ['outcome' => 'annule'] + $step)->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect(CaseRecord::query()->count())->toBe(1)
        ->and($case->fresh()->notes)->toBe($payload['notes'])
        ->and(CaseFollowup::query()->sole()->outcome->value)->toBe('realise');
});

it('rejoue la réponse d’origine sous la même Idempotency-Key et refuse un autre corps (middleware idempotent)', function (): void {
    $payload = C::payload();
    $as = fn () => F::as($this->peer)->withHeader('Idempotency-Key', $payload['client_uuid']);

    $as()->postJson('/api/v1/pair/cases', $payload)->assertCreated();
    $as()->postJson('/api/v1/pair/cases', $payload)->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.code', 'VBG-0001');
    $as()->postJson('/api/v1/pair/cases', ['referral' => 'Juriste'] + $payload)->assertUnprocessable()
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect(CaseRecord::query()->count())->toBe(1);
});

it('contrôle une commune envoyée seule contre le département du dossier, même désactivé depuis (RG-08)', function (): void {
    $zone = C::zone('Atlantique');
    $case = peerCase($this->peer, ['zone_id' => $zone->id, 'commune_id' => C::commune($zone)->id]);
    $sibling = Commune::query()->where('zone_id', $zone->id)->whereKeyNot($case->commune_id)->orderBy('position')->firstOrFail();
    $zone->update(['is_active' => false]);

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['commune_id' => $sibling->id])->assertOk()
        ->assertJsonPath('data.zone.id', $zone->id);

    expect($case->fresh()->commune_id)->toBe($sibling->id);
});

it('refuse une commune inactive quand commune_in_zone est désactivée', function (): void {
    C::disable('commune_in_zone');
    $zone = C::zone();
    $case = peerCase($this->peer, ['zone_id' => $zone->id]);
    [$inactive, $active] = Commune::query()->where('zone_id', C::zone('Zou')->id)->orderBy('position')->take(2)->get()->all();
    $inactive->update(['is_active' => false]);

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['commune_id' => $inactive->id])->assertUnprocessable()
        ->assertJsonValidationErrors('commune_id');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code, ['commune_id' => $active->id])->assertOk();
});

it('refuse la modification d’une étape de suivi d’un dossier archivé ou anonymisé (422 case_locked)', function (string $state): void {
    $case = $state === 'archived'
        ? CaseRecord::factory()->collectedBy($this->peer)->archived()->create()
        : peerCase($this->peer, ['anonymized_at' => now()]);
    $followup = CaseFollowup::factory()->forCase($case)->planned()->create(['by_user_id' => $this->peer->id]);

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code.'/followups/'.$followup->id, ['outcome' => 'realise'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'case_locked');

    expect($followup->fresh()->outcome->value)->toBe('planifie');
})->with(['archived', 'anonymized']);

it('gère la frise de suivi : ajout idempotent, liste chronologique déchiffrée, réalisation', function (): void {
    $case = peerCase($this->peer);
    $uuid = (string) Str::uuid();
    $step = [
        'date' => now()->subDay()->format('Y-m-d'),
        'step' => 'Écoute',
        'outcome' => 'planifie',
        'next_on' => now()->addDays(29)->format('Y-m-d'),
        'comment' => 'Premier échange, rendez-vous à confirmer.',
        'client_uuid' => $uuid,
    ];

    $created = F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', $step)->assertCreated()
        ->assertJsonPath('data.step', 'Écoute')
        ->assertJsonPath('data.by_me', true)
        ->assertJsonPath('meta.case', $case->code);
    F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', $step)->assertOk()->assertJsonPath('meta.duplicate', true);
    F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', ['date' => now()->format('Y-m-d'), 'step' => 'Accueil', 'outcome' => 'realise'])->assertCreated();

    expect(DB::table('case_followups')->where('client_uuid', $uuid)->value('comment'))->not->toContain('rendez-vous');

    F::as($this->peer)->getJson('/api/v1/pair/cases/'.$case->code.'/followups')->assertOk()
        ->assertJsonPath('data.0.step', 'Écoute')
        ->assertJsonPath('data.0.comment', 'Premier échange, rendez-vous à confirmer.')
        ->assertJsonPath('data.1.step', 'Accueil');

    $id = $created->json('data.id');

    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code.'/followups/'.$id, ['outcome' => 'realise'])->assertOk()
        ->assertJsonPath('data.outcome', 'realise');
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$case->code.'/followups/'.$id, ['next_on' => now()->subDays(5)->format('Y-m-d')])->assertUnprocessable()
        ->assertJsonValidationErrors('next_on');

    $otherCase = peerCase($this->peer);
    F::as($this->peer)->patchJson('/api/v1/pair/cases/'.$otherCase->code.'/followups/'.$id, ['outcome' => 'annule'])->assertNotFound();

    F::as($this->peer)->getJson('/api/v1/pair/cases/'.$case->code)->assertOk()
        ->assertJsonCount(2, 'data.followups')
        ->assertJsonPath('data.notes', $case->notes);
});

it('refuse une étape de suivi datée du futur ou hors liste', function (): void {
    $case = peerCase($this->peer);

    F::as($this->peer)->postJson('/api/v1/pair/cases/'.$case->code.'/followups', [
        'date' => now()->addDays(2)->format('Y-m-d'),
        'step' => 'Thérapie de conversion',
        'outcome' => 'fini',
    ])->assertUnprocessable()->assertJsonValidationErrors(['date', 'step', 'outcome']);
});
