<?php

declare(strict_types=1);

namespace App\Console\Commands\Feed;

use App\Application\Feed\ClosePolls;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * P7-07 : clôt les sondages arrivés à échéance et envoie la cloche « Sondage terminé » à leur auteur·rice
 * (App\Application\Feed\ClosePolls). Planifiée toutes les cinq minutes (routes/console.php, withoutOverlapping).
 */
class ClosePollsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'polls:close {--chunk=100 : Sondages traités par lot}';

    protected $description = 'Clôt les sondages arrivés à échéance et prévient leur auteur·rice (« Sondage terminé »)';

    public function handle(ClosePolls $close): int
    {
        $chunk = (int) $this->option('chunk');

        if ($chunk < 1) {
            $this->error('L’option --chunk doit être un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        $result = $close($chunk);

        $this->info(sprintf(
            '%s, %s.',
            $this->countLabel($result['closed'], 'sondage clos', 'sondages clos'),
            $this->countLabel($result['notified'], 'notification envoyée', 'notifications envoyées'),
        ));

        return self::SUCCESS;
    }
}
