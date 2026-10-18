<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Écran /code-de-verification (LOGIN §5) : 6 cases numériques `code[]` (ou un seul champ `code` collé), usage
 * login | signup | reset.
 */
final class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $code = $this->input('code');

        if (is_array($code)) {
            $code = implode('', array_map(static fn (mixed $d): string => is_scalar($d) ? (string) $d : '', $code));
        }

        // Code collé dans la première case sans JavaScript (« 482 913 », « 482-913 », « 482.913 ») : séparateurs retirés,
        // comme le fait resources/js/public/auth.js (LOGIN §5, WCAG 3.3.8 : collage autorisé).
        $this->merge(['code' => is_string($code) ? preg_replace('/[\s\x{00A0}\x{202F}.\-]+/u', '', $code) : $code]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(OtpPurpose::class)],
            'code' => ['required', 'string', 'digits:'.max(4, (int) config('xomiton.otp.length', 6))],
        ];
    }

    public function purpose(): OtpPurpose
    {
        return OtpPurpose::from((string) $this->string('purpose'));
    }

    public function code(): string
    {
        return (string) $this->string('code');
    }
}
