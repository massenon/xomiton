<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Support\Settings;
use Illuminate\Support\Carbon;

/*
 * `audit:purge` (P1-14) : rétention `xomiton.retention.audit_log_days`, surchargée par le paramètre
 * d'administration `audit_log_days` (P13-05) puis par l'option --days.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    config(['xomiton.retention.audit_log_days' => 90]);
});

it('supprime les entrées au-delà de la rétention par défaut (90 jours)', function (): void {
    AuditLog::factory()->count(2)->expired()->create();
    $kept = AuditLog::factory()->create(['created_at' => now()->subDays(89)]);

    $this->artisan('audit:purge')
        ->expectsOutputToContain('2 entrées du journal d’audit purgées (plus de 90 jours)')
        ->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->whereKey($kept->id)->exists())->toBeTrue();
});

it('applique le paramètre d’administration audit_log_days puis l’option --days', function (): void {
    AuditLog::factory()->create(['created_at' => now()->subDays(45)]);
    AuditLog::factory()->create(['created_at' => now()->subDays(25)]);
    AuditLog::factory()->create(['created_at' => now()->subDays(5)]);

    Settings::set('audit_log_days', 30);

    $this->artisan('audit:purge')
        ->expectsOutputToContain('1 entrée du journal d’audit purgée (plus de 30 jours)')
        ->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(2);

    $this->artisan('audit:purge', ['--days' => 20])
        ->expectsOutputToContain('1 entrée du journal d’audit purgée (plus de 20 jours)')
        ->assertSuccessful();

    expect(AuditLog::query()->count())->toBe(1);
});

it('refuse une rétention hors bornes ou non entière sans rien supprimer', function (): void {
    AuditLog::factory()->create(['created_at' => now()->subDays(5)]);

    $this->artisan('audit:purge', ['--days' => 3])->assertFailed();
    $this->artisan('audit:purge', ['--days' => 5000])->assertFailed();
    $this->artisan('audit:purge', ['--days' => 'trente'])->assertFailed();

    expect(AuditLog::query()->count())->toBe(1);
});

it('est idempotente', function (): void {
    AuditLog::factory()->expired()->create();

    $this->artisan('audit:purge')->expectsOutputToContain('1 entrée du journal d’audit purgée')->assertSuccessful();
    $this->artisan('audit:purge')->expectsOutputToContain('0 entrée du journal d’audit purgée')->assertSuccessful();
});
