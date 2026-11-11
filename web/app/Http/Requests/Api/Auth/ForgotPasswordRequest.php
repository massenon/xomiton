<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\OtpService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/password/forgot : adresse e-mail seule ; la réponse est identique que le compte existe ou non.
 */
final class ForgotPasswordRequest extends FormRequest
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
        ];
    }

    public function email(): string
    {
        return (string) $this->validated('email');
    }
}
