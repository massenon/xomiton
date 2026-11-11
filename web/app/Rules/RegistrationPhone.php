<?php

declare(strict_types=1);

namespace App\Rules;

use App\Application\Shared\Countries;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Téléphone facultatif de l'inscription : numéro béninois (règle PhoneBj) quand le pays est le Bénin, sinon format
 * international « +» suivi de 8 à 15 chiffres (espaces, points, tirets et parenthèses tolérés à la saisie).
 * `format()` renvoie la forme à enregistrer.
 */
final class RegistrationPhone implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('validation.phone_international')->translate();

            return;
        }

        $country = data_get($this->data, 'country_code');

        if ($country === Countries::BENIN) {
            (new PhoneBj)->validate($attribute, $value, $fail);

            return;
        }

        if (self::normalizeInternational($value) === null) {
            $fail('validation.phone_international')->translate();
        }
    }

    /**
     * Valeur à enregistrer après validation : forme canonique béninoise (Bénin), sinon « +chiffres ».
     */
    public static function format(string $value, ?string $country): string
    {
        if ($country === Countries::BENIN) {
            return PhoneBj::format($value);
        }

        return self::normalizeInternational($value) ?? trim($value);
    }

    /**
     * « +33612345678 » pour un numéro international valide (« + » puis 8 à 15 chiffres, premier chiffre non nul), sinon null.
     */
    public static function normalizeInternational(string $value): ?string
    {
        $compact = preg_replace('/[\s.\-()]/', '', trim($value));

        if ($compact === null || preg_match('/^\+[1-9]\d{7,14}$/', $compact) !== 1) {
            return null;
        }

        return $compact;
    }
}
