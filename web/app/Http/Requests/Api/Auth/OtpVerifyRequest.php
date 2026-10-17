<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\OtpService;
use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/auth/otp/verify : `purpose`, `email`, `code` (6 chiffres, collage toléré) et `device_name` pour nommer
 * le jeton délivré après un OTP de connexion.
 */
final class OtpVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];
        $email = $this->input('email');
        $code = $this->input('code');

        if (is_string($email)) {
            $merge['email'] = OtpService::normalizeEmail($email);
        }

        if (is_string($code)) {
            $merge['code'] = preg_replace('/\D/', '', $code) ?? $code;
        }

        $this->merge($merge);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $length = max(4, (int) config('xomiton.otp.length', 6));

        return [
            'purpose' => ['required', Rule::enum(OtpPurpose::class)],
            'email' => [$this->user('sanctum') === null ? 'required' : 'nullable', 'string', 'email:rfc', 'max:190'],
            'code' => ['required', 'string', 'digits:'.$length],
            'device_name' => ['nullable', 'string', 'max:100'],
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

    public function code(): string
    {
        return (string) $this->validated('code');
    }
}
