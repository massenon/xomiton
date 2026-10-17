<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use IntlDateFormatter;
use NumberFormatter;
use Throwable;

/**
 * Formatage des dates, heures et nombres selon VOICE_AND_TONE.md §6 et NFR-11 : texte « 2 novembre 2026 », « 15 h »,
 * tableaux « 02/11/2026 », « 15:00 », nombres « 1 234 » (espace fine insécable), pourcentages « 96 % ».
 * Locale intl `fr-BJ` avec repli `fr-FR` (App\Support\Locales), motifs ICU déclarés dans lang/<locale>/dates.php,
 * mêmes conventions que `Intl.DateTimeFormat('fr-BJ')` côté Vue et `intl` (`fr_BJ`) côté Flutter.
 * Usage : `LocaleFormatter::make()->longDate($post->created_at)` ; jamais de `->format()` brut dans une vue.
 */
final class LocaleFormatter
{
    /** Espace insécable (unités : « 15 h », « 96 % », « ± 5 m »). */
    public const NBSP = "\u{00A0}";

    /** Espace fine insécable (séparateur de milliers : « 1 234 »). */
    public const NNBSP = "\u{202F}";

    /** Motifs par défaut si lang/<locale>/dates.php ne les définit pas. */
    private const DEFAULT_FORMATS = [
        'long' => 'd MMMM y',
        'long_with_day' => 'EEEE d MMMM y',
        'medium' => 'd MMM y',
        'short' => 'dd/MM/y',
        'day_month' => 'd MMMM',
        'day_month_short' => 'dd/MM',
        'month_year' => 'MMMM y',
        'time_text' => "H\u{00A0}'h'",
        'time_text_minutes' => "H\u{00A0}'h'\u{00A0}mm",
        'time_table' => 'HH:mm',
        'datetime_text' => "d MMMM y 'à' H\u{00A0}'h'",
        'datetime_text_minutes' => "d MMMM y 'à' H\u{00A0}'h'\u{00A0}mm",
        'datetime_table' => 'dd/MM/y HH:mm',
        'iso_date' => 'yyyy-MM-dd',
    ];

    /** @var array<string, IntlDateFormatter> */
    private array $dateFormatters = [];

    /** @var array<int, NumberFormatter> */
    private array $numberFormatters = [];

    private readonly DateTimeZone $timezone;

    public function __construct(
        private readonly string $locale,
        private readonly string $intlTag,
        DateTimeZone|string|null $timezone = null,
    ) {
        $this->timezone = $timezone instanceof DateTimeZone
            ? $timezone
            : new DateTimeZone($timezone ?? (string) config('app.timezone', 'UTC'));
    }

    /**
     * Formateur pour la locale donnée (par défaut la locale de l'application) et le fuseau d'affichage
     * (par défaut `config('app.timezone')`).
     */
    public static function make(?string $locale = null, DateTimeZone|string|null $timezone = null): self
    {
        $locale ??= app()->getLocale();

        return new self($locale, Locales::intlTag($locale), $timezone);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function intlTag(): string
    {
        return $this->intlTag;
    }

    // --- Dates -----------------------------------------------------------------------------------------------------

    /** « 2 novembre 2026 ». */
    public function longDate(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('long'), $date);
    }

