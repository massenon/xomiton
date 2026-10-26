<?php

declare(strict_types=1);

namespace App\Console\Commands\Gateways;

use App\Application\Gateways\PublishDueDeliveries;
use Illuminate\Console\Command;

/**
 * P14-04 : planifiée chaque minute (routes/console.php). Publie par lots les remises échues via les connecteurs,
 * rejoue les échecs, laisse les autres à la publication manuelle assistée (statut `manuel`).
 */
class PublishGatewaysCommand extends Command
{
    protected $signature = 'gateways:publish
        {--limit= : Remises traitées au plus par exécution (défaut : xomiton.gateways.batch_size)}';

    protected $description = 'Publie les remises échues des passerelles (lots, rejeu des échecs, publication manuelle sinon)';

    public function handle(PublishDueDeliveries $publish): int
    {
        $limit = $this->option('limit');

        if ($limit !== null && (! ctype_digit((string) $limit) || (int) $limit < 1)) {
            $this->error('L’option --limit doit être un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        $result = $publish->run($limit === null ? null : (int) $limit);

        $this->line(sprintf('Publiées : %d ; en échec : %d ; à publier à la main : %d.', $result['published'], $result['failed'], $result['manual']));

        return self::SUCCESS;
    }
}
