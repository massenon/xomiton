<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\KeyedHash;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

/**
 * Limiteurs nommés du groupe `admin/*` de l'API (P15-16) : `admin_api` (toutes les routes, par compte), `admin_write`
 * (écritures rejouables, `idempotent:required,limit=admin_write` : un rejeu ne consomme pas le quota) et `admin_step_up`
 * (essais de mot de passe de `POST admin/step-up`, par compte et par heure). Seuils : config `xomiton.rate_limits`.
 */
final class AdminApiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $tooMany = static function (Request $request, array $headers): never {
            throw new ThrottleRequestsException(__('auth.throttle', ['seconds' => (int) ($headers['Retry-After'] ?? 60)]), null, $headers);
        };
        $key = static fn (Request $request, string $name): string => $name.'|'.($request->user('sanctum')?->getAuthIdentifier() ?? KeyedHash::make((string) $request->ip()));

        RateLimiter::for('admin_api', static fn (Request $request): Limit => Limit::perMinute((int) config('xomiton.rate_limits.admin_api_per_minute', 120))
            ->by($key($request, 'admin_api'))->response($tooMany));
        RateLimiter::for('admin_write', static fn (Request $request): Limit => Limit::perMinute((int) config('xomiton.rate_limits.admin_write_per_minute', 30))
            ->by($key($request, 'admin_write'))->response($tooMany));
        RateLimiter::for('admin_step_up', static fn (Request $request): Limit => Limit::perHour((int) config('xomiton.rate_limits.admin_step_up_per_hour', 10))
            ->by($key($request, 'admin_step_up'))->response($tooMany));
    }
}
