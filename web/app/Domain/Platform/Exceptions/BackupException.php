<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Refus ou échec d'une action de l'écran Sauvegardes (P13-03) : 422, message de lang/fr/admin_system.php (backups.errors.*).
 */
final class BackupException extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    private function __construct(string $langKey, private readonly string $errorKey, array $replace = [])
    {
        parent::__construct('admin_system.backups.errors.'.$langKey, $replace);
    }

    public function errorCode(): string
    {
        return $this->errorKey;
    }

    /** ext-zip absente : repli documenté (mysqldump de l'hébergeur, ADR-22). */
    public static function zipMissing(): self
    {
        return new self('zip_missing', 'backup_zip_missing');
    }

    public static function failed(): self
    {
        return new self('failed', 'backup_failed');
    }

    public static function unreadable(): self
    {
        return new self('unreadable', 'backup_unreadable');
    }

    public static function schemaTooNew(): self
    {
        return new self('schema_too_new', 'backup_schema_too_new');
    }

    public static function notRestorable(): self
    {
        return new self('not_restorable', 'backup_not_restorable');
    }

    public static function wordMismatch(): self
    {
        return new self('word_mismatch', 'backup_word_mismatch');
    }

    public static function alreadyRunning(): self
    {
        return new self('already_running', 'restore_already_running');
    }

    public static function notDeletable(): self
    {
        return new self('not_deletable', 'backup_not_deletable');
    }
}
