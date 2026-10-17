<?php

declare(strict_types=1);

use App\Models\SyncDevice;
use App\Models\SyncOutboxReceipt;
use Illuminate\Support\Carbon;

/*
 * `sync:prune` (P1-16) : reçus de plus de 90 jours supprimés, appareils sans contact depuis 180 jours révoqués.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    config(['xomiton.retention.sync_receipt_days' => 90, 'xomiton.retention.device_inactive_days' => 180]);
});

it('supprime les reçus anciens et révoque les appareils inactifs sans toucher aux autres', function (): void {
    $active = SyncDevice::factory()->create(['last_seen_at' => now()->subDays(10)]);
    $inactive = SyncDevice::factory()->create(['last_seen_at' => now()->subDays(181)]);
    $neverSeenOld = SyncDevice::factory()->create(['last_seen_at' => null, 'created_at' => now()->subDays(200)]);
    $neverSeenRecent = SyncDevice::factory()->create(['last_seen_at' => null, 'created_at' => now()->subDays(5)]);
    $alreadyRevoked = SyncDevice::factory()->revoked()->create(['last_seen_at' => now()->subDays(400)]);

    $old = SyncOutboxReceipt::factory()->forDevice($active)->create(['created_at' => now()->subDays(91)]);
    $recent = SyncOutboxReceipt::factory()->forDevice($active)->create(['created_at' => now()->subDays(89)]);
    $oldOnInactive = SyncOutboxReceipt::factory()->forDevice($inactive)->create(['created_at' => now()->subDays(300)]);

    $this->artisan('sync:prune')
        ->expectsOutputToContain('2 reçus de synchronisation supprimés')
        ->expectsOutputToContain('2 appareils révoqués')
        ->assertSuccessful();

    expect(SyncOutboxReceipt::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(SyncOutboxReceipt::query()->whereKey($oldOnInactive->id)->exists())->toBeFalse()
        ->and(SyncOutboxReceipt::query()->whereKey($recent->id)->exists())->toBeTrue()
        ->and($active->fresh()->is_revoked)->toBeFalse()
        ->and($inactive->fresh()->is_revoked)->toBeTrue()
        ->and($neverSeenOld->fresh()->is_revoked)->toBeTrue()
        ->and($neverSeenRecent->fresh()->is_revoked)->toBeFalse()
        ->and($alreadyRevoked->fresh()->is_revoked)->toBeTrue()
        ->and(SyncDevice::query()->count())->toBe(5);
});

it('est idempotente', function (): void {
    SyncDevice::factory()->create(['last_seen_at' => now()->subDays(200)]);
    SyncOutboxReceipt::factory()->create(['created_at' => now()->subDays(100)]);

    $this->artisan('sync:prune')
        ->expectsOutputToContain('1 reçu de synchronisation supprimé')
        ->expectsOutputToContain('1 appareil révoqué')
        ->assertSuccessful();

    $this->artisan('sync:prune')
        ->expectsOutputToContain('0 reçu de synchronisation supprimé')
        ->expectsOutputToContain('0 appareil révoqué')
        ->assertSuccessful();
});
