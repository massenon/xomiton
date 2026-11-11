<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 4 (facultative) : consentements données sensibles et newsletter, cases jamais pré-cochées.
 */
final class RegistrationConsentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'sensitive_data' => ['nullable', 'boolean'],
            'newsletter' => ['nullable', 'boolean'],
        ];
    }
}
