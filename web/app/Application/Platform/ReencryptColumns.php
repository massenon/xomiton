<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Models\Backup;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use JsonException;
use RuntimeException;

/**
 * Rotation d'`APP_KEY` (P1-15, D7, DATABASE_ENGINEERING.md §6) : parcourt par `chunkById` chaque colonne
 * chiffrée de `config('xomiton.encrypted_columns')`, déchiffre avec l'ancienne clé via un Encrypter dédié et
 * rechiffre avec la clé courante, en écrivant directement la colonne (pas d'événement Eloquent, `updated_at`
 * inchangé : une rotation de clé n'est pas une modification métier).
 *
 * Reprise : une valeur déjà déchiffrable avec la clé courante **seule** (sans les clés précédentes) est laissée
 * telle quelle, si bien qu'un appel interrompu — par le temps imparti de 50 s de l'hébergement mutualisé — se
 * poursuit au suivant. Une valeur indéchiffrable avec les deux clés est comptée en échec, laissée intacte et
 * journalisée (table, colonne, id ; jamais la valeur).
 *
 * `settings.value` est traitée à part : seules les lignes de type `secret` sont chiffrées, sous la forme d'une
 * chaîne JSON contenant la charge chiffrée (App\Models\Setting::encodeRaw).
 */
final class ReencryptColumns
{
    public const AUDIT_ACTION = 'platform.reencrypt';

    public const DEFAULT_CHUNK_SIZE = 500;

    /** Temps imparti par appel (secondes) : marge sous les 60 s du cron et les 30 s / 50 s de cPanel. */
    public const TIME_BUDGET_SECONDS = 50.0;

    /** Âge maximal de la dernière sauvegarde terminée pour autoriser la rotation. */
    public const BACKUP_MAX_AGE_HOURS = 24;

    private const SETTINGS_TABLE = 'settings';

    private const STATUS_CURRENT = 'current';

    private const STATUS_ROTATED = 'rotated';

    private const STATUS_FAILED = 'failed';

    /**
     * Table => colonnes chiffrées, telles que déclarées dans config/xomiton.php.
     *
     * @return array<string, list<string>>
     */
    public function columns(): array
    {
        $columns = config('xomiton.encrypted_columns', []);

        if (! is_array($columns)) {
            return [];
        }

        $result = [];

        foreach ($columns as $table => $list) {
            if (is_string($table) && is_array($list) && $list !== []) {
                $result[$table] = array_values(array_map('strval', $list));
            }
        }

        return $result;
    }

    /**
     * Une sauvegarde terminée existe-t-elle depuis moins de `BACKUP_MAX_AGE_HOURS` ? (sauvegarde préalable obligatoire)
     */
    public function hasRecentBackup(): bool
    {
        return Backup::query()
            ->completed()
            ->where('created_at', '>=', now()->subHours(self::BACKUP_MAX_AGE_HOURS))
            ->exists();
    }

    /**
     * Ancienne clé à utiliser : celle passée explicitement, sinon la première d'`APP_PREVIOUS_KEYS`.
     *
     * @throws InvalidArgumentException sans ancienne clé disponible.
     */
    public function previousKey(?string $from = null): string
    {
        $key = trim((string) $from);

        if ($key === '') {
            $previous = config('app.previous_keys', []);
            $key = is_array($previous) && isset($previous[0]) ? trim((string) $previous[0]) : '';
        }

        if ($key === '') {
            throw new InvalidArgumentException(
                'Aucune ancienne clé : passez --from=<clé> ou renseignez APP_PREVIOUS_KEYS dans le fichier .env.'
            );
        }

        return $key;
    }

    /**
     * Rechiffre toutes les colonnes déclarées, table par table, dans le temps imparti.
     *
     * @param  string  $fromKey  ancienne clé (`base64:…` ou brute)
     * @param  (callable(string $table, array{processed: int, skipped: int, failed: int} $counts): void)|null  $onTable  rappel après chaque table
     *
     * @throws InvalidArgumentException clé invalide ou identique à la clé courante.
     */
    public function run(
        string $fromKey,
        int $chunkSize = self::DEFAULT_CHUNK_SIZE,
        bool $dryRun = false,
        float $timeBudget = self::TIME_BUDGET_SECONDS,
        ?callable $onTable = null,
    ): ReencryptResult {
        if ($chunkSize < 1) {
            throw new InvalidArgumentException('La taille des lots doit être un entier supérieur ou égal à 1.');
        }

        $cipher = (string) config('app.cipher', 'AES-256-CBC');
        $currentKey = $this->parseKey((string) config('app.key'), 'APP_KEY');
        $previousKey = $this->parseKey($fromKey, 'ancienne clé');

        if (hash_equals($currentKey, $previousKey)) {
            throw new InvalidArgumentException('L’ancienne clé est identique à APP_KEY : rien à rechiffrer.');
        }

        // Encrypters dédiés, sans clés précédentes : la clé courante seule décide de ce qui est « déjà à jour ».
        $current = $this->encrypter($currentKey, $cipher, 'APP_KEY');
        $previous = $this->encrypter($previousKey, $cipher, 'ancienne clé');

        $startedAt = microtime(true);
        $tables = [];
        $completed = true;

        foreach ($this->columns() as $table => $columns) {
            [$counts, $tableCompleted] = $this->processTable(
                $table, $columns, $current, $previous, $chunkSize, $dryRun, $startedAt, $timeBudget,
            );

            $tables[$table] = $counts;

            Log::info('Ré-encryption : table parcourue.', [
                'table' => $table,
                'dry_run' => $dryRun,
                'completed' => $tableCompleted,
            ] + $counts);

            if ($onTable !== null) {
                $onTable($table, $counts);
            }

            if (! $tableCompleted) {
                $completed = false;

                break;
            }
        }

        $result = new ReencryptResult($tables, $completed, round(microtime(true) - $startedAt, 2), $dryRun);

        if (! $dryRun) {
            Audit::logWith(self::AUDIT_ACTION, [
                'tables' => $tables,
                'completed' => $completed,
                'processed' => $result->processed(),
                'skipped' => $result->skipped(),
                'failed' => $result->failed(),
                'elapsed_seconds' => $result->elapsedSeconds,
                'chunk' => $chunkSize,
            ]);
        }

        return $result;
    }

