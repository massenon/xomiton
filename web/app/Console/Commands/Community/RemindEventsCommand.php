<?php

declare(strict_types=1);

namespace App\Console\Commands\Community;

use App\Application\Community\Events\SendEventReminders;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

/**
 * P8-04 : rappel de la veille aux participant·es confirmé·es (App\Application\Community\Events\SendEventReminders).
 * Planifiée toutes les quinze minutes (routes/console.php, withoutOverlapping) : un événement reçoit son rappel entre
 * 24 h et 23 h 45 avant son début, une seule fois.
 */
class RemindEventsCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'events:remind {--chunk=100 : Événements traités par lot}';

    protected $description = 'Envoie le rappel de la veille aux participant·es des événements qui commencent dans les 24 h';

    public function handle(SendEventReminders $remind): int
    {
        $chunk = (int) $this->option('chunk');

        if ($chunk < 1) {
            $this->error('L’option --chunk doit être un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        $result = $remind($chunk);

        $this->info(sprintf(
            '%s, %s.',
            $this->countLabel($result['events'], 'événement rappelé', 'événements rappelés'),
            $this->countLabel($result['notified'], 'rappel envoyé', 'rappels envoyés'),
        ));

        return self::SUCCESS;
    }
}
