<?php

declare(strict_types=1);

use App\Application\Account\DeviceRegistry;
use App\Application\Cases\CaseValidator;
use App\Application\Sync\Push\PushItem;
use App\Enums\AlertSource;
use App\Enums\AlertType;
use App\Enums\CaseStatus;
use App\Enums\ConflictStatus;
use App\Enums\UserStatus;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Http\Requests\Api\Sync\SyncItemDecoder;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\Commune;
use App\Models\IdempotencyKey;
use App\Models\Message;
use App\Models\Population;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\SyncOutboxReceipt;
use App\Models\ValidationRule;
use App\Models\ViolenceType;
use App\Notifications\SosBurstDetected;
use App\Notifications\SyncConflict as SyncConflictNotification;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;
use Tests\Support\MessagingFixtures;

/*
 * P9-06 (lot synchro) : `POST sync/push` — lot de 50 rejoué deux fois sans doublon (même clé de lot : réponse d'origine ;
 * autre clé : `duplicate` et mêmes identifiants), un élément en erreur n'arrête pas le lot (SOS d'abord, champ refusé
 * d'un SOS retiré), reçus `sync_outbox_receipts` (jamais pour un élément anonyme), conflit de champ 409 enregistré
 * dans `sync_conflicts` + cloche admin, `collecte` créée par CreateCase (lot dossiers), appareil obligatoire,
 * liste blanche de maintenance ; `GET sync/conflicts` et `POST heartbeat`.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();

    $this->pair = AlertFixtures::member('pair');
    $this->device = SyncDevice::factory()->create(['user_id' => $this->pair->id, 'pending_count' => 0]);
    Sanctum::actingAs($this->pair);
});

function syncPush(array $items, ?string $batch = null, ?SyncDevice $device = null, array $extra = []): TestResponse
{
    return test()->withHeaders([
        HandleIdempotencyKey::HEADER => $batch ?? (string) Str::uuid(),
        DeviceRegistry::HEADER => ($device ?? test()->device)->device_uuid,
    ])->postJson('/api/v1/sync/push', ['items' => $items] + $extra);
}

function syncReportItem(array $payload = [], ?string $uuid = null): array
{
    return ['uuid' => $uuid ?? (string) Str::uuid(), 'type' => 'signalement', 'payload' => array_replace([
        'type' => 'report',
        'category' => ViolenceType::query()->where('is_active', true)->value('slug'),
        'description' => 'Mon voisin me menace depuis trois semaines, je n’ose plus sortir.',
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        'anonymous' => false,
    ], $payload)];
}

function syncCaseCreate(array $payload = [], ?string $uuid = null): array
{
    return ['uuid' => $uuid ?? (string) Str::uuid(), 'type' => 'collecte', 'payload' => array_replace([
        'kind' => 'VBG',
        'occurred_on' => now()->subDays(3)->format('Y-m-d'),
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        'population_id' => Population::query()->where('is_active', true)->value('id'),
        'sex' => 'femme',
        'age_range' => '18-24',
        'violence_type_id' => ViolenceType::query()->where('is_active', true)->value('id'),
        'referral' => 'Juriste',
        'notes' => 'Orientée vers la juriste après un premier échange.',
    ], $payload)];
}

function syncSosItem(?string $uuid = null): array
{
    return ['uuid' => $uuid ?? (string) Str::uuid(), 'type' => 'sos', 'payload' => []];
}

function syncMessageItem(string $conversation, string $body = 'Je serai là demain.'): array
{
    return ['uuid' => (string) Str::uuid(), 'type' => 'message', 'payload' => ['conversation' => $conversation, 'body' => $body]];
}

function syncCaseUpdate(CaseRecord $case, array $changes, array $base, ?string $uuid = null): array
{
    return ['uuid' => $uuid ?? (string) Str::uuid(), 'type' => 'collecte', 'op' => 'update', 'payload' => [
        'server_id' => $case->id, 'changes' => $changes, 'base' => $base,
    ]];
}

it('refuse sans compte, sans appareil enregistré, avec un appareil révoqué et sans clé de lot', function (): void {
    syncPush([syncReportItem()], device: SyncDevice::factory()->create())->assertForbidden()->assertJsonPath('code', 'device_required');
    syncPush([syncReportItem()], device: SyncDevice::factory()->revoked()->create(['user_id' => $this->pair->id]))
        ->assertForbidden()->assertJsonPath('code', 'device_revoked');
    $this->flushHeaders()->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->postJson('/api/v1/sync/push', ['items' => [syncReportItem()]])
        ->assertUnprocessable()->assertJsonPath('code', 'idempotency_key_required');
    $this->flushHeaders()->withHeaders([HandleIdempotencyKey::HEADER => (string) Str::uuid()])->postJson('/api/v1/sync/push', ['items' => [syncReportItem()]])
        ->assertForbidden()->assertJsonPath('code', 'device_required');

    expect(Alert::query()->count())->toBe(0);
});

it('rejoue un lot de 50 deux fois sans doublon : même clé → réponse d’origine, autre clé → duplicate et mêmes identifiants', function (): void {
    // Quota `report` des signalements (10 par heure et par IP) relevé : ce test porte sur l'idempotence, pas sur le quota.
    config(['xomiton.rate_limits.report_per_hour_per_ip' => 1000]);
    $items = array_map(static fn (): array => syncReportItem(), range(1, 50));
    $batch = (string) Str::uuid();

    $first = syncPush($items, $batch, extra: ['pending' => 3])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.created', 50)->assertJsonCount(50, 'data.items');

    expect(Alert::query()->count())->toBe(50)
        ->and(SyncOutboxReceipt::query()->forDevice($this->device->id)->count())->toBe(50)
        ->and(SyncOutboxReceipt::query()->where('result', 'created')->where('kind', 'alert')->count())->toBe(50)
        ->and($first->json('data.items.0'))->toMatchArray(['uuid' => $items[0]['uuid'], 'result' => 'created', 'status' => 201, 'code' => null])
        ->and(Alert::query()->where('idempotency_key', $items[0]['uuid'])->value('ulid'))->toBe($first->json('data.items.0.server_id'))
        ->and(Alert::query()->where('source', AlertSource::SyncOffline->value)->where('device_id', $this->device->id)->where('user_id', $this->pair->id)->count())->toBe(50)
        ->and($this->device->fresh()->pending_count)->toBe(3)
        ->and($this->device->fresh()->last_sync_at)->not->toBeNull();

    // Même lot, même clé : la réponse d'origine, rien de neuf.
    $replay = syncPush($items, $batch, extra: ['pending' => 3])->assertOk()->assertHeader(HandleIdempotencyKey::REPLAYED_HEADER, 'true');
    expect($replay->json())->toBe($first->json());

    // Mêmes éléments dans un nouveau lot : chaque élément est un doublon, mêmes identifiants et codes de suivi.
    $again = syncPush($items)->assertOk()->assertJsonPath('meta.duplicates', 50)->assertJsonPath('meta.created', 0);

    foreach (range(0, 49) as $i) {
        expect($again->json("data.items.$i.result"))->toBe('duplicate')
            ->and($again->json("data.items.$i.status"))->toBe(200)
            ->and($again->json("data.items.$i.server_id"))->toBe($first->json("data.items.$i.server_id"))
            ->and($again->json("data.items.$i.reference"))->toBe($first->json("data.items.$i.reference"));
    }

    expect(Alert::query()->count())->toBe(50)->and(SyncOutboxReceipt::query()->count())->toBe(50);
});

it('traite chaque élément à part : un élément en erreur n’arrête pas le lot, SOS d’abord, champ refusé d’un SOS retiré', function (): void {
    $sosUuid = (string) Str::uuid();
    $items = [
        syncReportItem(['description' => 'Trop court']),
        ['uuid' => (string) Str::uuid(), 'type' => 'inconnu', 'payload' => []],
        syncReportItem(['anonymous' => true]),
        syncReportItem(),
        ['uuid' => (string) Str::uuid(), 'type' => 'message', 'payload' => ['conversation' => (string) Str::ulid(), 'body' => 'Bonjour']],
        ['uuid' => $sosUuid, 'type' => 'sos', 'payload' => ['phone' => 'pas un numéro', 'message' => 'Aide-moi', 'zone_id' => AlertFixtures::zone('Littoral')->id]],
        ['uuid' => (string) Str::uuid(), 'type' => 'collecte', 'payload' => ['kind' => 'VBG']],
        ['uuid' => (string) Str::uuid(), 'type' => 'collecte', 'op' => 'update', 'payload' => ['server_id' => 999999, 'changes' => ['referral' => 'Juriste'], 'base' => ['referral' => null]]],
    ];

    $response = syncPush($items)->assertOk()
        ->assertJsonPath('meta.created', 2)->assertJsonPath('meta.rejected', 6);

    expect(array_column($response->json('data.items'), 'uuid'))->toBe(array_column($items, 'uuid'))
        ->and($response->json('data.items.0'))->toMatchArray(['result' => 'rejected', 'status' => 422, 'code' => 'validation_failed'])
        ->and($response->json('data.items.0.errors'))->toHaveKey('description')
        ->and($response->json('data.items.1'))->toMatchArray(['status' => 422, 'code' => 'unsupported_type'])
        ->and($response->json('data.items.2'))->toMatchArray(['status' => 422, 'code' => 'anonymous_not_allowed'])
        ->and($response->json('data.items.3'))->toMatchArray(['result' => 'created', 'status' => 201])
        ->and($response->json('data.items.4'))->toMatchArray(['status' => 404, 'code' => 'not_found'])
        ->and($response->json('data.items.5'))->toMatchArray(['result' => 'created', 'status' => 201])
        ->and($response->json('data.items.6'))->toMatchArray(['result' => 'rejected', 'status' => 422, 'code' => 'validation_failed'])
        ->and($response->json('data.items.6.errors'))->toHaveKeys(['occurred_on', 'zone_id'])
        ->and($response->json('data.items.7'))->toMatchArray(['result' => 'rejected', 'status' => 404, 'code' => 'not_found']);

    $sos = Alert::query()->where('type', AlertType::Sos->value)->sole();
    $report = Alert::query()->where('type', AlertType::Report->value)->sole();

    expect($sos->idempotency_key)->toBe($sosUuid)
        ->and($sos->contact)->toBeNull()
        ->and($sos->description)->toBe('Aide-moi')
        ->and($sos->user_id)->toBe($this->pair->id)
        ->and($sos->id)->toBeLessThan($report->id)
        ->and(CaseRecord::query()->count())->toBe(0);

    // Reçus : créés et refus définitifs ; jamais pour l'anonyme ni le type inconnu.
    expect(SyncOutboxReceipt::query()->pluck('result', 'idempotency_key')->all())->toEqualCanonicalizing([
        $items[0]['uuid'] => 'rejected',
        $items[3]['uuid'] => 'created',
        $items[4]['uuid'] => 'rejected',
        $sosUuid => 'created',
        $items[6]['uuid'] => 'rejected',
        $items[7]['uuid'] => 'rejected',
    ]);

    // Un élément refusé repart (référentiel corrigé, nouvel essai) ; même uuid avec un autre contenu : refusé.
    syncPush([syncReportItem([], $items[0]['uuid'])])->assertOk()->assertJsonPath('data.items.0.code', 'uuid_reused');
});

it('envoie un message hors connexion par client_uuid, sans doublon au rejeu', function (): void {
    $other = MessagingFixtures::member();
    $conversation = MessagingFixtures::privateConversation($this->pair, $other);
    $item = ['uuid' => (string) Str::uuid(), 'type' => 'message', 'payload' => ['conversation' => $conversation->ulid, 'body' => 'Je serai là demain.']];

    $first = syncPush([$item])->assertOk()->assertJsonPath('data.items.0.result', 'created');
    $message = Message::query()->sole();

    expect($first->json('data.items.0.server_id'))->toBe($message->id)
        ->and($message->client_uuid)->toBe($item['uuid'])
        ->and($message->body)->toBe('Je serai là demain.');

    syncPush([$item])->assertOk()->assertJsonPath('data.items.0.result', 'duplicate')->assertJsonPath('data.items.0.server_id', $message->id);
    expect(Message::query()->count())->toBe(1);
});

it('refuse un message d’un compte au e-mail non confirmé (403 email_unverified), le reste du lot passe', function (): void {
    $this->pair->forceFill(['email_verified_at' => null])->save();
    $other = MessagingFixtures::member();
    $conversation = MessagingFixtures::privateConversation($other, MessagingFixtures::member());
    Sanctum::actingAs($this->pair);

    syncPush([
        ['uuid' => (string) Str::uuid(), 'type' => 'message', 'payload' => ['conversation' => $conversation->ulid, 'body' => 'Coucou']],
        syncReportItem(),
    ])->assertOk()
        ->assertJsonPath('data.items.0.code', 'email_unverified')->assertJsonPath('data.items.0.status', 403)
        ->assertJsonPath('data.items.1.result', 'created');
});

it('crée un dossier collecté hors connexion (code attribué par le serveur), sans doublon au rejeu ; 403 sans cases.create', function (): void {
    $zone = AlertFixtures::zone('Littoral');
    $item = ['uuid' => (string) Str::uuid(), 'type' => 'collecte', 'payload' => [
        'kind' => 'VBG',
        'occurred_on' => now()->subDays(3)->format('Y-m-d'),
        'zone_id' => $zone->id,
        'population_id' => Population::query()->where('is_active', true)->value('id'),
        'sex' => 'femme',
        'age_range' => '18-24',
        'violence_type_id' => ViolenceType::query()->where('is_active', true)->value('id'),
        'referral' => 'Juriste',
        'notes' => 'Orientée vers la juriste après un premier échange.',
    ]];

    $first = syncPush([$item])->assertOk()->assertJsonPath('data.items.0.result', 'created')->assertJsonPath('data.items.0.status', 201);
    $case = CaseRecord::query()->sole();

    expect($first->json('data.items.0.server_id'))->toBe($case->id)
        ->and($first->json('data.items.0.reference'))->toBe($case->code)
        ->and($case->code)->toStartWith('VBG-')
        ->and($case->client_uuid)->toBe($item['uuid'])
        ->and($case->collected_by)->toBe($this->pair->id)
        ->and($case->device_id)->toBe($this->device->id)
        ->and($case->source)->toBe(AlertSource::SyncOffline)
        ->and(SyncOutboxReceipt::query()->sole()->kind)->toBe('case');

    syncPush([$item])->assertOk()->assertJsonPath('data.items.0.result', 'duplicate')->assertJsonPath('data.items.0.reference', $case->code);
    expect(CaseRecord::query()->count())->toBe(1);

    $member = AlertFixtures::member();
    $memberDevice = SyncDevice::factory()->create(['user_id' => $member->id]);
    Sanctum::actingAs($member);
    syncPush([['uuid' => (string) Str::uuid(), 'type' => 'collecte', 'payload' => $item['payload']]], device: $memberDevice)
        ->assertOk()->assertJsonPath('data.items.0.status', 403)->assertJsonPath('data.items.0.code', 'forbidden');
});

it('fusionne une modification de dossier : serveur gagne le statut, l’autrice ses notes, conflit 409 enregistré sinon', function (): void {
    $admin = AlertFixtures::admin();
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['referral' => 'Point focal', 'notes' => 'Première écoute faite au centre.', 'status' => CaseStatus::Open]);

    // Pendant que l'appareil est hors ligne, l'équipe change l'orientation et le statut.
    $case->forceFill(['referral' => 'Juriste', 'status' => CaseStatus::InProgress])->save();

    $item = syncCaseUpdate($case,
        ['referral' => 'Psychologue', 'status' => 'clos', 'notes' => 'Deuxième rencontre, orientation vers une psychologue.'],
        ['referral' => 'Point focal', 'status' => 'ouvert', 'notes' => 'Première écoute faite au centre.'],
    );

    $response = syncPush([$item])->assertOk()
        ->assertJsonPath('meta.conflicts', 1)
        ->assertJsonPath('data.items.0.result', 'conflict')
        ->assertJsonPath('data.items.0.status', 409)
        ->assertJsonPath('data.items.0.code', 'conflict')
        ->assertJsonPath('data.items.0.server_id', $case->id)
        ->assertJsonPath('data.items.0.reference', $case->code)
        ->assertJsonPath('data.items.0.applied', ['notes'])
        ->assertJsonPath('data.items.0.kept', ['status']);

    $conflict = SyncConflict::query()->sole();
    $case->refresh();

    expect($response->json('data.items.0.conflicts'))->toBe([['field' => 'referral', 'conflict_id' => $conflict->id]])
        ->and($case->referral)->toBe('Juriste')
        ->and($case->status)->toBe(CaseStatus::InProgress)
        ->and($case->notes)->toBe('Deuxième rencontre, orientation vers une psychologue.')
        ->and($conflict->status)->toBe(ConflictStatus::Open)
        ->and($conflict->device_id)->toBe($this->device->id)
        ->and($conflict->server_value)->toBe('Juriste')
        ->and($conflict->device_value)->toBe('Psychologue')
        ->and(DB::table('sync_conflicts')->value('device_value'))->not->toContain('Psychologue')
        ->and(Crypt::decryptString(DB::table('sync_conflicts')->value('device_value')))->toBe('Psychologue')
        ->and($this->device->fresh()->conflicts_count)->toBe(1)
        ->and(SyncOutboxReceipt::query()->sole()->result)->toBe('conflict');

    Notification::assertSentTo($admin, SyncConflictNotification::class);

    // Conflits de l'appareil, valeurs servies à la collectrice du dossier.
    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->getJson('/api/v1/sync/conflicts?status=open')
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('meta.open', 1)
        ->assertJsonPath('data.0.id', $conflict->id)
        ->assertJsonPath('data.0.subject', ['type' => 'collecte', 'server_id' => $case->id, 'reference' => $case->code])
        ->assertJsonPath('data.0.field', 'referral')
        ->assertJsonPath('data.0.device_value', 'Psychologue');

    // État courant du dossier rendu avec le résultat : l'appareil reprend la valeur du serveur.
    expect($response->json('data.items.0.case'))->toMatchArray(['id' => $case->id, 'code' => $case->code, 'referral' => 'Juriste', 'status' => 'en_cours']);

    // Rejeu de l'élément dans un nouveau lot : la réponse d'origine (409, conflits relus), aucun second conflit.
    syncPush([$item])->assertOk()
        ->assertJsonPath('meta.conflicts', 1)
        ->assertJsonPath('data.items.0.result', 'conflict')
        ->assertJsonPath('data.items.0.status', 409)
        ->assertJsonPath('data.items.0.conflicts', [['field' => 'referral', 'conflict_id' => $conflict->id]])
        ->assertJsonPath('data.items.0.case.referral', 'Juriste');
    expect(SyncConflict::query()->count())->toBe(1);
});

it('applique une modification sans changement concurrent (updated) et ferme le dossier', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['referral' => 'Point focal', 'status' => CaseStatus::InProgress]);

    syncPush([syncCaseUpdate($case, ['referral' => 'Hébergement', 'status' => 'clos'], ['referral' => 'Point focal', 'status' => 'en_cours'])])
        ->assertOk()->assertJsonPath('data.items.0.result', 'updated')->assertJsonPath('data.items.0.status', 200)
        ->assertJsonPath('data.items.0.applied', ['referral', 'status'])->assertJsonPath('data.items.0.kept', []);

    $case->refresh();
    expect($case->referral)->toBe('Hébergement')->and($case->status)->toBe(CaseStatus::Closed)->and($case->closed_at)->not->toBeNull()
        ->and(SyncConflict::query()->count())->toBe(0);
});

it('rend 404 neutre pour le dossier d’une autre personne et refuse un champ verrouillé ou sans valeur d’origine', function (): void {
    $foreign = CaseRecord::factory()->create(['referral' => 'Juriste']);
    $mine = CaseRecord::factory()->collectedBy($this->pair)->create();

    syncPush([
        syncCaseUpdate($foreign, ['referral' => 'Psychologue'], ['referral' => 'Juriste']),
        syncCaseUpdate($mine, ['collected_by' => 1], ['collected_by' => $this->pair->id]),
        syncCaseUpdate($mine, ['referral' => 'Psychologue'], []),
    ])->assertOk()
        ->assertJsonPath('data.items.0.status', 404)->assertJsonPath('data.items.0.code', 'not_found')
        ->assertJsonPath('data.items.0.server_id', null)
        ->assertJsonPath('data.items.1.code', 'validation_failed')
        ->assertJsonPath('data.items.2.code', 'validation_failed');

    expect($foreign->fresh()->referral)->toBe('Juriste')
        ->and(SyncConflict::query()->count())->toBe(0);
});

it('refuse un lot de plus de 50 éléments ou des uuid en double (422, rien n’est créé)', function (): void {
    syncPush(array_map(static fn (): array => syncReportItem(), range(1, 51)))->assertUnprocessable()->assertJsonValidationErrors('items');

    $uuid = (string) Str::uuid();
    syncPush([syncReportItem([], $uuid), syncReportItem([], strtoupper($uuid))])->assertUnprocessable();

    expect(Alert::query()->count())->toBe(0);
});

it('accepte sync/push et heartbeat pendant la maintenance, refuse sync/pull (RG-01)', function (): void {
    $this->seed(SettingsSeeder::class);
    Settings::set('maintenance', true);

    syncPush([['uuid' => (string) Str::uuid(), 'type' => 'sos', 'payload' => []]])->assertOk()->assertJsonPath('data.items.0.result', 'created');
    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->postJson('/api/v1/heartbeat')->assertOk();
    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->getJson('/api/v1/sync/pull')->assertStatus(503);
});

it('tient le signal de vie de l’appareil (heartbeat)', function (): void {
    $this->device->forceFill(['last_seen_at' => now()->subDay()])->save();
    SyncConflict::factory()->create(['device_id' => $this->device->id, 'status' => ConflictStatus::Open]);

    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->postJson('/api/v1/heartbeat', ['pending' => 4, 'app_version' => '1.6.0'])
        ->assertOk()->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.device.pending_count', 4)
        ->assertJsonPath('data.open_conflicts', 1);

    $device = $this->device->fresh();
    expect($device->pending_count)->toBe(4)->and($device->app_version)->toBe('1.6.0')->and($device->last_seen_at->isToday())->toBeTrue();

    $this->flushHeaders()->postJson('/api/v1/heartbeat')->assertForbidden()->assertJsonPath('code', 'device_required');
});

it('refuse la collecte d’un compte pair en attente de validation (RG-03) : création et modification 403, le SOS passe', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['referral' => 'Point focal']);
    $this->pair->forceFill(['status' => UserStatus::Pending])->save();
    Sanctum::actingAs($this->pair->fresh());

    syncPush([
        syncCaseCreate(),
        syncCaseUpdate($case, ['referral' => 'Juriste'], ['referral' => 'Point focal']),
        syncSosItem(),
    ])->assertOk()
        ->assertJsonPath('data.items.0.status', 403)->assertJsonPath('data.items.0.code', 'forbidden')
        ->assertJsonPath('data.items.1.status', 403)->assertJsonPath('data.items.1.code', 'forbidden')
        ->assertJsonPath('data.items.2.result', 'created');

    expect(CaseRecord::query()->count())->toBe(1)
        ->and($case->fresh()->referral)->toBe('Point focal')
        ->and(SyncConflict::query()->count())->toBe(0);
});

it('limite les lots (sync_push, 429) sauf un lot fait uniquement de SOS ; un SOS rejoué n’exempte pas le reste du lot', function (): void {
    config(['xomiton.rate_limits.sync_push_per_minute' => 1]);
    $sos = syncSosItem();

    syncPush([syncReportItem()])->assertOk()->assertJsonPath('data.items.0.result', 'created');
    syncPush([syncReportItem()])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');

    // Lot fait uniquement de SOS : jamais limité (RG-01, P2-08).
    syncPush([$sos])->assertOk()->assertJsonPath('data.items.0.result', 'created');
    syncPush([$sos, syncSosItem()])->assertOk()->assertJsonPath('data.items.0.result', 'duplicate')->assertJsonPath('data.items.1.result', 'created');

    // Le même SOS rejoué ne fait pas passer un signalement.
    syncPush([$sos, syncReportItem()])->assertStatus(429);

    expect(Alert::query()->where('type', AlertType::Report->value)->count())->toBe(1)
        ->and(Alert::query()->where('type', AlertType::Sos->value)->count())->toBe(2);
});

it('applique les quotas des routes dédiées à chaque élément (messages_write, report) : 429 sans reçu, compteur partagé', function (): void {
    $conversation = MessagingFixtures::privateConversation($this->pair, MessagingFixtures::member());
    // La création de la conversation (POST conversations, throttle:messages_write) compte déjà un envoi sur trois.
    config(['xomiton.rate_limits.messages_write_per_minute' => 3, 'xomiton.rate_limits.report_per_hour_per_ip' => 1]);
    $items = [
        syncMessageItem($conversation->ulid, 'Premier message.'),
        syncMessageItem($conversation->ulid, 'Deuxième message.'),
        syncMessageItem($conversation->ulid, 'Troisième message.'),
        syncReportItem(),
        syncReportItem(),
        syncSosItem(),
    ];

    $response = syncPush($items)->assertOk()->assertJsonPath('meta.created', 4)->assertJsonPath('meta.rejected', 2);

    expect(array_column($response->json('data.items'), 'status'))->toBe([201, 201, 429, 201, 429, 201])
        ->and($response->json('data.items.2'))->toMatchArray(['result' => 'rejected', 'code' => 'too_many_requests', 'message' => __('sync.items.too_many')])
        ->and($response->json('data.items.4.code'))->toBe('too_many_requests')
        ->and(Message::query()->count())->toBe(2)
        ->and(Alert::query()->where('type', AlertType::Report->value)->count())->toBe(1)
        ->and(SyncOutboxReceipt::query()->whereIn('idempotency_key', [$items[2]['uuid'], $items[4]['uuid']])->exists())->toBeFalse();

    // Même compteur que la route dédiée.
    $this->postJson("/api/v1/conversations/{$conversation->ulid}/messages", ['body' => 'Par la route dédiée.'])->assertStatus(429);
});

it('fait passer chaque SOS neuf de la synchronisation par la surveillance des rafales (P5-09), sans jamais le refuser', function (): void {
    config(['xomiton.rate_limits.sos_log_per_minute_per_ip' => 1]);
    $admin = AlertFixtures::admin();

    syncPush([syncSosItem(), syncSosItem(), syncSosItem()])->assertOk()->assertJsonPath('meta.created', 3);

    Notification::assertSentTo($admin, SosBurstDetected::class);
});

it('ne traite pendant la maintenance que SOS et signalements d’un compte hors administration (503 retry_later sans reçu)', function (): void {
    $conversation = MessagingFixtures::privateConversation($this->pair, MessagingFixtures::member());
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['referral' => 'Point focal']);
    $this->seed(SettingsSeeder::class);
    Settings::set('maintenance', true);
    $items = [
        syncMessageItem($conversation->ulid),
        syncCaseCreate(),
        syncCaseUpdate($case, ['referral' => 'Juriste'], ['referral' => 'Point focal']),
        syncReportItem(),
        syncSosItem(),
    ];

    $response = syncPush($items)->assertOk()->assertJsonPath('meta.created', 2)->assertJsonPath('meta.rejected', 3);

    foreach ([0, 1, 2] as $i) {
        expect($response->json("data.items.$i"))->toMatchArray(['result' => 'rejected', 'status' => 503, 'code' => 'retry_later', 'message' => __('sync.items.maintenance')]);
    }

    expect($response->json('data.items.3.result'))->toBe('created')
        ->and($response->json('data.items.4.result'))->toBe('created')
        ->and(Message::query()->count())->toBe(0)
        ->and(CaseRecord::query()->count())->toBe(1)
        ->and($case->fresh()->referral)->toBe('Point focal')
        ->and(SyncOutboxReceipt::query()->count())->toBe(2);

    // L'administration travaille normalement (RG-14).
    $admin = AlertFixtures::admin();
    $adminDevice = SyncDevice::factory()->create(['user_id' => $admin->id]);
    Sanctum::actingAs($admin);
    syncPush([syncCaseCreate()], device: $adminDevice)->assertOk()->assertJsonPath('data.items.0.result', 'created');
});

it('rend les éléments restants en 503 retry_later, sans reçu, une fois le budget de temps du lot épuisé', function (): void {
    config(['xomiton.sync.push_time_budget_seconds' => 0]);
    $items = [syncReportItem(), syncSosItem(), syncReportItem()];

    $response = syncPush($items)->assertOk()->assertJsonPath('meta.created', 1);

    expect(array_column($response->json('data.items'), 'status'))->toBe([503, 201, 503])
        ->and($response->json('data.items.0.code'))->toBe('retry_later')
        ->and(SyncOutboxReceipt::query()->pluck('idempotency_key')->all())->toBe([$items[1]['uuid']]);

    config(['xomiton.sync.push_time_budget_seconds' => 20]);
    syncPush([$items[0], $items[2]])->assertOk()->assertJsonPath('meta.created', 2);
});

it('répond 202 processing, jamais 429, quand le même lot est encore en cours de traitement', function (): void {
    config(['xomiton.idempotency.wait_seconds' => 0]);
    $batch = (string) Str::uuid();
    $items = [syncSosItem()];

    syncPush($items, $batch)->assertOk();
    IdempotencyKey::query()->update(['status_code' => null, 'response_body' => null]);

    syncPush($items, $batch)->assertStatus(202)
        ->assertHeader('Retry-After')
        ->assertJsonPath('data.status', 'processing');

    expect(Alert::query()->count())->toBe(1);
});

it('garde les notes de l’autrice modifiées des deux côtés, conflit pour un autre compte ; journal cases.updated source=sync', function (): void {
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['notes' => 'Première écoute faite au centre.']);
    $case->forceFill(['notes' => 'Note ajoutée par l’équipe au bureau.'])->save();

    syncPush([syncCaseUpdate($case, ['notes' => 'Deuxième rencontre faite à domicile.'], ['notes' => 'Première écoute faite au centre.'])])
        ->assertOk()->assertJsonPath('data.items.0.result', 'updated')
        ->assertJsonPath('data.items.0.applied', ['notes'])
        ->assertJsonPath('data.items.0.case.notes', 'Deuxième rencontre faite à domicile.');

    $audit = AuditLog::query()->where('action', 'cases.updated')->sole();
    expect($case->fresh()->notes)->toBe('Deuxième rencontre faite à domicile.')
        ->and($audit->detail)->toBe('code='.$case->code.' ; champs=notes ; source=sync')
        ->and($audit->detail)->not->toContain('Deuxième')
        ->and($audit->user_id)->toBe($this->pair->id);

    // Appareil de l'administration (qui n'a pas collecté le dossier) : les notes modifiées des deux côtés sont en conflit.
    $admin = AlertFixtures::admin();
    $adminDevice = SyncDevice::factory()->create(['user_id' => $admin->id]);
    $case->forceFill(['notes' => 'Note corrigée par la coordination.'])->save();
    Sanctum::actingAs($admin);

    syncPush([syncCaseUpdate($case, ['notes' => 'Note de l’administration hors ligne.'], ['notes' => 'Deuxième rencontre faite à domicile.'])], device: $adminDevice)
        ->assertOk()->assertJsonPath('data.items.0.result', 'conflict')
        ->assertJsonPath('data.items.0.conflicts.0.field', 'notes')
        ->assertJsonPath('data.items.0.applied', []);

    expect($case->fresh()->notes)->toBe('Note corrigée par la coordination.');
});

it('contrôle la commune d’une modification selon le catalogue (commune_in_zone activable), comme PATCH pair/cases', function (): void {
    $this->seed(ValidationRulesSeeder::class);
    $case = CaseRecord::factory()->collectedBy($this->pair)->create(['zone_id' => AlertFixtures::zone('Littoral')->id, 'commune_id' => null]);
    $elsewhere = Commune::query()->where('zone_id', AlertFixtures::zone('Zou')->id)->where('is_active', true)->orderBy('position')->firstOrFail();

    $refused = syncPush([syncCaseUpdate($case, ['commune_id' => $elsewhere->id], ['commune_id' => null])])->assertOk()
        ->assertJsonPath('data.items.0.code', 'validation_failed');
    expect($refused->json('data.items.0.errors'))->toHaveKey('changes.commune_id');

    ValidationRule::query()->where('key', 'commune_in_zone')->update(['is_active' => false]);

    syncPush([syncCaseUpdate($case, ['commune_id' => $elsewhere->id], ['commune_id' => null])])->assertOk()
        ->assertJsonPath('data.items.0.result', 'updated');
    expect($case->fresh()->commune_id)->toBe($elsewhere->id);
});

it('lit chaque élément sur une requête dérivée sans toucher au corps du lot (sacs d’entrée propres)', function (): void {
    $items = [syncReportItem()];
    $request = Request::create('/api/v1/sync/push', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: (string) json_encode(['items' => $items]));
    $request->setUserResolver(fn () => $this->pair);

    $data = (new SyncItemDecoder($request, app(CaseValidator::class)))
        ->report(new PushItem(0, $items[0]['uuid'], 'signalement', PushItem::OP_CREATE, $items[0]['payload']), $this->device->id);

    expect($data->description)->toBe($items[0]['payload']['description'])
        ->and($request->input('items'))->toBe($items)
        ->and($request->has('description'))->toBeFalse()
        ->and($request->has('idempotency_key'))->toBeFalse();
});
