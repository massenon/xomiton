<?php

declare(strict_types=1);

namespace App\Console\Commands\Maintenance;

use App\Application\Maintenance\FlushMailOutbox;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;

class FlushMailOutboxCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'mail:flush-outbox
        {--limit=50 : Nombre maximal d’e-mails envoyés par exécution}';

    protected $description = 'Envoie les e-mails en attente dans mail_outbox quand un transport SMTP est disponible (50 au plus par exécution)';

    public function handle(FlushMailOutbox $flush): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1) {
            $this->error('L’option --limit doit être un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        $result = $flush->run($limit);

        if (! $result->transportAvailable) {
            $this->warn(sprintf(
                'Aucun transport SMTP disponible (MAIL_MAILER=%s) : %s en attente, rien n’est envoyé.',
                $result->mailer,
                $this->countLabel($result->remaining, 'e-mail reste', 'e-mails restent'),
            ));

            return self::SUCCESS;
        }

        if ($result->processed() === 0) {
            $this->line('Boîte d’envoi vide : aucun e-mail à envoyer.');

            return self::SUCCESS;
        }

        $this->info(sprintf(
            '%s via « %s », %s ; %s encore en attente.',
            $this->countLabel($result->sent, 'e-mail envoyé', 'e-mails envoyés'),
            $result->mailer,
            $this->countLabel($result->failed, 'échec', 'échecs'),
            $this->countLabel($result->remaining, 'e-mail', 'e-mails'),
        ));

        return $result->failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
