<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Commune;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * La commune choisie doit appartenir au département sélectionné (règle `commune_in_zone`, liste en cascade).
 * Usage : `'commune_id' => ['nullable', 'integer', new CommuneInZone('zone_id')]`.
 */
final class CommuneInZone implements DataAwareRule, ValidationRule
{
    /** @var array<string, mixed> */
    private array $data = [];

    public function __construct(private readonly string $zoneField = 'zone_id') {}

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
        $zoneId = data_get($this->data, $this->zoneField);

        if (! is_numeric($value) || ! is_numeric($zoneId)) {
            $fail('validation.commune_in_zone')->translate();

            return;
        }

        $matches = Commune::query()
            ->whereKey((int) $value)
            ->where('zone_id', (int) $zoneId)
            ->where('is_active', true)
            ->exists();

        if (! $matches) {
            $fail('validation.commune_in_zone')->translate();
        }
    }
}
