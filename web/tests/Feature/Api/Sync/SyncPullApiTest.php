<?php

declare(strict_types=1);

use App\Application\Account\DeviceRegistry;
use App\Application\Sync\Pull\PullChanges;
use App\Enums\AlertStatus;
use App\Enums\CaseStatus;
use App\Enums\ContentStatus;
use App\Enums\DirectoryRole;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\SyncCursor;
use App\Models\SyncDevice;
use App\Models\SyncTombstone;
use App\Models\Thematique;
use App\Models\User;
use App\Models\ValidationRule;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;

/*
 * P9-06 (lot synchro) : `GET sync/pull?since=` — tirage complet (`reset`) puis deltas par curseur, collections selon le
 * compte (publiques pour tout le monde ; dossiers, suivis, alertes routées, règles pour le pair), suppression serveur
 * (logique, définitive, dépublication, réattribution) retirée de l'appareil, pages (`has_more`), fiche programmée à son
 * heure, curseur illisible 422, curseur trop vieux → tirage complet, `sync_cursors` tenu.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    config(['xomiton.sync.pull_lag_seconds' => 0]);

    $this->pair = AlertFixtures::member('pair');
    $this->device = SyncDevice::factory()->create(['user_id' => $this->pair->id]);
    Sanctum::actingAs($this->pair);
});

function syncPull(?string $since = null, ?SyncDevice $device = null, array $query = []): TestResponse
{
    $params = array_filter(['since' => $since] + $query, static fn (mixed $value): bool => $value !== null);

    return test()->withHeader(DeviceRegistry::HEADER, ($device ?? test()->device)->device_uuid)
        ->getJson('/api/v1/sync/pull'.($params === [] ? '' : '?'.http_build_query($params)));
}

/** Identifiants servis par une page pour une collection. */
function pulledKeys(TestResponse $response, string $collection): array
{
    return array_column((array) $response->json("data.$collection.upserts"), 'id');
}

it('sert les collections publiques à tout compte et celles du pair au pair seulement', function (): void {
    $member = AlertFixtures::member();
    $memberDevice = SyncDevice::factory()->create(['user_id' => $member->id]);
    Sanctum::actingAs($member);

    syncPull(device: $memberDevice)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.collections', ['emergency_contacts', 'directory_contacts', 'thematiques'])
        ->assertJsonPath('meta.has_more', false)
        ->assertJsonPath('data.directory_contacts.reset', true);

    Sanctum::actingAs($this->pair);
    $response = syncPull()->assertOk()
        ->assertJsonPath('meta.collections', ['emergency_contacts', 'directory_contacts', 'thematiques', 'validation_rules', 'cases', 'case_followups', 'alerts']);

    expect($response->json('meta.cursor'))->toBeString()
        ->and($response->json('meta.emergency_numbers'))->toBeArray()
        ->and(SyncCursor::query()->forDevice($this->device->id)->pluck('collection')->sort()->values()->all())->toBe(collect(SyncCursor::COLLECTIONS)->sort()->values()->all())
        ->and(PullChanges::names())->toBe(SyncCursor::COLLECTIONS)
        ->and($this->device->fresh()->last_sync_at)->not->toBeNull();

    syncPull(query: ['collections' => ['cases']])->assertOk()->assertJsonPath('meta.collections', ['cases']);
    syncPull(query: ['collections' => ['inconnue']])->assertUnprocessable()->assertJsonValidationErrors('collections.0');
});

it('retire de l’appareil une fiche supprimée, masquée ou dépubliée et sert les modifications par curseur', function (): void {
    $kept = DirectoryContact::factory()->create(['name' => 'Fiche gardée']);
    $deleted = DirectoryContact::factory()->role(DirectoryRole::Juriste)->create();
    $hidden = DirectoryContact::factory()->create();
    $thematique = Thematique::factory()->create();

    $first = syncPull()->assertOk();
    expect(pulledKeys($first, 'directory_contacts'))->toContain($kept->id, $deleted->id, $hidden->id)
        ->and(pulledKeys($first, 'emergency_contacts'))->toContain($deleted->id)
        ->and(pulledKeys($first, 'thematiques'))->toContain($thematique->id)
        ->and($first->json('data.directory_contacts.deletes'))->toBe([]);

    // Rien de neuf : aucune ligne, plus de tirage complet.
    $this->travel(10)->seconds();
    $quiet = syncPull($first->json('meta.cursor'))->assertOk()
        ->assertJsonPath('data.directory_contacts.reset', false)
        ->assertJsonPath('data.directory_contacts.upserts', [])
        ->assertJsonPath('data.directory_contacts.deletes', []);

    $this->travel(10)->seconds();
    $kept->update(['name' => 'Fiche renommée']);
    $deleted->delete();
    $hidden->update(['is_public' => false]);
    $thematique->update(['status' => ContentStatus::Draft]);
    $this->travel(10)->seconds();

    $delta = syncPull($quiet->json('meta.cursor'))->assertOk();

    expect(pulledKeys($delta, 'directory_contacts'))->toBe([$kept->id])
        ->and($delta->json('data.directory_contacts.upserts.0.name'))->toBe('Fiche renommée')
        ->and($delta->json('data.directory_contacts.deletes'))->toEqualCanonicalizing([$deleted->id, $hidden->id])
        ->and($delta->json('data.emergency_contacts.deletes'))->toContain($deleted->id)
        ->and($delta->json('data.thematiques.deletes'))->toBe([$thematique->id]);

    // Suppression définitive : pierre tombale, retirée de l'appareil au tirage suivant.
    $this->travel(10)->seconds();
    $deleted->forceDelete();
    $this->travel(10)->seconds();

    $after = syncPull($delta->json('meta.cursor'))->assertOk();
    expect($after->json('data.directory_contacts.deletes'))->toBe([$deleted->id])
        ->and(SyncTombstone::query()->where('collection', 'directory_contacts')->pluck('subject_key')->all())->toEqualCanonicalizing([(string) $hidden->id, (string) $deleted->id]);
});

