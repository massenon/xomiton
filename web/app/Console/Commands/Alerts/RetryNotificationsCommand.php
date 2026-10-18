<?php

declare(strict_types=1);

namespace App\Console\Commands\Alerts;

use App\Application\Alerts\Dispatch\RetryAlertNotifications;
use App\Console\Commands\Concerns\FormatsCounts;
use App\Models\AlertNotification;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `notifications:retry` (P5-03, ARCH §6, D7) : chaque minute, avant `heartbeat:ping`. Rejoue les notifications
 * d'alerte en attente ou échouées, applique les SMS de repli et supprime les abonnements push disparus ; pose le
 * marqueur `notifications:retry:last_success_at` (3 min) lu par la supervision (P3-07, P17-03). Des remises en échec
 * ne sont pas des erreurs de la commande ; une exception imprévue l'est (code FAILURE, marqueur non posé).
 */
class RetryNotificationsCommand extends Command
{
    use FormatsCounts;

    public const SUCCESS_MARKER = 'notifications:retry:last_success_at';

    protected $signature = 'notifications:retry
        {--limit=100 : Lignes examinées au plus par exécution}
        {--dry-run : Affiche ce qui serait rejoué sans rien envoyer ni écrire}
        {--now= : Horodatage de référence (ISO 8601, tests et reprise)}';

    protected $description = 'Rejoue les notifications d’alerte en attente ou échouées (push, SMS, e-mail), applique les SMS de repli et supprime les abonnements push disparus (D7, NOTIFICATIONS §4.7)';

    public function handle(RetryAlertNotifications $retry): int
    {
        $limit = (int) $this->option('limit');

        if ($limit < 1) {
            $this->error('L’option --limit doit être un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        try {
            $now = $this->option('now') ? CarbonImmutable::parse((string) $this->option('now')) : CarbonImmutable::now();
        } catch (Throwable) {
            $this->error('L’option --now doit être une date ISO 8601.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');

        try {
            $result = $retry->run($now, $limit, $dryRun);
        } catch (Throwable $exception) {
            Log::error('notifications:retry', ['error' => class_basename($exception)]);
            $this->error('Rejeu interrompu : '.class_basename($exception));

            return self::FAILURE;
        }

        foreach ($result->lines as $line) {
            $this->line(sprintf(
                '%s#%d (%s) %s%s',
                $dryRun ? '[simulation] ' : '',
                $line['id'],
                $line['channel'],
                $line['action'] === 'retry' ? 'à rejouer' : 'abandonnée',
                $line['reason'] === null ? '' : ' : '.AlertNotification::reasonLabel($line['reason']),
            ));
        }

        foreach ($result->errors as $error) {
            $this->error(sprintf('Échec pour la ligne #%d : %s', $error['id'], $error['error']));
        }

        $this->info(sprintf(
            '%s, %s, %s, %s, %s%s.',
            $this->countLabel($result->examined, 'ligne examinée', 'lignes examinées'),
            $this->countLabel($result->sent, 'envoyée', 'envoyées'),
            $this->countLabel($result->failed, 'échec (rejeu planifié)', 'échecs (rejeu planifié)'),
            $this->countLabel($result->skipped, 'abandonnée', 'abandonnées'),
            $this->countLabel($result->smsFallbacks, 'SMS de repli', 'SMS de repli'),
            $result->hasErrors() ? ', '.$this->countLabel(count($result->errors), 'erreur', 'erreurs') : '',
        ));

        if ($result->hasErrors()) {
            return self::FAILURE;
        }

        if (! $dryRun) {
            Cache::put(self::SUCCESS_MARKER, CarbonImmutable::now()->toIso8601String(), 180);
        }

        return self::SUCCESS;
    }
}
