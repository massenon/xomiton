<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Enums\OtpPurpose;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Bouton « Renvoyer le code » (écran OTP, bandeau « Confirme ton e-mail »). */
final class ResendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(OtpPurpose::class)],
        ];
    }

    public function purpose(): OtpPurpose
    {
        return OtpPurpose::from((string) $this->string('purpose'));
    }
}
