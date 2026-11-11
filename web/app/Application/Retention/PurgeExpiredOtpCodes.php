<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Application\Retention\Concerns\DeletesInChunks;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Log;

/**
 * Règle de rétention des codes OTP (DATABASE_ENGINEERING.md §6 : 24 h) : supprime les codes expirés ou
 * consommés depuis plus de `xomiton.retention.otp_hours` heures. Un code encore valide n'est jamais touché.
 */
final class PurgeExpiredOtpCodes
{
    use DeletesInChunks;

    public function hours(): int
    {
        return (int) config('xomiton.retention.otp_hours', 24);
    }

    /**
     * @return int Nombre de codes supprimés.
     */
    public function run(): int
    {
        $deleted = $this->deleteInChunks(OtpCode::query()->purgeable($this->hours()));

        Log::info('Rétention : codes OTP purgés.', ['count' => $deleted, 'hours' => $this->hours()]);

        return $deleted;
    }
}
