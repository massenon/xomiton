<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\OtpService;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/password/reset : adresse, preuve (`reset_token` délivré par otp/verify, jeton du lien magique ou code
 * à 6 chiffres), nouveau mot de passe confirmé (Q30 a).
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

        if (is_string($email)) {
            $this->merge(['email' => OtpService::normalizeEmail($email)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email:rfc', 'max:190'],
            'reset_token' => ['required', 'string', 'max:500'],
            'password' => ['required', 'string', 'max:255', 'confirmed', new StrongPassword],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['reset_token' => __('auth.api.attributes.reset_token')];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }

    public function proof(): string
    {
        return trim((string) $this->validated('reset_token'));
    }

    public function password(): string
    {
        return (string) $this->validated('password');
    }
}
