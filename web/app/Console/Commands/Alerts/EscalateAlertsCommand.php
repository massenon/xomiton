<?php

declare(strict_types=1);

namespace App\Console\Commands\Alerts;

use App\Application\Alerts\EscalateAlerts;
use App\Console\Commands\Concerns\FormatsCounts;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * RG-05 / Q27 (P5-07) : planifiée chaque minute (routes/console.php, avant heartbeat:ping). Après une exécution sans
 * erreur, pose le marqueur `alerts:escalate:last_success_at` (3 min) lu par la supervision (P3-07, P17-03). Échoue sans
 * rien écrire tant qu'aucun écouteur n'envoie AlertEscalated / AlertReminderDue (P5-03).
 */
class EscalateAlertsCommand extends Command
{
    use FormatsCounts;

    public const SUCCESS_MARKER = 'alerts:escalate:last_success_at';

    protected $signature = 'alerts:escalate
        {--limit=200 : Alertes examinées au plus par exécution}
        {--dry-run : Affiche les décisions sans rien écrire ni émettre}
        {--now= : Horodatage de référence (ISO 8601, tests et reprise)}';

    protected $description = 'Escalade les SOS sans accusé de réception (5 min, RG-05) vers le point focal national, rappelle puis escalade les signalements et besoins sans accusé (24 h / 48 h, Q27)';

    public function handle(EscalateAlerts $escalate): int
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

        if (! $dryRun && ! $escalate->isWired()) {
            // Avant P5-03 : rien n'est consommé, le marqueur de succès n'est pas posé (la supervision le voit).
            Log::critical('alerts:escalate : aucun écouteur sur AlertEscalated / AlertReminderDue, escalade suspendue.');
            $this->error('Aucun envoi branché sur l’escalade (P5-03) : aucune alerte n’est escaladée ni rappelée.');

            return self::FAILURE;
        }

        $result = $escalate->run($now, $limit, $dryRun);

        foreach ($result->actions as $action) {
            $this->line(sprintf(
                '%s%s %s (%s, %d min) → %s',
                $dryRun ? '[simulation] ' : '',
                $action['action'] === 'escalate' ? 'Escalade' : 'Rappel',
                $action['code'],
                $action['type'],
                $action['minutes'],
                $action['target'],
            ));
        }

        foreach ($result->errors as $error) {
            $this->error(sprintf('Échec pour l’alerte %s : %s', $error['ulid'], $error['error']));
        }

        $this->info(sprintf(
            '%s, %s, %s, %s%s.',
            $this->countLabel($result->examined, 'alerte examinée', 'alertes examinées'),
            $this->countLabel($result->escalated, 'escaladée', 'escaladées'),
            $this->countLabel($result->reminded, 'rappelée', 'rappelées'),
            $this->countLabel($result->skipped, 'ignorée', 'ignorées'),
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
