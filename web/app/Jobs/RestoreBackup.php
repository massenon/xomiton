<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Platform\Backup\RestoreRunner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Un maillon de la restauration d'une sauvegarde (P13-03, ADR-22) : exécute les étapes dans le budget de
 * `xomiton.backup.job_budget_seconds` (< 50 s, cron cPanel `queue:work --stop-when-empty --max-time=50`), puis se
 * ré-enfile tant qu'il reste du travail. Un verrou évite que deux workers traitent la même chaîne ; un échec marque
 * l'état « échec » (reprise possible depuis l'écran), sans nouvel essai automatique.
 */
final class RestoreBackup implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 1;

    public int $timeout = 55;

    public function __construct(public readonly string $restoreId) {}

    public function handle(RestoreRunner $runner): void
    {
        $lock = Cache::lock('xomiton.backup.restore', 120);

        if (! $lock->get()) {
            $this->release(10);

            return;
        }

        try {
            $more = $runner->run($this->restoreId);
        } finally {
            $lock->release();
        }

        if ($more) {
            self::dispatch($this->restoreId);
        }
    }

    public function failed(Throwable $exception): void
    {
        app(RestoreRunner::class)->fail($this->restoreId, $exception);
    }
}
