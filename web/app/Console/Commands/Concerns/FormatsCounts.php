<?php

declare(strict_types=1);

namespace App\Console\Commands\Concerns;

/**
 * Accord en nombre des messages de sortie (« 1 code OTP purgé », « 12 codes OTP purgés »).
 */
trait FormatsCounts
{
    protected function countLabel(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count > 1 ? $plural : $singular);
    }
}
