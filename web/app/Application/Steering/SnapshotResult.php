<?php

declare(strict_types=1);

namespace App\Application\Steering;

/**
 * Bilan d'un instantané `indicators:snapshot` : valeurs enregistrées, calculs sans donnée, valeurs manuelles
 * conservées et indicateurs non calculables (code => raison).
 */
final class SnapshotResult
{
    public int $recorded = 0;

    public int $withoutData = 0;

    public int $manualKept = 0;

    /** Indicateurs intégrés calculés (au moins une valeur enregistrée ou tentée). */
    public int $indicators = 0;

    /** Départements parcourus pour les indicateurs calculés par zone. */
    public int $zones = 0;

    /** @var array<string, string> code => raison */
    public array $unsupported = [];

    /** @var array<string, string> code => raison de l'absence de valeur nationale */
    public array $withoutDataReasons = [];

    public function __construct(public readonly Period $period) {}
}
