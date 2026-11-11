<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Application\Member\AccountSpaces;
use App\Application\Member\MobileSpaces;
use Illuminate\Console\Command;

/**
 * Interrupteur serveur d'un espace de l'application mobile (P15-22, MobileSpaces) : couper ou rouvrir l'espace Pair ou
 * l'espace Admin sans republier l'application, depuis le terminal cPanel, SSH ou une tâche cron (mêmes principes que
 * `xomiton:maintenance`). L'espace Membre ne se coupe pas (SOS, signalement, urgences : RG-01). Journal d'audit
 * `platform.mobile_space_switched` (acteur « système »).
 *   php artisan xomiton:mobile-space admin off
 *   php artisan xomiton:mobile-space peer on
 *   php artisan xomiton:mobile-space admin status
 */
class MobileSpaceCommand extends Command
{
    protected $signature = 'xomiton:mobile-space
        {space : peer ou admin}
        {state=status : on, off ou status}';

    protected $description = 'Coupe, rouvre ou décrit un espace (pair, admin) de l’application mobile, sans la republier';

    public function handle(): int
    {
        $space = strtolower(trim((string) $this->argument('space')));
        $state = strtolower(trim((string) $this->argument('state')));

        if (! in_array($space, [AccountSpaces::PEER, AccountSpaces::ADMIN], true)) {
            $this->error((string) __('account.spaces.command.unknown', ['space' => $space]));

            return self::INVALID;
        }

        if (! in_array($state, ['on', 'off', 'status'], true)) {
            $this->error((string) __('account.spaces.command.invalid_state', ['state' => $state]));

            return self::INVALID;
        }

        if ($state !== 'status') {
            MobileSpaces::set($space, $state === 'on');
        }

        $this->info((string) __($state === 'status' ? 'account.spaces.command.status' : 'account.spaces.command.changed', [
            'space' => $space,
            'state' => __('account.spaces.command.'.(MobileSpaces::enabled($space) ? 'on' : 'off')),
        ]));

        return self::SUCCESS;
    }
}
