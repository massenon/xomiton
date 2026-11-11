<?php

declare(strict_types=1);

namespace App\Console\Commands\Retention;

use App\Application\Retention\PurgeExpiredOtpCodes;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class PurgeOtpCodesCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'otp:purge';

    protected $description = 'Supprime les codes OTP expirés ou consommés depuis plus de 24 h (DATABASE_ENGINEERING.md §6)';

    public function handle(PurgeExpiredOtpCodes $rule): int
    {
        $deleted = $rule->run();

        $this->info(sprintf(
            '%s (expirés ou consommés depuis plus de %d h).',
            $this->countLabel($deleted, 'code OTP purgé', 'codes OTP purgés'),
            $rule->hours(),
        ));

        return self::SUCCESS;
    }
}
