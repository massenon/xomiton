<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

/**
 * Position d'un appareil dans une collection de `GET sync/pull` (P9-06) : dernière ligne servie (horodatage de
 * modification en secondes Unix + identifiant, curseur en jeu de clés) et dernière pierre tombale servie.
 */
final readonly class PullPosition
{
    public function __construct(
        public int $at = 0,
        public int $id = 0,
        public int $tombAt = 0,
        public int $tombId = 0,
    ) {}

    public function isStart(): bool
    {
        return $this->at === 0 && $this->id === 0 && $this->tombAt === 0 && $this->tombId === 0;
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    public function toArray(): array
    {
        return [$this->at, $this->id, $this->tombAt, $this->tombId];
    }
}
