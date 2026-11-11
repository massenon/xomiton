<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use App\Application\Auth\OtpService;
use App\Enums\MemberType;
use App\Models\User;
use App\Rules\CommuneInZone;
use App\Rules\EmailAddress;
use App\Rules\PhoneBj;
use App\Rules\Pseudo;
use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/auth/register (P2-03, Q12 c) : phase 1 obligatoire (pseudo, e-mail, mot de passe, cases CGU + charte et
 * confidentialité distinctes), phases 2-3 facultatives dans `profile`, phase 4 facultative dans `consents`.
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
            'pseudo' => ['required', 'string', new Pseudo],
            'email' => ['required', 'string', 'max:190', new EmailAddress, Rule::unique(User::class, 'email')],
            'password' => ['required', 'string', 'max:255', new StrongPassword],
            'phone' => ['nullable', 'string', new PhoneBj],
            'cgu' => ['required', 'accepted'],
            'privacy' => ['required', 'accepted'],
            // Q68 : absent ou faux → 422 `age_confirmation_required` (levé par RegisterUser, code stable), pas `validation_failed`.
            'age_confirmed' => ['nullable', 'boolean'],
            // Lien / code d'invitation à l'équipe : l'inscription reste celle d'un membre, le rôle suit à la validation.
            'invitation' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:100'],
            'profile' => ['sometimes', 'array'],
            'profile.pronouns' => ['nullable', 'string', 'max:30'],
            'profile.zone_id' => ['nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true), 'required_with:profile.commune_id'],
            'profile.commune_id' => ['nullable', 'integer', new CommuneInZone('profile.zone_id')],
            'profile.member_type' => ['nullable', Rule::in([MemberType::Member->value])],
            'profile.trusted_contact_phone' => ['nullable', 'string', new PhoneBj],
            'profile.trusted_contact_name' => ['nullable', 'string', 'max:80'],
            'profile.discreet_mode' => ['nullable', 'boolean'],
            'consents' => ['sometimes', 'array'],
            'consents.sensitive_data' => ['nullable', 'boolean'],
            'consents.newsletter' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'profile.pronouns' => __('validation.attributes.pronouns'),
            'profile.zone_id' => __('validation.attributes.zone_id'),
            'profile.commune_id' => __('validation.attributes.commune_id'),
            'profile.member_type' => __('validation.attributes.member_type'),
            'profile.trusted_contact_phone' => __('validation.attributes.trusted_contact_phone'),
            'profile.trusted_contact_name' => __('validation.attributes.trusted_contact_name'),
            'consents.sensitive_data' => __('validation.attributes.sensitive_data'),
            'consents.newsletter' => __('validation.attributes.newsletter'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cgu.required' => __('validation.custom.cgu.accepted'),
            'privacy.required' => __('validation.custom.privacy.accepted'),
            'email.unique' => __('auth.api.email_taken'),
        ];
    }
}
