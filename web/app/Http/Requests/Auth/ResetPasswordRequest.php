<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\OtpService;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire /nouveau-mot-de-passe : nouveau mot de passe confirmé, preuve = lien signé (champs cachés `email` +
 * `token`) ou code saisi à l'écran précédent (session).
 */
final class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        $this->merge(['email' => is_string($email) ? OtpService::normalizeEmail($email) : $email]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email:rfc', 'max:190', 'required_with:token'],
            'token' => ['nullable', 'string', 'max:500'],
            'password' => ['required', 'string', 'max:255', 'confirmed', new StrongPassword],
        ];
    }
}
