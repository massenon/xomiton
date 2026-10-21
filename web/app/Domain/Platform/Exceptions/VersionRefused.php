<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;

/**
 * Publication, planification ou déploiement d'une version refusé (P13-02) : 422, message de lang/fr/admin_system.php
 * (updates.errors.*), rattaché au champ de l'écran.
 */
final class VersionRefused extends DomainException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    private function __construct(string $key, private readonly string $errorKey, private readonly string $field, array $replace = [])
    {
        parent::__construct('admin_system.updates.errors.'.$key, $replace);
    }

    public function errorCode(): string
    {
        return $this->errorKey;
    }

    public function errors(): array
    {
        return [$this->field => [$this->userMessage()]];
    }

    public static function exists(string $version): self
    {
        return new self('exists', 'version_exists', 'version', ['version' => $version]);
    }

    public static function notNewer(string $current): self
    {
        return new self('not_newer', 'version_not_newer', 'version', ['current' => $current]);
    }

    public static function notPlanned(): self
    {
        return new self('not_planned', 'version_not_planned', 'version');
    }

    public static function pastDate(): self
    {
        return new self('past_date', 'version_past_date', 'scheduledFor');
    }

    public static function minimumAboveCurrent(string $current): self
    {
        return new self('minimum_above_current', 'version_minimum_above_current', 'minVersion', ['current' => $current]);
    }

    public static function unknownTask(): self
    {
        return new self('unknown_task', 'optimization_unknown_task', 'task');
    }
}
