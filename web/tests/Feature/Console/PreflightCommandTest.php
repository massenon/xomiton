<?php

declare(strict_types=1);

use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Console\Commands\Alerts\RetryNotificationsCommand;
use Illuminate\Support\Facades\Cache;

/*
 * `xomiton:preflight` (P17-02, RUNBOOK §1) : test de fumée de production.
 */

function productionLikeSetup(): void
{
    $public = sys_get_temp_dir().'/xm-preflight-'.bin2hex(random_bytes(4));
    mkdir($public.'/build', 0775, true);
    file_put_contents($public.'/build/manifest.json', '{}');
    app()->usePublicPath($public);
    app()->detectEnvironment(fn (): string => 'production');

    config([
        'app.debug' => false,
        'app.url' => 'https://xomiton.example',
        'session.secure' => true,
        'sanctum.stateful' => ['xomiton.example'],
        'queue.default' => 'database',
        'xomiton.heartbeat.url' => 'https://hc-ping.test/x',
        'xomiton.backup.password' => 'secret',
    ]);
}

it('échoue hors production et signale APP_DEBUG et HTTPS', function (): void {
    config(['app.debug' => true, 'app.url' => 'http://localhost']);

    $this->artisan('xomiton:preflight --skip-cron')
        ->expectsOutputToContain('[FAIL] APP_ENV vaut « testing »')
        ->expectsOutputToContain('[FAIL] APP_DEBUG doit être false')
        ->expectsOutputToContain('n’est pas en https')
        ->assertFailed();
});

it('échoue quand le cron n’a pas laissé de marqueur récent', function (): void {
    productionLikeSetup();

    $this->artisan('xomiton:preflight')
        ->expectsOutputToContain('[FAIL] Cron : aucun passage de alerts:escalate')
        ->assertFailed();
});

it('rétrograde le cron en avertissement avec --skip-cron', function (): void {
    productionLikeSetup();

    $this->artisan('xomiton:preflight --skip-cron')
        ->expectsOutputToContain('[WARN] Cron : aucun passage de notifications:retry')
        ->expectsOutputToContain('[WARN] Web Push : pilote « log »')
        ->assertSuccessful();
});

it('accepte un cron récent et refuse un cron ancien', function (): void {
    productionLikeSetup();
    Cache::put(EscalateAlertsCommand::SUCCESS_MARKER, now()->toIso8601String(), 600);
    Cache::put(RetryNotificationsCommand::SUCCESS_MARKER, now()->toIso8601String(), 600);

    $this->artisan('xomiton:preflight')
        ->expectsOutputToContain('[OK]   Cron : alerts:escalate réussi')
        ->assertSuccessful();

    Cache::put(RetryNotificationsCommand::SUCCESS_MARKER, now()->subMinutes(10)->toIso8601String(), 600);

    $this->artisan('xomiton:preflight')
        ->expectsOutputToContain('[FAIL] Cron : notifications:retry date de')
        ->assertFailed();
});

it('--strict fait échouer sur un simple avertissement', function (): void {
    productionLikeSetup();
    Cache::put(EscalateAlertsCommand::SUCCESS_MARKER, now()->toIso8601String(), 600);
    Cache::put(RetryNotificationsCommand::SUCCESS_MARKER, now()->toIso8601String(), 600);

    $this->artisan('xomiton:preflight --strict')->assertFailed();
});
