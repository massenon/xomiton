<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\ProfileData;
use App\Rules\PhoneBj;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Phase 3 (facultative) : contact de confiance (téléphone béninois, chiffré) et mode discret.
 */
final class RegistrationSecurityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'trusted_contact_name' => ['nullable', 'string', 'max:80'],
            'trusted_contact_phone' => ['nullable', 'string', 'max:30', new PhoneBj],
            'discreet_mode' => ['nullable', 'boolean'],
        ];
    }

    public function toProfileData(): ProfileData
    {
        return new ProfileData(
            trustedContactPhone: $this->filled('trusted_contact_phone') ? (string) $this->string('trusted_contact_phone') : null,
            trustedContactName: $this->filled('trusted_contact_name') ? (string) $this->string('trusted_contact_name') : null,
            discreetMode: $this->boolean('discreet_mode'),
        );
    }
}
