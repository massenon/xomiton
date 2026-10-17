<?php

declare(strict_types=1);

namespace App\Console\Commands\Maintenance;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Signal de vie du cron vers la supervision externe (D7, NFR-03 : Healthchecks.io, UptimeRobot…).
 * Sans `HEARTBEAT_URL`, la commande ne fait rien et reste silencieuse.
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
}
