<?php

declare(strict_types=1);

namespace App\Application\Account\Jobs;

use App\Application\Account\PersonalDataExports;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Export RGPD en file (`QUEUE_CONNECTION=database`, D7 : traité par la ligne cron `queue:work --stop-when-empty`) :
 * jamais dans la requête (la collecte peut être longue). Une seule tâche par compte à la fois (ShouldBeUnique).
 * Compte supprimé entre-temps : rien n'est construit.
 */
final class BuildPersonalDataExport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $userId) {}

    public function uniqueId(): string
    {
        return 'personal-export-'.$this->userId;
    }

    public function handle(PersonalDataExports $exports): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            return;
        }

        $exports->build($user);
    }
}
