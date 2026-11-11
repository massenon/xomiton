<?php

declare(strict_types=1);

namespace App\Domain\Cases;

use App\Enums\AgeRange;
use DateTimeInterface;

/**
 * Bornes de plausibilité (règle `plausibility` du catalogue, `validation_rules.params` : `age_min`, `age_max`,
 * `max_past_years`, `lat`, `lng`) et règles qui en dépendent :
 *
 *  - RG-07 : une date postérieure à aujourd'hui est refusée (règle `date_not_future`) ; une date de plus de
 *    `max_past_years` ans (5 par défaut) déclenche un **avertissement**, jamais un refus ;
 *  - RG-08 (Q25 a) : la tranche d'âge (`AgeRange`, jamais un âge exact) doit être comprise entre `age_min` et
 *    `age_max` (10 et 99 par défaut : toutes les tranches de 10-14 à 50+) — refus porté par la règle `age_range` ;
 *  - coordonnées GPS dans le cadre du Bénin (avertissement, pour les alertes géolocalisées).
 *
 * Pur : les paramètres viennent de l'Application (App\Application\Cases\ValidationCatalog). Paramètre absent ou
 * illisible → valeur par défaut, jamais un contrôle relâché.
 */
final class Plausibility
{
    public const DEFAULT_AGE_MIN = 10;

    public const DEFAULT_AGE_MAX = 99;

    public const DEFAULT_MAX_PAST_YEARS = 5;

    /** Cadre du Bénin (latitude, longitude), seeder du catalogue. */
    public const DEFAULT_LAT = [6.2, 12.5];

    public const DEFAULT_LNG = [0.7, 3.9];

    /**
     * @param  array{0: float, 1: float}  $lat
     * @param  array{0: float, 1: float}  $lng
     */
    public function __construct(
        public readonly int $ageMin = self::DEFAULT_AGE_MIN,
        public readonly int $ageMax = self::DEFAULT_AGE_MAX,
        public readonly int $maxPastYears = self::DEFAULT_MAX_PAST_YEARS,
        public readonly array $lat = self::DEFAULT_LAT,
        public readonly array $lng = self::DEFAULT_LNG,
    ) {}

    /**
     * @param  array<string, mixed>|null  $params
     */
    public static function fromParams(?array $params, int $defaultMaxPastYears = self::DEFAULT_MAX_PAST_YEARS): self
    {
        $params ??= [];
        $ageMin = self::int($params['age_min'] ?? null, self::DEFAULT_AGE_MIN, 0);
        $ageMax = self::int($params['age_max'] ?? null, self::DEFAULT_AGE_MAX, 0);

        if ($ageMax < $ageMin) {
            [$ageMin, $ageMax] = [self::DEFAULT_AGE_MIN, self::DEFAULT_AGE_MAX];
        }

        return new self(
            ageMin: $ageMin,
            ageMax: $ageMax,
            maxPastYears: self::int($params['max_past_years'] ?? null, max(1, $defaultMaxPastYears), 1),
            lat: self::range($params['lat'] ?? null, self::DEFAULT_LAT),
            lng: self::range($params['lng'] ?? null, self::DEFAULT_LNG),
        );
    }

    /** RG-07 : date postérieure au jour donné (comparaison par jour calendaire, `Y-m-d`). */
    public static function isFuture(DateTimeInterface $date, DateTimeInterface $today): bool
    {
        return $date->format('Y-m-d') > $today->format('Y-m-d');
    }

    /** RG-07 : date antérieure de plus de `maxPastYears` ans au jour donné (avertissement). */
    public function isTooOld(DateTimeInterface $date, DateTimeInterface $today): bool
    {
        $limit = (int) $today->format('Y') - $this->maxPastYears;
        $threshold = sprintf('%04d-%s', $limit, $today->format('m-d'));

        return $date->format('Y-m-d') < $threshold;
    }

    /** RG-08 (Q25 a) : la tranche entière tient dans [`age_min`, `age_max`] ; la tranche ouverte « 50+ » par son bas. */
    public function acceptsAgeRange(AgeRange $range): bool
    {
        $upper = $range->upperBound() ?? $range->lowerBound();

        return $range->lowerBound() >= $this->ageMin && $upper <= $this->ageMax;
    }

    /**
     * Tranches admises par les bornes, dans l'ordre de l'énumération (liste déroulante du client).
     *
     * @return list<AgeRange>
     */
    public function acceptedAgeRanges(): array
    {
        return array_values(array_filter(AgeRange::cases(), fn (AgeRange $range): bool => $this->acceptsAgeRange($range)));
    }

    /** Coordonnées dans le cadre du Bénin (bornes incluses). */
    public function acceptsCoordinates(float $lat, float $lng): bool
    {
        return $lat >= $this->lat[0] && $lat <= $this->lat[1] && $lng >= $this->lng[0] && $lng <= $this->lng[1];
    }

    /**
     * @return array{age_min: int, age_max: int, max_past_years: int, lat: array{0: float, 1: float}, lng: array{0: float, 1: float}}
     */
    public function toArray(): array
    {
        return [
            'age_min' => $this->ageMin,
            'age_max' => $this->ageMax,
            'max_past_years' => $this->maxPastYears,
            'lat' => $this->lat,
            'lng' => $this->lng,
        ];
    }

    private static function int(mixed $value, int $default, int $min): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT);

        return $int === false || $int < $min ? $default : (int) $int;
    }

    /**
     * @param  array{0: float, 1: float}  $default
     * @return array{0: float, 1: float}
     */
    private static function range(mixed $value, array $default): array
    {
        if (! is_array($value) || count($value) !== 2) {
            return $default;
        }

        [$low, $high] = array_values($value);

        if (! is_numeric($low) || ! is_numeric($high) || (float) $low > (float) $high) {
            return $default;
        }

        return [(float) $low, (float) $high];
    }
}
