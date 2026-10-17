<?php

declare(strict_types=1);

namespace App\Application\Steering;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Période d'un instantané d'indicateur (bornes de dates incluses) : par défaut le mois civil précédent.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {
        if ($end->lessThan($start)) {
            throw new InvalidArgumentException('La fin de période précède son début.');
        }
    }

    /**
     * Mois civil précédant l'instant donné (par défaut maintenant).
     */
    public static function previousMonth(?CarbonInterface $now = null): self
    {
        $reference = CarbonImmutable::instance($now ?? now())->subMonthNoOverflow();

        return self::month($reference->year, $reference->month);
    }

    public static function month(int $year, int $month): self
    {
        if ($month < 1 || $month > 12) {
            throw new InvalidArgumentException(sprintf('Mois invalide : %d.', $month));
        }

        $start = CarbonImmutable::create($year, $month, 1)->startOfDay();

        return new self($start, $start->endOfMonth()->startOfDay());
    }

    /**
     * Depuis « AAAA-MM » (option --month).
     */
    public static function fromMonthString(string $value): self
    {
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', trim($value), $matches) !== 1) {
            throw new InvalidArgumentException('Le mois attendu est au format AAAA-MM (exemple : 2026-09).');
        }

        return self::month((int) $matches[1], (int) $matches[2]);
    }

    /** Début de période à la première seconde (filtres sur les horodatages). */
    public function startAt(): CarbonImmutable
    {
        return $this->start->startOfDay();
    }

    /** Fin de période à la dernière seconde (filtres sur les horodatages). */
    public function endAt(): CarbonImmutable
    {
        return $this->end->endOfDay();
    }

    /** Libellé court « 09/2026 » (mois civil) ou « 01/09/2026 – 15/09/2026 ». */
    public function label(): string
    {
        if ($this->start->day === 1 && $this->end->equalTo($this->start->endOfMonth()->startOfDay())) {
            return $this->start->format('m/Y');
        }

        return $this->start->format('d/m/Y').' – '.$this->end->format('d/m/Y');
    }
}
