<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeExpiredIdempotencyKeys;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeIdempotencyKeysCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'idempotency:purge';

    protected $description = 'Supprime les réponses idempotentes (idempotency_keys) dont la fenêtre de rejeu est passée (ADR-32)';

    public function handle(PurgeExpiredIdempotencyKeys $rule): int
    {
        $deleted = $rule->run();

        $this->info(sprintf('%s.', $this->countLabel($deleted, 'clé d’idempotence purgée', 'clés d’idempotence purgées')));

        return self::SUCCESS;
    }
}