it('pagine un tirage complet sans perdre de ligne (has_more)', function (): void {
    config(['xomiton.sync.pull_page_size' => 2]);
    $ids = DirectoryContact::factory()->count(5)->create()->pluck('id')->all();
    $this->travel(5)->seconds();

    $seen = [];
    $cursor = null;
    $pages = 0;

    do {
        $page = syncPull($cursor, query: ['collections' => ['directory_contacts']])->assertOk();
        $seen = [...$seen, ...pulledKeys($page, 'directory_contacts')];
        $cursor = $page->json('meta.cursor');
        $pages++;
        expect($page->json('data.directory_contacts.reset'))->toBe($pages === 1);
    } while ($page->json('meta.has_more') && $pages < 10);

    expect($pages)->toBe(3)->and($seen)->toEqualCanonicalizing($ids);
});

it('sert les dossiers du pair et les retire quand ils sont archivés ou réattribués', function (): void {
    $other = AlertFixtures::member('pair');
    $mine = CaseRecord::factory()->collectedBy($this->pair)->create(['notes' => 'Notes de la collectrice, assez longues.']);
    $moved = CaseRecord::factory()->collectedBy($this->pair)->create();
    $archived = CaseRecord::factory()->collectedBy($this->pair)->create();
    $foreign = CaseRecord::factory()->collectedBy($other)->create();
    ValidationRule::query()->delete();
    $rule = ValidationRule::query()->create(['key' => 'email', 'label' => 'E-mail valide', 'description' => 'Adresse e-mail bien formée.', 'is_active' => true, 'is_locked' => false, 'position' => 1]);

    $first = syncPull()->assertOk();
    expect(pulledKeys($first, 'cases'))->toEqualCanonicalizing([$mine->id, $moved->id, $archived->id])
        ->and($first->json('data.cases.upserts.0'))->toHaveKeys(['code', 'kind', 'status', 'notes', 'occurred_on', 'age_range'])
        ->and(collect($first->json('data.cases.upserts'))->firstWhere('id', $mine->id)['notes'])->toBe('Notes de la collectrice, assez longues.')
        ->and(pulledKeys($first, 'validation_rules'))->toBe([$rule->id]);

    $this->travel(10)->seconds();
    $moved->update(['collected_by' => $other->id]);
    $archived->update(['status' => CaseStatus::Archived]);
    $foreign->update(['referral' => 'Juriste']);
    $rule->delete();
    $this->travel(10)->seconds();

    $delta = syncPull($first->json('meta.cursor'))->assertOk();
    expect($delta->json('data.cases.deletes'))->toEqualCanonicalizing([$moved->id, $archived->id])
        ->and(pulledKeys($delta, 'cases'))->toBe([])
        ->and($delta->json('data.validation_rules.deletes'))->toBe([$rule->id]);

    // La collègue qui reçoit le dossier le voit à son prochain tirage, sans pierre tombale.
    $otherDevice = SyncDevice::factory()->create(['user_id' => $other->id]);
    Sanctum::actingAs($other);
    expect(pulledKeys(syncPull(device: $otherDevice), 'cases'))->toEqualCanonicalizing([$moved->id, $foreign->id]);
});

it('sert les alertes routées vers la fiche du pair (résumé, clé ULID) et les retire une fois closes ou routées ailleurs', function (): void {
    $contact = DirectoryContact::factory()->linkedTo($this->pair)->create();
    $elsewhere = DirectoryContact::factory()->create();
    $closing = Alert::factory()->routedTo($contact)->create();
    $rerouted = Alert::factory()->routedTo($contact)->create();
    Alert::factory()->routedTo($elsewhere)->create();

    $first = syncPull(query: ['collections' => ['alerts']])->assertOk();
    expect(pulledKeys($first, 'alerts'))->toEqualCanonicalizing([$closing->ulid, $rerouted->ulid])
        ->and($first->json('data.alerts.upserts.0'))->not->toHaveKeys(['description', 'contact', 'lat']);

    $this->travel(10)->seconds();
    $closing->update(['status' => AlertStatus::Closed, 'closed_at' => now()]);
    $rerouted->update(['routed_to' => $elsewhere->id]);
    $this->travel(10)->seconds();

    syncPull($first->json('meta.cursor'), query: ['collections' => ['alerts']])->assertOk()
        ->assertJsonPath('data.alerts.upserts', [])
        ->assertJsonPath('data.alerts.deletes', fn (array $deletes): bool => collect($deletes)->sort()->values()->all() === collect([$closing->ulid, $rerouted->ulid])->sort()->values()->all());
});

