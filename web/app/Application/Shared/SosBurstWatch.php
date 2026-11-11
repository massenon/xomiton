<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\User;
use App\Notifications\SosBurstDetected;
use App\Support\KeyedHash;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Limiteur `sos` en mode observateur (P2-08, ARCH §4 « sans limite bloquante », AppServiceProvider) : compte les SOS par
 * adresse sur une minute glissante ; au-delà de `rate_limits.sos_log_per_minute_per_ip` (5), chaque envoi est journalisé
 * et les comptes `alerts.manage` reçoivent une cloche SosBurstDetected (au plus une par adresse et par quart d'heure).
 * L'IP n'apparaît jamais en clair (clé de limiteur, journal, notification) : empreinte HMAC à clé d'application (ADR-33).
 * Ne lève jamais : une panne de cache ou de base ne doit pas faire échouer un SOS.
 */
final class SosBurstWatch
{
    public const LOG_MESSAGE = 'SOS : envois répétés depuis la même adresse.';

    public const PERMISSION = 'alerts.manage';

    /** Une notification au plus par adresse et par fenêtre (secondes). */
    public const NOTIFY_EVERY_SECONDS = 900;

    public function record(Request $request): void
    {
        try {
            $fingerprint = KeyedHash::make((string) $request->ip(), 16);
            $hits = RateLimiter::hit('sos-watch|'.$fingerprint, 60);

            if ($hits <= max(1, (int) config('xomiton.rate_limits.sos_log_per_minute_per_ip', 5))) {
                return;
            }

            Log::warning(self::LOG_MESSAGE, [
                'hits_last_minute' => $hits,
                'route' => $request->route()?->getName(),
                'ip_hash' => $fingerprint,
            ]);

            $this->notifyAdmins($fingerprint, $hits);
        } catch (Throwable $e) {
            Log::error('SOS : surveillance des envois répétés indisponible.', ['error' => $e->getMessage()]);
        }
    }

    private function notifyAdmins(string $fingerprint, int $hits): void
    {
        $key = 'sos-watch-notified|'.$fingerprint;

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return;
        }

        RateLimiter::hit($key, self::NOTIFY_EVERY_SECONDS);
        $admins = self::admins();

        if ($admins->isNotEmpty()) {
            Notification::send($admins, new SosBurstDetected($hits, $fingerprint));
        }
    }

    /**
     * @return Collection<int, User>
     */
    private static function admins(): Collection
    {
        return User::query()->active()->permission(self::PERMISSION)->orderBy('id')->get();
    }
}