    /**
     * @param  list<string>  $columns
     * @return array{0: array{processed: int, skipped: int, failed: int}, 1: bool}
     */
    private function processTable(
        string $table,
        array $columns,
        Encrypter $current,
        Encrypter $previous,
        int $chunkSize,
        bool $dryRun,
        float $startedAt,
        float $timeBudget,
    ): array {
        $counts = ['processed' => 0, 'skipped' => 0, 'failed' => 0];
        $completed = true;
        $touched = false;

        $query = DB::table($table)->select(array_merge(['id'], $columns));

        if ($table === self::SETTINGS_TABLE) {
            $query->where('type', Setting::TYPE_SECRET);
        }

        $query->chunkById($chunkSize, function (Collection $rows) use (
            $table, $columns, $current, $previous, $dryRun, $startedAt, $timeBudget, &$counts, &$completed, &$touched
        ): ?bool {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $raw = $row->{$column} ?? null;

                    if (! is_string($raw) || $raw === '') {
                        continue;
                    }

                    [$status, $fresh] = $this->rotate($raw, $table, $current, $previous);

                    match ($status) {
                        self::STATUS_CURRENT => $counts['skipped']++,
                        self::STATUS_ROTATED => $counts['processed']++,
                        default => $counts['failed']++,
                    };

                    if ($status === self::STATUS_ROTATED && $fresh !== null) {
                        $updates[$column] = $fresh;
                    }

                    if ($status === self::STATUS_FAILED) {
                        Log::warning('Ré-encryption : valeur indéchiffrable avec l’ancienne clé comme avec la clé courante.', [
                            'table' => $table,
                            'column' => $column,
                            'id' => $row->id,
                        ]);
                    }
                }

                if ($updates !== [] && ! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update($updates);
                    $touched = true;
                }
            }

            if (microtime(true) - $startedAt >= $timeBudget) {
                $completed = false;

                return false;
            }

            return null;
        });

        if ($touched && $table === self::SETTINGS_TABLE) {
            // Les lignes brutes sont mises en cache 24 h : elles doivent refléter la nouvelle charge chiffrée.
            Setting::flushCache();
        }

        return [$counts, $completed];
    }

    /**
     * Rechiffre une valeur brute : déjà lisible avec la clé courante, rechiffrée depuis l'ancienne clé, ou en échec.
     *
     * @return array{0: string, 1: ?string} statut et nouvelle valeur brute (pour `rotated`)
     */
    private function rotate(string $raw, string $table, Encrypter $current, Encrypter $previous): array
    {
        $payload = $table === self::SETTINGS_TABLE ? $this->unwrapSetting($raw) : $raw;

        if ($payload === null) {
            return [self::STATUS_FAILED, null];
        }

        try {
            $current->decrypt($payload, false);

            return [self::STATUS_CURRENT, null];
        } catch (DecryptException) {
            // Pas chiffrée avec la clé courante : on tente l'ancienne.
        }

        try {
            $plain = $previous->decrypt($payload, false);
        } catch (DecryptException) {
            return [self::STATUS_FAILED, null];
        }

        $fresh = $current->encrypt($plain, false);

        if ($table === self::SETTINGS_TABLE) {
            $fresh = json_encode($fresh, JSON_THROW_ON_ERROR);
        }

        return [self::STATUS_ROTATED, $fresh];
    }

    /**
     * `settings.value` d'un secret : JSON d'une chaîne contenant la charge chiffrée.
     */
    private function unwrapSetting(string $raw): ?string
    {
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    /**
     * Clé telle que `config/app.php` la lit : préfixe `base64:` décodé, sinon brute.
     */
    private function parseKey(string $key, string $label): string
    {
        $key = trim($key);

        if ($key === '') {
            throw new InvalidArgumentException(sprintf('La clé « %s » est vide.', $label));
        }

        if (str_starts_with($key, 'base64:')) {
            $decoded = base64_decode(substr($key, 7), true);

            if ($decoded === false || $decoded === '') {
                throw new InvalidArgumentException(sprintf('La clé « %s » n’est pas un base64 valide.', $label));
            }

            return $decoded;
        }

        return $key;
    }

    private function encrypter(string $key, string $cipher, string $label): Encrypter
    {
        try {
            return new Encrypter($key, $cipher);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException(sprintf(
                'La clé « %s » ne convient pas au chiffrement %s (%s).',
                $label,
                $cipher,
                $exception->getMessage(),
            ), 0, $exception);
        }
    }
}
