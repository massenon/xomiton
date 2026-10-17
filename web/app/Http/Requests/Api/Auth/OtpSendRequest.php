<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\OtpService;
use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/auth/otp/send : renvoi d'un code (`purpose` login | signup | reset). L'adresse est facultative quand la
 * requête porte un jeton : c'est alors celle du compte connecté.
 */
final class OtpSendRequest extends FormRequest
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
            'purpose' => ['required', Rule::enum(OtpPurpose::class)],
            'email' => [$this->user('sanctum') === null ? 'required' : 'nullable', 'string', 'email:rfc', 'max:190'],
        ];
    }

    public function purpose(): OtpPurpose
    {
        return OtpPurpose::from((string) $this->validated('purpose'));
    }

    public function email(): string
    {
        $email = $this->validated('email');

        if (is_string($email) && $email !== '') {
            return $email;
        }

        return OtpService::normalizeEmail((string) $this->user('sanctum')?->email);
    }
}
