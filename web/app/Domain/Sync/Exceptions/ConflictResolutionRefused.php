<?php

declare(strict_types=1);

namespace App\Domain\Sync\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Résolution d'un conflit de synchronisation refusée par l'administration (P13-01) : 422, message de
 * lang/fr/admin_system.php (sync.errors.*), rattaché au champ de l'écran.
 */
final class ConflictResolutionRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    private function __construct(string $key, private readonly string $errorKey, array $replace = [])
    {
        parent::__construct('admin_system.sync.errors.'.$key, $replace);
    }

    public function errorCode(): string
    {
        return $this->errorKey;
    }

    public function errors(): array
    {
        return ['merged' => [$this->userMessage()]];
    }

    public static function alreadyResolved(): self
    {
        return new self('already_resolved', 'conflict_already_resolved');
    }

    public static function unknownResolution(): self
    {
        return new self('unknown_resolution', 'conflict_unknown_resolution');
    }

    public static function subjectMissing(): self
    {
        return new self('subject_missing', 'conflict_subject_missing');
    }

    public static function notMergeable(): self
    {
        return new self('not_mergeable', 'conflict_not_mergeable');
    }

    /** @param  list<string>  $messages */
    public static function invalidValue(array $messages): self
    {
        return new self('invalid_value', 'conflict_invalid_value', ['reason' => implode(' ', $messages)]);
    }
}
