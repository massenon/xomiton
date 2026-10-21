<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use App\Application\Platform\ReencryptColumns;
use App\Models\Setting;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Colonnes chiffrées d'une sauvegarde (ADR-22) : exportées **déchiffrées** (liste `xomiton.encrypted_columns`), rechiffrées
 * avec la clé courante à la restauration — une rotation d'APP_KEY entre les deux ne rend aucun récit illisible.
 * `settings.value` : seules les lignes de type `secret` sont chiffrées, en chaîne JSON contenant la charge (Setting).
 * Une valeur indéchiffrable (ancienne clé perdue) reste telle quelle, préfixée de RAW_PREFIX, et est comptée.
 */
final class ColumnCipher
{
    public const RAW_PREFIX = 'xm-raw-ciphertext:';

    /** @var array<string, list<string>>|null */
    private ?array $columns = null;

    public function __construct(private readonly ReencryptColumns $columnsSource) {}

    /**
     * @return list<string>
     */
    public function columnsOf(string $table): array
    {
        $this->columns ??= $this->columnsSource->columns();

        return $this->columns[$table] ?? [];
    }

    /**
     * @param  array<string, mixed>  $row  ligne brute de la base
     * @return array{0: array<string, mixed>, 1: int} ligne en clair et nombre de valeurs indéchiffrables
     */
    public function decryptRow(string $table, array $row): array
    {
        $failed = 0;

        foreach ($this->columnsOf($table) as $column) {
            if (! isset($row[$column]) || ! is_string($row[$column]) || $row[$column] === '' || ! $this->applies($table, $row)) {
                continue;
            }

            $plain = $this->decrypt($table, $row[$column]);

            if ($plain === null) {
                $row[$column] = self::RAW_PREFIX.$row[$column];
                $failed++;
            } else {
                $row[$column] = $plain;
            }
        }

        return [$row, $failed];
    }

    /**
     * @param  array<string, mixed>  $row  ligne d'une sauvegarde
     * @return array<string, mixed>
     */
    public function encryptRow(string $table, array $row): array
    {
        foreach ($this->columnsOf($table) as $column) {
            if (! isset($row[$column]) || ! is_string($row[$column]) || $row[$column] === '' || ! $this->applies($table, $row)) {
                continue;
            }

            $value = $row[$column];

            if (str_starts_with($value, self::RAW_PREFIX)) {
                $row[$column] = substr($value, strlen(self::RAW_PREFIX));

                continue;
            }

            $row[$column] = $table === 'settings'
                ? json_encode(Crypt::encryptString($value), JSON_THROW_ON_ERROR)
                : Crypt::encryptString($value);
        }

        return $row;
    }

    /** `settings.value` n'est chiffrée que pour les secrets. */
    private function applies(string $table, array $row): bool
    {
        return $table !== 'settings' || ($row['type'] ?? null) === Setting::TYPE_SECRET;
    }

    private function decrypt(string $table, string $raw): ?string
    {
        try {
            if ($table === 'settings') {
                $payload = json_decode($raw, true);

                return is_string($payload) ? Crypt::decryptString($payload) : null;
            }

            return Crypt::decryptString($raw);
        } catch (DecryptException) {
            return null;
        }
    }
}
