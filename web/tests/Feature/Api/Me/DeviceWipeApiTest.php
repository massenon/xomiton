<?php

declare(strict_types=1);

use App\Application\Account\DeviceRegistry;
use App\Application\Admin\Mobile\StepUp;
use App\Application\Admin\Roles\ManagePeerRole;
use App\Application\Sync\Admin\RequestDeviceWipe;
use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;
use Tests\Support\FeedFixtures as F;

/*
 * Effacement à distance d'un appareil (P15-11, volet serveur) : permission `device.wipe` (admin, superadmin), action
 * admin (`admin/devices/{id}/wipe`, step-up) et écran Livewire, ordre lu par l'appareil (`GET me` → device.wipe,
 * `heartbeat` → wipe), accusé `POST me/devices/{uuid}/wiped` (idempotent), 403 `device_revoked` pour toute requête
 * authentifiée de l'appareil révoqué (sauf les appels qui lui permettent de se mettre en règle), retrait du rôle pair.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
    Notification::fake();

    $this->pair = AlertFixtures::member('pair');
    $this->device = SyncDevice::factory()->create(['user_id' => $this->pair->id]);
    $this->admin = F::member('admin');
    $this->admin->syncRoles(['membre', 'admin']);
});

function wipeAsAdmin(SyncDevice $device, ?User $admin = null, bool $stepUp = true): Illuminate\Testing\TestResponse
{
    $admin ??= test()->admin;
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    if ($stepUp) {
        $headers[StepUp::HEADER] = app(StepUp::class)->issue($admin, 'password')['token'];
    }

    return F::as($admin)->withHeaders($headers)->postJson('/api/v1/admin/devices/'.$device->getKey().'/wipe');
}

function asDevice(User $user, SyncDevice $device): Tests\TestCase
{
    Sanctum::actingAs($user);

    return test()->flushHeaders()->withHeader(DeviceRegistry::HEADER, $device->device_uuid);
}

it('donne device.wipe à l’admin et au superadmin seulement', function (): void {
    expect($this->admin->can('device.wipe'))->toBeTrue()
        ->and(F::member('superadmin')->can('device.wipe'))->toBeTrue()
        ->and($this->pair->can('device.wipe'))->toBeFalse()
        ->and(F::member('moderateur')->can('device.wipe'))->toBeFalse()
        ->and(F::member('membre')->can('device.wipe'))->toBeFalse();
});

it('marque l’appareil à effacer, le révoque et supprime ses abonnements push (admin, step-up)', function (): void {
    PushSubscription::factory()->create(['user_id' => $this->pair->id, 'sync_device_id' => $this->device->id, 'kind' => 'fcm']);

    wipeAsAdmin($this->device)->assertStatus(202)
        ->assertJsonPath('data.wipe_pending', true)
        ->assertJsonPath('data.wipe_reason', 'admin')
        ->assertJsonPath('data.revoked', true)
        ->assertJsonMissingPath('data.device_uuid');

    $this->device->refresh();
    expect($this->device->is_revoked)->toBeTrue()
        ->and($this->device->wipe_requested_at)->not->toBeNull()
        ->and($this->device->wiped_at)->toBeNull()
        ->and(PushSubscription::query()->where('sync_device_id', $this->device->id)->count())->toBe(0);

    $log = AuditLog::query()->where('action', 'device.wipe_requested')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($this->admin->id)
        ->and($log->detail)->toContain($this->device->label)->toContain('source=mobile')->not->toContain($this->device->device_uuid);
});

it('est idempotent : un second ordre garde la date et le motif d’origine', function (): void {
    wipeAsAdmin($this->device)->assertStatus(202);
    $first = $this->device->refresh()->wipe_requested_at;
    $this->travel(5)->minutes();

    wipeAsAdmin($this->device)->assertOk()->assertJsonPath('meta.already_requested', true);

    expect($this->device->refresh()->wipe_requested_at->equalTo($first))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'device.wipe_requested')->count())->toBe(1);
});

it('exige le step-up et la permission, et refuse (403) membre, pair, modérateur et admin sans la permission', function (): void {
    wipeAsAdmin($this->device, stepUp: false)->assertForbidden()->assertJsonPath('code', 'step_up_required');

    foreach (['membre', 'pair', 'moderateur'] as $role) {
        $user = F::member($role);
        F::as($user)->withHeader('Idempotency-Key', (string) Str::uuid())->postJson('/api/v1/admin/devices/'.$this->device->id.'/wipe')->assertForbidden();
        F::as($user)->getJson('/api/v1/admin/members/'.$this->pair->id.'/devices')->assertForbidden();
    }

    $stripped = F::member('admin');
    $stripped->syncRoles(['membre', 'admin']);
    $stripped->roles->first(fn ($r) => $r->name === 'admin')?->revokePermissionTo('device.wipe');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    wipeAsAdmin($this->device, $stripped)->assertForbidden();

    expect($this->device->refresh()->wipe_requested_at)->toBeNull();
});

it('liste les appareils d’un compte sans jamais exposer l’UUID', function (): void {
    F::as($this->admin)->getJson('/api/v1/admin/members/'.$this->pair->id.'/devices')
        ->assertOk()
        ->assertJsonPath('data.0.id', $this->device->id)
        ->assertJsonPath('data.0.can.wipe', true)
        ->assertJsonMissingPath('data.0.device_uuid')
        ->assertDontSee($this->device->device_uuid);
});

it('fait lire l’ordre à l’appareil par GET me et heartbeat, sans autre donnée', function (): void {
    wipeAsAdmin($this->device)->assertStatus(202);

    asDevice($this->pair, $this->device)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.device.revoked', true)
        ->assertJsonPath('data.device.wipe.reason', 'admin')
        ->assertJsonStructure(['data' => ['device' => ['wipe' => ['requested_at', 'reason']]]]);

    asDevice($this->pair, $this->device)->postJson('/api/v1/heartbeat')->assertOk()
        ->assertJsonPath('data.wipe.reason', 'admin')
        ->assertJsonMissingPath('data.device')
        ->assertJsonMissingPath('data.open_conflicts');
});

it('ne porte aucun ordre pour un appareil actif', function (): void {
    asDevice($this->pair, $this->device)->getJson('/api/v1/me')->assertOk()
        ->assertJsonPath('data.device.wipe', null)->assertJsonPath('data.device.revoked', false);

    asDevice($this->pair, $this->device)->postJson('/api/v1/heartbeat')->assertOk()->assertJsonPath('data.wipe', null)
        ->assertJsonStructure(['data' => ['rules_updated_at', 'device' => ['device_uuid']]]);

    Sanctum::actingAs($this->pair);
    $this->flushHeaders()->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.device', null);
});

it('répond 403 device_revoked à tout appel authentifié de l’appareil effacé, sauf ceux qui le mettent en règle', function (): void {
    wipeAsAdmin($this->device)->assertStatus(202);

    foreach (['/api/v1/notifications', '/api/v1/dashboard', '/api/v1/sync/pull', '/api/v1/pair/cases', '/api/v1/pair/alerts', '/api/v1/feed', '/api/v1/me/settings'] as $uri) {
        asDevice($this->pair, $this->device)->getJson($uri)->assertForbidden()->assertJsonPath('code', 'device_revoked');
    }

    asDevice($this->pair, $this->device)->postJson('/api/v1/sync/push', ['items' => []], ['Idempotency-Key' => (string) Str::uuid()])
        ->assertForbidden()->assertJsonPath('code', 'device_revoked');

    // L'appareil se met en règle : retrait du push, accusé.
    asDevice($this->pair, $this->device)->deleteJson('/api/v1/me/push-subscriptions', ['token' => 'x'])->assertNoContent();
    asDevice($this->pair, $this->device)->postJson('/api/v1/me/devices/'.$this->device->device_uuid.'/wiped')->assertOk();

    // Sans l'en-tête, la requête n'est pas rattachée à l'appareil : le jeton de la personne reste valable.
    Sanctum::actingAs($this->pair);
    $this->flushHeaders()->getJson('/api/v1/notifications')->assertOk();
});

it('répond 403 device_revoked à un appareil révoqué sans ordre d’effacement, même pour GET me et heartbeat', function (): void {
    $revoked = SyncDevice::factory()->revoked()->create(['user_id' => $this->pair->id]);

    asDevice($this->pair, $revoked)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'device_revoked');
    asDevice($this->pair, $revoked)->postJson('/api/v1/heartbeat')->assertForbidden()->assertJsonPath('code', 'device_revoked');
    asDevice($this->pair, $revoked)->postJson('/api/v1/me/devices/'.$revoked->device_uuid.'/wiped')->assertStatus(422)->assertJsonPath('code', 'wipe_not_requested');
});

it('ne bloque jamais le SOS ni le signalement, même avec l’en-tête d’un appareil effacé', function (): void {
    wipeAsAdmin($this->device)->assertStatus(202);
    Sanctum::actingAs($this->pair);

    $this->flushHeaders()->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->getJson('/api/v1/urgences')->assertOk();
    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->getJson('/api/v1/status')->assertOk();
});

it('accuse l’effacement une seule fois (idempotent) et le journalise', function (): void {
    wipeAsAdmin($this->device)->assertStatus(202);

    asDevice($this->pair, $this->device)->postJson('/api/v1/me/devices/'.$this->device->device_uuid.'/wiped')->assertOk()
        ->assertJsonPath('data.wipe', null)
        ->assertJsonPath('data.revoked', true);
    $wipedAt = $this->device->refresh()->wiped_at;
    expect($wipedAt)->not->toBeNull();

    $this->travel(2)->minutes();
    asDevice($this->pair, $this->device)->postJson('/api/v1/me/devices/'.$this->device->device_uuid.'/wiped')->assertOk();

    expect($this->device->refresh()->wiped_at->equalTo($wipedAt))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'device.wiped')->count())->toBe(1);

    // L'ordre n'est plus visible : plus de lecture possible (appareil révoqué sans ordre en attente).
    asDevice($this->pair, $this->device)->getJson('/api/v1/me')->assertForbidden()->assertJsonPath('code', 'device_revoked');
});

it('refuse l’accusé sans ordre (422) et pour l’appareil d’un autre compte (404)', function (): void {
    Sanctum::actingAs($this->pair);
    $this->flushHeaders()->postJson('/api/v1/me/devices/'.$this->device->device_uuid.'/wiped')->assertStatus(422)->assertJsonPath('code', 'wipe_not_requested');

    $other = SyncDevice::factory()->create();
    wipeAsAdmin($other)->assertStatus(202);
    $this->postJson('/api/v1/me/devices/'.$other->device_uuid.'/wiped')->assertNotFound();
    expect($other->refresh()->wiped_at)->toBeNull();

    $this->postJson('/api/v1/me/devices/'.(string) Str::uuid().'/wiped')->assertNotFound();
});

it('exige un compte connecté pour l’accusé', function (): void {
    $this->postJson('/api/v1/me/devices/'.$this->device->device_uuid.'/wiped')->assertUnauthorized();
});

it('marque à effacer les appareils actifs au retrait du rôle pair (API admin mobile)', function (): void {
    $second = SyncDevice::factory()->create(['user_id' => $this->pair->id]);
    $already = SyncDevice::factory()->revoked()->create(['user_id' => $this->pair->id]);
    $stranger = SyncDevice::factory()->create();

    F::as($this->admin)->withHeaders([
        'Idempotency-Key' => (string) Str::uuid(),
        StepUp::HEADER => app(StepUp::class)->issue($this->admin, 'password')['token'],
    ])->deleteJson('/api/v1/admin/members/'.$this->pair->id.'/roles/peer', ['reason' => 'Fin de mission'])->assertOk();

    expect($this->pair->fresh()->hasRole('pair'))->toBeFalse()
        ->and($this->device->refresh()->wipe_reason)->toBe(RequestDeviceWipe::REASON_PEER_ROLE_REMOVED)
        ->and($this->device->is_revoked)->toBeTrue()
        ->and($second->refresh()->wipePending())->toBeTrue()
        ->and($already->refresh()->wipe_requested_at)->toBeNull()
        ->and($stranger->refresh()->wipe_requested_at)->toBeNull()
        ->and(AuditLog::query()->where('action', 'device.wipe_requested')->count())->toBe(2);
});

it('marque à effacer au passage pair → membre (PUT admin/members/{id}/role) mais pas quand le rôle pair est conservé', function (): void {
    $keeps = AlertFixtures::member('pair');
    $keepsDevice = SyncDevice::factory()->create(['user_id' => $keeps->id]);

    F::as($this->admin)->withHeaders([
        'Idempotency-Key' => (string) Str::uuid(),
        StepUp::HEADER => app(StepUp::class)->issue($this->admin, 'password')['token'],
    ])->putJson('/api/v1/admin/members/'.$this->pair->id.'/role', ['role' => 'membre'])->assertOk();

    expect($this->device->refresh()->wipePending())->toBeTrue();

    // Accorder le rôle pair (sans le retirer) ne marque rien.
    app(ManagePeerRole::class)->apply($keeps, $this->admin);
    expect($keepsDevice->refresh()->wipe_requested_at)->toBeNull();
});