it('fait entrer une fiche programmée à son heure de publication', function (): void {
    $scheduled = Thematique::factory()->scheduled()->create(['published_at' => now()->addHour()]);

    $first = syncPull(query: ['collections' => ['thematiques']])->assertOk();
    expect(pulledKeys($first, 'thematiques'))->not->toContain($scheduled->id);

    $this->travel(2)->hours();
    $later = syncPull($first->json('meta.cursor'), query: ['collections' => ['thematiques']])->assertOk();

    expect(pulledKeys($later, 'thematiques'))->toBe([$scheduled->id])
        ->and($later->json('data.thematiques.upserts.0'))->toHaveKeys(['title', 'body', 'sections', 'content_warning']);
});

it('refuse un curseur illisible (422) et refait un tirage complet après la rétention des pierres tombales', function (): void {
    syncPull('pas-un-curseur')->assertUnprocessable()->assertJsonPath('code', 'cursor_invalid');

    $first = syncPull(query: ['collections' => ['directory_contacts']])->assertOk();
    $this->travel(91)->days();
    Artisan::call('sync:prune');

    syncPull($first->json('meta.cursor'), query: ['collections' => ['directory_contacts']])->assertOk()
        ->assertJsonPath('data.directory_contacts.reset', true);
});

it('exige un appareil enregistré du compte', function (): void {
    $stranger = SyncDevice::factory()->create(['user_id' => User::factory()->create()->id]);

    syncPull(device: $stranger)->assertForbidden()->assertJsonPath('code', 'device_required');
    $this->flushHeaders()->getJson('/api/v1/sync/conflicts')->assertForbidden()->assertJsonPath('code', 'device_required');
});

it('ne sert pas les collections du pair à un compte pair en attente de validation (RG-03)', function (): void {
    CaseRecord::factory()->collectedBy($this->pair)->create();
    $this->pair->forceFill(['status' => UserStatus::Pending])->save();
    Sanctum::actingAs($this->pair->fresh());

    syncPull()->assertOk()->assertJsonPath('meta.collections', ['emergency_contacts', 'directory_contacts', 'thematiques']);
    syncPull(query: ['collections' => ['cases', 'alerts']])->assertOk()->assertJsonPath('meta.collections', []);
});

it('n’écrit jamais de pierre tombale pour tout le monde dans une collection du compte ni ne révèle une fiche réservée au réseau', function (): void {
    // Alerte routée vers une fiche sans compte lié, alerte sans fiche : aucun appareil ne les a reçues.
    $unlinked = Alert::factory()->routedTo(DirectoryContact::factory()->create())->create();
    $unrouted = Alert::factory()->create(['routed_to' => null]);
    $network = DirectoryContact::factory()->hidden()->role(DirectoryRole::Juriste)->create();
    $unpublished = DirectoryContact::factory()->role(DirectoryRole::Juriste)->create();

    $first = syncPull()->assertOk();
    expect(pulledKeys($first, 'directory_contacts'))->toContain($unpublished->id)->not->toContain($network->id);

    $this->travel(10)->seconds();
    $unlinked->delete();
    $unrouted->delete();
    $network->update(['name' => 'Fiche du réseau renommée']);
    $unpublished->update(['is_public' => false]);
    $this->travel(10)->seconds();

    $delta = syncPull($first->json('meta.cursor'))->assertOk();
    expect($delta->json('data.directory_contacts.deletes'))->toBe([$unpublished->id])
        ->and($delta->json('data.emergency_contacts.deletes'))->toBe([$unpublished->id]);

    $this->travel(10)->seconds();
    $network->forceDelete();
    $unpublished->forceDelete();
    $this->travel(10)->seconds();

    $after = syncPull($delta->json('meta.cursor'))->assertOk();
    expect($after->json('data.directory_contacts.deletes'))->toBe([])
        ->and($after->json('data.emergency_contacts.deletes'))->toBe([])
        ->and(SyncTombstone::query()->whereIn('collection', ['cases', 'case_followups', 'alerts'])->count())->toBe(0)
        ->and(SyncTombstone::query()->where('collection', 'directory_contacts')->pluck('subject_key')->all())->toBe([(string) $unpublished->id]);
});

it('purge par sync:prune les pierres tombales plus vieilles que la rétention et garde les récentes', function (): void {
    $old = SyncTombstone::query()->create(['collection' => 'directory_contacts', 'subject_key' => '101', 'user_id' => null]);
    $old->forceFill(['created_at' => now()->subDays(91)])->save();
    SyncTombstone::query()->create(['collection' => 'cases', 'subject_key' => '102', 'user_id' => $this->pair->id]);

    Artisan::call('sync:prune');

    expect(SyncTombstone::query()->pluck('subject_key')->all())->toBe(['102']);
});
