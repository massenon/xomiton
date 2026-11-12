<?php

declare(strict_types=1);

namespace App\Console\Commands\Moderation;

use App\Application\Admin\Users\AccountSuspension;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * P18-06 : réactive les comptes dont la suspension temporaire est arrivée à échéance (`users.suspended_until` passé),
 * notifie la personne et journalise `users.suspension_lifted` (acteur : système). Planifiée toutes les cinq minutes
 * (routes/console.php, withoutOverlapping) ; un compte banni n'est jamais concerné.
 */
class LiftSuspensionsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'accounts:lift-suspensions';

    protected $description = 'Réactive les comptes dont la suspension temporaire est arrivée à échéance';

    public function handle(AccountSuspension $suspension): int
    {
        $lifted = $suspension->liftExpired();
        $this->info($this->countLabel($lifted, 'compte réactivé', 'comptes réactivés').'.');

        return self::SUCCESS;
    }
}
