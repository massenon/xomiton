<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\OtpService;
use App\Application\Auth\RegisterData;
use App\Models\User;
use App\Rules\Pseudo;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Phase 1 de l'inscription (D4, Q12 c, Q30) : pseudonyme, e-mail, mot de passe « Fort » et les deux consentements
 * obligatoires, cases distinctes jamais pré-cochées.
 */
final class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge([
            'pseudo' => is_string($this->input('pseudo')) ? trim($this->input('pseudo')) : $this->input('pseudo'),
            'email' => is_string($email) ? OtpService::normalizeEmail($email) : $email,
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'pseudo' => ['required', 'string', new Pseudo],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'max:255', new StrongPassword],
            'cgu' => ['accepted'],
            'privacy' => ['accepted'],
        ];
    }

    /**
     * Même texte que l'API (`auth.api.email_taken`) : il dit quoi faire (se connecter ou « Mot de passe oublié »).
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.unique' => (string) __('auth.api.email_taken'),
        ];
    }

    public function toRegisterData(): RegisterData
    {
        return new RegisterData(
            pseudo: (string) $this->string('pseudo'),
            email: (string) $this->string('email'),
            password: (string) $this->string('password'),
            acceptsTerms: $this->boolean('cgu'),
            acceptsPrivacy: $this->boolean('privacy'),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
        );
    }
}
