<?php

declare(strict_types=1);

namespace App\Rules;

use App\Domain\Cases\Plausibility;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Throwable;

/**
 * Date non postérieure à aujourd'hui (règle `date_not_future` du catalogue, RG-07). « Aujourd'hui » est le jour
 * calendaire du Bénin (`xomiton.display_timezone`, UTC+1 ; la base reste en UTC) : une collecte saisie à 00 h 30 à
 * Cotonou n'est pas « dans le futur ». À placer après une règle de format (`date_format:Y-m-d`) : une valeur
 * illisible est laissée à celle-ci.
 */
final class NotFutureDate implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        try {
            $date = CarbonImmutable::parse($value);
        } catch (Throwable) {
            return;
        }

        if (Plausibility::isFuture($date, self::today())) {
            $fail('validation.date_not_future')->translate();
        }
    }

    /** Jour calendaire local (fuseau d'affichage, sinon celui de l'application). */
    public static function today(): CarbonImmutable
    {
        $timezone = (string) config('xomiton.display_timezone', '');

        return CarbonImmutable::now($timezone !== '' ? $timezone : (string) config('app.timezone', 'UTC'))->startOfDay();
    }
}
