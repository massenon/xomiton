<?php

declare(strict_types=1);

use App\Models\OtpCode;
use Illuminate\Support\Carbon;

/*
 * `otp:purge` (P1-14) : codes expirés ou consommés depuis plus de 24 h supprimés, codes récents conservés.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
});

it('purge les codes expirés ou consommés depuis plus de 24 h et garde les autres', function (): void {
    $staleExpired = OtpCode::factory()->create(['expires_at' => now()->subHours(25)]);
    $staleConsumed = OtpCode::factory()->create(['expires_at' => now()->addMinutes(5), 'consumed_at' => now()->subHours(30)]);
    $recentlyExpired = OtpCode::factory()->create(['expires_at' => now()->subHours(2)]);
    $recentlyConsumed = OtpCode::factory()->create(['consumed_at' => now()->subHour()]);
    $valid = OtpCode::factory()->create();

    $this->artisan('otp:purge')
        ->expectsOutputToContain('2 codes OTP purgés')
        ->assertSuccessful();

    expect(OtpCode::query()->whereKey($staleExpired->id)->exists())->toBeFalse()
        ->and(OtpCode::query()->whereKey($staleConsumed->id)->exists())->toBeFalse()
        ->and(OtpCode::query()->whereKey($recentlyExpired->id)->exists())->toBeTrue()
        ->and(OtpCode::query()->whereKey($recentlyConsumed->id)->exists())->toBeTrue()
        ->and(OtpCode::query()->whereKey($valid->id)->exists())->toBeTrue();
});

it('est idempotente', function (): void {
    OtpCode::factory()->count(3)->create(['expires_at' => now()->subDays(2)]);

    $this->artisan('otp:purge')->expectsOutputToContain('3 codes OTP purgés')->assertSuccessful();
    $this->artisan('otp:purge')->expectsOutputToContain('0 code OTP purgé')->assertSuccessful();

    expect(OtpCode::query()->count())->toBe(0);
});

it('respecte la durée configurée xomiton.retention.otp_hours', function (): void {
    config(['xomiton.retention.otp_hours' => 48]);
    OtpCode::factory()->create(['expires_at' => now()->subHours(30)]);

    $this->artisan('otp:purge')->expectsOutputToContain('0 code OTP purgé')->assertSuccessful();

    expect(OtpCode::query()->count())->toBe(1);
});
