<?php

declare(strict_types=1);

namespace App\Application\Retention;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Purge des sessions en base (D7 : `SESSION_DRIVER=database`, aucun ramasse-miettes garanti sur cPanel) :
 * supprime les lignes dont `last_activity` est antérieur à la durée de session plus un jour de marge.
 */
final class PurgeStaleSessions
{
    private const CHUNK_SIZE = 500;

    public function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    public function tableExists(): bool
    {
        return Schema::connection($this->connectionName())->hasTable($this->table());
    }

    /**
     * Horodatage Unix en dessous duquel une session est considérée morte.
     */
    public function cutoff(): int
    {
        return now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->subDays((int) config('xomiton.retention.session_grace_days', 1))
            ->getTimestamp();
    }

    /**
     * @return int Nombre de sessions supprimées (0 si la table n'existe pas).
     */
    public function run(): int
    {
        if (! $this->tableExists()) {
            return 0;
        }

        $cutoff = $this->cutoff();
        $deleted = 0;

        do {
            $ids = $this->query()
                ->where('last_activity', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id')
                ->all();

            if ($ids !== []) {
                $deleted += $this->query()->whereIn('id', $ids)->delete();
            }
        } while (count($ids) === self::CHUNK_SIZE);

        Log::info('Rétention : sessions expirées purgées.', ['count' => $deleted]);

        return $deleted;
    }

    private function connectionName(): ?string
    {
        $connection = config('session.connection');

        return is_string($connection) && $connection !== '' ? $connection : null;
    }

    private function query(): Builder
    {
        return DB::connection($this->connectionName())->table($this->table());
    }
}
