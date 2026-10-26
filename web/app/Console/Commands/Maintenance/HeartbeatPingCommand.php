<?php

declare(strict_types=1);

namespace App\Console\Commands\Maintenance;

use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Console\Commands\Alerts\RetryNotificationsCommand;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Signal de vie du cron vers la supervision externe (D7, NFR-03 : Healthchecks.io, UptimeRobot…).
 * Sans `HEARTBEAT_URL`, la commande ne fait rien et reste silencieuse.
 * Le signal n'est envoyé que si `alerts:escalate` et `notifications:retry` ont réussi récemment (marqueurs de succès,
 * P3-07 / P17-03) : un cron qui tourne mais dont les tâches échouent doit déclencher l'alerte de la supervision.
 * `xomiton.heartbeat.require_markers` = false désactive cette condition (environnements sans ces tâches).
 */
class HeartbeatPingCommand extends Command
{
    public const TIMEOUT_SECONDS = 5;

    protected $signature = 'heartbeat:ping';

    protected $description = 'Envoie un signal de vie (GET, délai 5 s) à l’URL de supervision xomiton.heartbeat.url';

    public function handle(): int
    {
        $url = trim((string) config('xomiton.heartbeat.url', ''));

        if ($url === '') {
            return self::SUCCESS;
        }

        $stale = $this->staleMarkers();

        if ($stale !== []) {
            $this->warn('Signal de vie non envoyé : tâche en échec ou absente ('.implode(', ', $stale).').');
            Log::warning('Signal de vie retenu : marqueurs de succès absents.', ['markers' => $stale]);

            return self::FAILURE;
        }

        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->connectTimeout(self::TIMEOUT_SECONDS)
                ->withUserAgent('Xomiton heartbeat')
                ->get($url);
        } catch (Throwable $exception) {
            $this->warn('Signal de vie non délivré : '.$exception->getMessage());
            Log::warning('Signal de vie non délivré.', ['url' => $url, 'error' => $exception->getMessage()]);

            return self::FAILURE;
        }

        if (! $response->successful()) {
            $this->warn(sprintf('Signal de vie refusé par la supervision (HTTP %d).', $response->status()));
            Log::warning('Signal de vie refusé par la supervision.', ['url' => $url, 'status' => $response->status()]);

            return self::FAILURE;
        }

        $this->info('Signal de vie envoyé.');

        return self::SUCCESS;
    }

    /** @return list<string> marqueurs de succès absents ou expirés (TTL 3 min posé par les commandes). */
    private function staleMarkers(): array
    {
        if (! (bool) config('xomiton.heartbeat.require_markers', true)) {
            return [];
        }

        return array_values(array_filter(
            [EscalateAlertsCommand::SUCCESS_MARKER, RetryNotificationsCommand::SUCCESS_MARKER],
            static fn (string $marker): bool => ! Cache::has($marker),
        ));
    }
}
