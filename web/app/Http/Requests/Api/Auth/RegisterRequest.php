<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\RegisterData;
use App\Http\Requests\Support\RegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/register : l'unique envoi de l'inscription en 3 étapes (décision client du 11/11/2026). Les règles,
 * messages et clés d'erreur sont ceux de la requête web (App\Http\Requests\Support\RegistrationRules) ; seul
 * `device_name` (nom de l'appareil du jeton) est propre à l'API.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(RegistrationRules::normalize($this->all()));
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return RegistrationRules::rules() + [
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RegistrationRules::messages();
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return RegistrationRules::attributes();
    }

    public function toRegisterData(): RegisterData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return RegistrationRules::toData($validated, $this->ip(), $this->userAgent());
    }
}
