<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts\Concerns;

use App\Models\User;
use Illuminate\Validation\Rule;

/**
 * Lecture commune des charges utiles d'alerte (API P5-06, web P5-04/P5-05, mobile) : clé `Idempotency-Key` lue dans
 * l'en-tête (ADR-32) ou le corps, booléens normalisés (formulaire ou JSON), compte web ou Sanctum.
 */
trait ReadsAlertPayload
{
    /**
     * @param  list<string>  $booleans
     */
    protected function normalizeAlertPayload(array $booleans = [], array $defaults = []): void
    {
        $merge = [];
        $header = trim((string) $this->headers->get('Idempotency-Key', ''));

        if ($header !== '' && ! $this->filled('idempotency_key')) {
            $merge['idempotency_key'] = $header;
        }

        if (is_string($this->input('idempotency_key'))) {
            $merge['idempotency_key'] = strtolower(trim((string) ($merge['idempotency_key'] ?? $this->input('idempotency_key'))));
        }

        foreach ($booleans as $field) {
            if ($this->has($field)) {
                $merge[$field] = $this->boolean($field);
            } elseif (array_key_exists($field, $defaults)) {
                $merge[$field] = $defaults[$field];
            }
        }

        $this->merge($merge);
    }

    protected function currentUser(): ?User
    {
        $user = $this->user() ?? $this->user('sanctum');

        return $user instanceof User ? $user : null;
    }

    /**
     * Position facultative : latitude, longitude (ensemble) et précision en mètres.
     *
     * @return array<string, list<mixed>>
     */
    protected function locationRules(): array
    {
        return [
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'accuracy' => ['nullable', 'integer', 'between:0,65535'],
        ];
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function needsRules(): array
    {
        return [
            'needs.*' => ['string', 'distinct', Rule::exists('needs', 'slug')->where('is_active', true)],
        ];
    }

    /**
     * Noms des éléments de liste (`needs.*`, `attachments.*`) : clés au singulier dans validation.attributes, une clé
     * à joker y entrerait en collision avec la liste dans l'export ARB (lang:export).
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'needs.*' => (string) __('validation.attributes.need'),
            'attachments.*' => (string) __('validation.attributes.attachment'),
        ];
    }

    protected function floatOrNull(string $key): ?float
    {
        $value = $this->input($key);

        return is_numeric($value) ? (float) $value : null;
    }

    protected function intOrNull(string $key): ?int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function stringOrNull(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @return list<string>
     */
    protected function needSlugs(): array
    {
        $needs = $this->input('needs', []);

        return is_array($needs) ? array_values(array_filter($needs, 'is_string')) : [];
    }
}
