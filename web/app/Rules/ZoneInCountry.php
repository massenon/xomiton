<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Zone;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La zone choisie doit être active et appartenir au pays choisi (zones de tout pays, décision client du 11/11/2026) : une
 * zone d'un autre pays est refusée. Usage : `'zone_id' => ['nullable', 'integer', new ZoneInCountry('country_code')]`.
 */
final class ZoneInCountry implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $countryField = 'country_code') {}

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
        $country = data_get($this->data, $this->countryField);

        if (! is_numeric($value) || ! is_string($country) || $country === '') {
            $fail('validation.zone_in_country')->translate();

            return;
        }

        $matches = Zone::query()
            ->whereKey((int) $value)
            ->inCountry($country)
            ->where('is_active', true)
            ->exists();

        if (! $matches) {
            $fail('validation.zone_in_country')->translate();
        }
    }
}
