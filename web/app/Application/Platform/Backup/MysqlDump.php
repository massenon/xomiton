<?php

declare(strict_types=1);

namespace App\Application\Platform\Backup;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Sauvegarde **officielle** (ADR-22, D7) : `mysqldump --single-transaction` compressé, déposé sur le disque de sauvegarde.
 * Disponible seulement sur MySQL / MariaDB avec `proc_open` et le binaire `mysqldump` ; sinon l'archive JSON de
 * BackupService est le repli. Le mot de passe de la base passe par l'environnement du processus, jamais par la ligne de
 * commande.
 */
final class MysqlDump
{
    public function binary(): ?string
    {
        if (! (bool) config('xomiton.backup.mysqldump', true) || ! function_exists('proc_open')) {
            return null;
        }

        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        return (new ExecutableFinder)->find((string) config('xomiton.backup.mysqldump_binary', 'mysqldump'));
    }

    public function available(): bool
    {
        return $this->binary() !== null;
    }

    /**
     * @return string|null chemin déposé sur le disque, null en cas d'échec (journalisé)
     */
    public function run(string $name): ?string
    {
        $binary = $this->binary();

        if ($binary === null) {
            return null;
        }

        $config = (array) config('database.connections.'.config('database.default'), []);
        $temporary = tempnam(sys_get_temp_dir(), 'xmdump');

        if ($temporary === false) {
            return null;
        }

        try {
            $handle = gzopen($temporary, 'wb6');

            if ($handle === false) {
                return null;
            }

            $process = new Process(
                [$binary, '--single-transaction', '--quick', '--no-tablespaces', '--default-character-set=utf8mb4',
                    '-h', (string) ($config['host'] ?? '127.0.0.1'), '-P', (string) ($config['port'] ?? '3306'),
                    '-u', (string) ($config['username'] ?? ''), (string) ($config['database'] ?? '')],
                null,
                ['MYSQL_PWD' => (string) ($config['password'] ?? '')],
                null,
                600,
            );
            $process->run(static function (string $type, string $buffer) use ($handle): void {
                if ($type === Process::OUT) {
                    gzwrite($handle, $buffer);
                }
            });
            gzclose($handle);

            if (! $process->isSuccessful()) {
                Log::warning('Sauvegarde : mysqldump en échec.', ['code' => $process->getExitCode()]);

                return null;
            }

            $path = trim((string) config('xomiton.backup.directory', 'backups'), '/').'/'.$name.'.sql.gz';
            $stream = fopen($temporary, 'rb');

            if ($stream === false || ! Storage::disk((string) config('xomiton.backup.disk', 'local'))->writeStream($path, $stream)) {
                return null;
            }

            fclose($stream);

            return $path;
        } catch (Throwable $exception) {
            Log::warning('Sauvegarde : mysqldump impossible.', ['error' => $exception->getMessage()]);

            return null;
        } finally {
            @unlink($temporary);
        }
    }
}