    /** « lundi 2 novembre 2026 ». */
    public function longDateWithDay(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('long_with_day'), $date);
    }

    /** « 2 nov. 2026 ». */
    public function mediumDate(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('medium'), $date);
    }

    /** « 02/11/2026 » (tableaux). */
    public function shortDate(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('short'), $date);
    }

    /** « 2 novembre ». */
    public function dayMonth(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('day_month'), $date);
    }

    /** « 02/11 ». */
    public function dayMonthShort(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('day_month_short'), $date);
    }

    /** « novembre 2026 ». */
    public function monthYear(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('month_year'), $date);
    }

    /** « 2026-11-02 » (attributs `datetime`, API). */
    public function isoDate(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('iso_date'), $date);
    }

    // --- Heures ----------------------------------------------------------------------------------------------------

    /** « 15 h » ou « 15 h 30 » (texte). */
    public function time(DateTimeInterface|string|int|null $date): string
    {
        $carbon = $this->toDate($date);

        if ($carbon === null) {
            return '';
        }

        return $this->pattern($this->format($carbon->minute === 0 ? 'time_text' : 'time_text_minutes'), $carbon);
    }

    /** « 15:00 » (tableaux). */
    public function tableTime(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('time_table'), $date);
    }

    /** « 2 novembre 2026 à 15 h » (texte). */
    public function dateTime(DateTimeInterface|string|int|null $date): string
    {
        $carbon = $this->toDate($date);

        if ($carbon === null) {
            return '';
        }

        return $this->pattern($this->format($carbon->minute === 0 ? 'datetime_text' : 'datetime_text_minutes'), $carbon);
    }

    /** « 02/11/2026 15:00 » (tableaux, journal). */
    public function tableDateTime(DateTimeInterface|string|int|null $date): string
    {
        return $this->pattern($this->format('datetime_table'), $date);
    }

    /**
     * Formate selon un motif ICU quelconque (IntlDateFormatter), dans le fuseau d'affichage. Chaîne vide si la date
     * est nulle ou illisible : une vue n'affiche jamais « 01/01/1970 ».
     */
    public function pattern(string $pattern, DateTimeInterface|string|int|null $date): string
    {
        $carbon = $this->toDate($date);

        if ($carbon === null) {
            return '';
        }

        $formatted = $this->dateFormatter($pattern)->format($carbon);

        return $formatted === false ? '' : $formatted;
    }

    /**
     * Formulation relative du prototype (`x_ago()`) : « à l’instant », « il y a 5 min », « il y a 3 h »,
     * « il y a 2 j » puis la date courte au-delà de sept jours. Dates futures : « dans :count … ».
     */
    public function ago(DateTimeInterface|string|int|null $date, ?DateTimeInterface $now = null): string
    {
        $carbon = $this->toDate($date);

        if ($carbon === null) {
            return '';
        }

        $reference = $now !== null ? CarbonImmutable::instance($now) : CarbonImmutable::now($this->timezone);
        $seconds = $reference->getTimestamp() - $carbon->getTimestamp();
        $future = $seconds < 0;
        $seconds = abs($seconds);

        if ($seconds < 60) {
            return $this->line('dates.relative.now', 'à l’instant');
        }

        if ($seconds < 3600) {
            $count = (int) floor($seconds / 60);

            return $this->choice($future ? 'dates.relative.in_minutes' : 'dates.relative.minutes', $count, $future ? 'dans :count min' : 'il y a :count min');
        }

        if ($seconds < 86400) {
            $count = (int) floor($seconds / 3600);

            return $this->choice($future ? 'dates.relative.in_hours' : 'dates.relative.hours', $count, $future ? 'dans :count h' : 'il y a :count h');
        }

        if ($seconds < 7 * 86400) {
            $count = (int) floor($seconds / 86400);

            return $this->choice($future ? 'dates.relative.in_days' : 'dates.relative.days', $count, $future ? 'dans :count j' : 'il y a :count j');
        }

        return $this->shortDate($carbon);
    }

    /** Nom du mois (1–12) depuis lang/<locale>/dates.php, sinon ICU. */
    public function monthName(int $month, bool $short = false): string
    {
        $key = 'dates.'.($short ? 'months_short' : 'months').'.'.$month;
        $line = $this->line($key, '');

        if ($line !== '') {
            return $line;
        }

        return $this->pattern($short ? 'MMM' : 'MMMM', CarbonImmutable::create(2026, max(1, min(12, $month)), 1, 12, 0, 0, $this->timezone));
    }

    /** Nom du jour (ISO 8601 : 1 = lundi … 7 = dimanche) depuis lang/<locale>/dates.php, sinon ICU. */
    public function dayName(int $isoDay, bool $short = false): string
    {
        $key = 'dates.'.($short ? 'days_short' : 'days').'.'.$isoDay;
        $line = $this->line($key, '');

        if ($line !== '') {
            return $line;
        }

        // Le 5 janvier 2026 est un lundi.
        return $this->pattern($short ? 'EEE' : 'EEEE', CarbonImmutable::create(2026, 1, 4 + max(1, min(7, $isoDay)), 12, 0, 0, $this->timezone));
    }

    // --- Nombres ---------------------------------------------------------------------------------------------------

    /**
     * « 1 234 », « 1 234,5 » : séparateur de milliers normalisé en espace fine insécable (U+202F) quelle que soit la
     * version d'ICU, virgule décimale selon la locale.
     */
    public function number(int|float $value, int $decimals = 0): string
    {
        $formatted = $this->numberFormatter($decimals)->format($value);

        if ($formatted === false) {
            return number_format($value, $decimals, ',', self::NNBSP);
        }

        return str_replace(self::NBSP, self::NNBSP, $formatted);
    }

    /** « 96 % » (espace insécable avant le symbole). */
    public function percent(int|float $value, int $decimals = 0): string
    {
        return $this->number($value, $decimals).self::NBSP.'%';
    }

    /** « 850 Ko », « 1,2 Mo » (libellés d'unités dans lang/<locale>/dates.php). */
    public function fileSize(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return $this->line('dates.units.megabytes', ':value Mo', ['value' => $this->number($bytes / (1024 * 1024), 1)]);
        }

        return $this->line('dates.units.kilobytes', ':value Ko', ['value' => $this->number((int) max(1, round($bytes / 1024)))]);
    }

    /** « ± 12 m ». */
    public function accuracy(int|float $meters): string
    {
        return $this->line('dates.units.accuracy', '± :value m', ['value' => $this->number($meters)]);
    }

    // --- Internes --------------------------------------------------------------------------------------------------

    private function format(string $name): string
    {
        return $this->line('dates.formats.'.$name, self::DEFAULT_FORMATS[$name] ?? $name);
    }

    /**
     * Ligne de traduction dans la locale du formateur, avec repli explicite si la clé n'existe pas.
     *
     * @param  array<string, string|int|float>  $replace
     */
    private function line(string $key, string $default, array $replace = []): string
    {
        $line = __($key, $replace, $this->locale);

        if (! is_string($line) || $line === $key) {
            return strtr($default, array_combine(
                array_map(static fn (string $k): string => ':'.$k, array_keys($replace)),
                array_map('strval', $replace),
            ));
        }

        return $line;
    }

    private function choice(string $key, int $count, string $default): string
    {
        $line = trans_choice($key, $count, ['count' => $count], $this->locale);

        if ($line === $key) {
            return str_replace(':count', (string) $count, $default);
        }

        return $line;
    }

    private function toDate(DateTimeInterface|string|int|null $date): ?CarbonImmutable
    {
        if ($date === null || $date === '') {
            return null;
        }

        try {
            if ($date instanceof DateTimeInterface) {
                $carbon = CarbonImmutable::instance($date);
            } elseif (is_int($date)) {
                $carbon = CarbonImmutable::createFromTimestamp($date, 'UTC');
            } else {
                $carbon = CarbonImmutable::parse($date, 'UTC');
            }
        } catch (Throwable) {
            return null;
        }

        return $carbon->setTimezone($this->timezone);
    }

    private function dateFormatter(string $pattern): IntlDateFormatter
    {
        return $this->dateFormatters[$pattern] ??= new IntlDateFormatter(
            $this->intlTag,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $this->timezone,
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );
    }

    private function numberFormatter(int $decimals): NumberFormatter
    {
        if (! isset($this->numberFormatters[$decimals])) {
            $formatter = new NumberFormatter($this->intlTag, NumberFormatter::DECIMAL);
            $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, $decimals);
            $formatter->setAttribute(NumberFormatter::GROUPING_USED, 1);
            $this->numberFormatters[$decimals] = $formatter;
        }

        return $this->numberFormatters[$decimals];
    }
}
