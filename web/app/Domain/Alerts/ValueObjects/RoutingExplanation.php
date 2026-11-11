<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

use App\Enums\RoutingFallback;

/**
 * Aperçu du routage (P11, MODULE_SPECIFICATIONS.md §P11) : pour chacun des 4 niveaux de RG-04, les contacts examinés
 * (identifiants), le contact trouvé et si le niveau a été sauté (département absent), puis la décision retenue.
 */
final readonly class RoutingExplanation
{
    /**
     * @param  list<array{fallback: RoutingFallback, level: int, examined: list<int>, matched: int|null, skipped: bool, retained: bool}>  $levels
     */
    public function __construct(
        public array $levels,
        public RoutingDecision $decision,
    ) {}

    /**
     * @return array{fallback: RoutingFallback, level: int, examined: list<int>, matched: int|null, skipped: bool, retained: bool}|null
     */
    public function retainedLevel(): ?array
    {
        foreach ($this->levels as $level) {
            if ($level['retained']) {
                return $level;
            }
        }

        return null;
    }
}
