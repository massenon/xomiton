<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\RegisterData;
use App\Http\Requests\Support\RegistrationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /inscription : l'unique envoi du formulaire en 3 étapes (décision client du 11/11/2026). Règles, messages et
 * normalisation : App\Http\Requests\Support\RegistrationRules, les mêmes que pour POST /api/v1/auth/register.
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
        return RegistrationRules::rules();
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
