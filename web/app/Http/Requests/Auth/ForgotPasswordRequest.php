<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\OtpService;
use Illuminate\Foundation\Http\FormRequest;

/** Formulaire /mot-de-passe-oublie : une adresse e-mail, réponse identique quel que soit le compte (Q8 c). */
final class ForgotPasswordRequest extends FormRequest
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
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
        ];
    }
}
