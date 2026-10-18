<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** `GET urgences?zone=` (P5-06) et page `/urgences` (P4-06) : département facultatif (identifiant d'une zone active). */
final class EmergencyContactsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'zone' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
        ];
    }

    public function zone(): ?Zone
    {
        return $this->filled('zone') ? Zone::query()->find((int) $this->query('zone')) : null;
    }
}
