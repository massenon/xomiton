<?php

declare(strict_types=1);

use App\Models\MailLog;
use Illuminate\Support\Carbon;

/*
 * `mail:purge-logs` (P1-14) : journaux d'envoi de plus de 180 jours supprimés.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
});

it('supprime les journaux de plus de 180 jours et conserve les récents', function (): void {
    MailLog::factory()->count(2)->expired()->create();
    $boundary = MailLog::factory()->create(['created_at' => now()->subDays(MailLog::RETENTION_DAYS)->addMinute()]);
    $recent = MailLog::factory()->create(['created_at' => now()->subDays(10)]);

    $this->artisan('mail:purge-logs')
        ->expectsOutputToContain('2 journaux d’envoi purgés')
        ->assertSuccessful();

    expect(MailLog::query()->count())->toBe(2)
        ->and(MailLog::query()->whereKey($boundary->id)->exists())->toBeTrue()
        ->and(MailLog::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('est idempotente', function (): void {
    MailLog::factory()->expired()->create();

    $this->artisan('mail:purge-logs')->expectsOutputToContain('1 journal d’envoi purgé')->assertSuccessful();
    $this->artisan('mail:purge-logs')->expectsOutputToContain('0 journal d’envoi purgé')->assertSuccessful();
});
