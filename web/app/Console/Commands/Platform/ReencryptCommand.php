<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Application\Platform\ReencryptColumns;
use App\Application\Platform\ReencryptResult;
use App\Console\Commands\Concerns\FormatsCounts;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Rotation d'`APP_KEY` (P1-15, runbook P17-05) : à lancer après `php artisan key:generate` avec l'ancienne clé
 * dans `APP_PREVIOUS_KEYS` (ou `--from`). Chaque appel s'arrête proprement après 50 s (cron cPanel) : relancer
 * jusqu'au message « terminée », puis retirer l'ancienne clé d'`APP_PREVIOUS_KEYS`.
 */
class ReencryptCommand extends Command
{
    use FormatsCounts;

    protected $signature = 'xomiton:reencrypt
        {--from= : Ancienne clé (base64:… ou brute) ; par défaut la première d’APP_PREVIOUS_KEYS, à préférer (pas de clé dans l’historique du shell)}
        {--chunk=500 : Nombre de lignes lues par lot}
        {--dry-run : Compte les valeurs à rechiffrer sans rien écrire}
        {--force : Passe outre l’absence de sauvegarde terminée depuis moins de 24 h}';

    protected $description = 'Rechiffre avec APP_KEY toutes les colonnes chiffrées encore lisibles seulement avec l’ancienne clé (rotation de clé, reprise automatique, 50 s par appel)';

    public function handle(ReencryptColumns $rule): int
    {
        $chunk = $this->option('chunk');

        if (! is_numeric($chunk) || (int) $chunk < 1 || (int) $chunk != $chunk) {
            $this->error('L’option --chunk attend un entier supérieur ou égal à 1.');

            return self::INVALID;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! $this->option('force') && ! $rule->hasRecentBackup()) {
            $this->error(sprintf(
                'Aucune sauvegarde terminée depuis moins de %d h dans la table backups : lancez une sauvegarde avant la rotation de clé (ou --force pour passer outre, à vos risques).',
                ReencryptColumns::BACKUP_MAX_AGE_HOURS,
            ));

            return self::FAILURE;
        }

        try {
            $from = $rule->previousKey($this->option('from'));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        if ($dryRun) {
            $this->line('Simulation : aucune valeur ne sera modifiée.');
        }

        try {
            $result = $rule->run(
                $from,
                (int) $chunk,
                $dryRun,
                ReencryptColumns::TIME_BUDGET_SECONDS,
                function (string $table, array $counts): void {
                    $this->line(sprintf(
                        '  %-20s %s, %s, %s',
                        $table,
                        $this->countLabel($counts['processed'], 'valeur rechiffrée', 'valeurs rechiffrées'),
                        $this->countLabel($counts['skipped'], 'déjà à jour', 'déjà à jour'),
                        $this->countLabel($counts['failed'], 'échec', 'échecs'),
                    ));
                },
            );
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::INVALID;
        }

        $this->summarize($result);

        return $result->hasFailures() ? self::FAILURE : self::SUCCESS;
    }

    private function summarize(ReencryptResult $result): void
    {
        $this->newLine();

        $this->info(sprintf(
            '%s%s en %s s, %s, %s.',
            $result->dryRun ? 'Simulation : ' : '',
            $this->countLabel($result->processed(), $result->dryRun ? 'valeur à rechiffrer' : 'valeur rechiffrée', $result->dryRun ? 'valeurs à rechiffrer' : 'valeurs rechiffrées'),
            number_format($result->elapsedSeconds, 1, ',', ' '),
            $this->countLabel($result->skipped(), 'déjà à jour', 'déjà à jour'),
            $this->countLabel($result->failed(), 'échec', 'échecs'),
        ));

        if ($result->hasFailures()) {
            $this->warn('Des valeurs restent indéchiffrables avec les deux clés : elles sont laissées intactes et listées dans le journal (table, colonne, id). Vérifiez --from / APP_PREVIOUS_KEYS.');
        }

        if (! $result->completed) {
            $this->warn(sprintf(
                'Temps imparti de %d s atteint : la ré-encryption n’est pas terminée. Relancez la commande, elle reprendra où elle s’est arrêtée (les valeurs déjà rechiffrées sont ignorées).',
                (int) ReencryptColumns::TIME_BUDGET_SECONDS,
            ));

            return;
        }

        if (! $result->dryRun) {
            $this->info('Ré-encryption terminée : l’ancienne clé peut être retirée d’APP_PREVIOUS_KEYS une fois la lecture vérifiée.');
        }
    }
}
