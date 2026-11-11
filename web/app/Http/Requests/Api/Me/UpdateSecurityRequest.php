<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH /api/v1/me/security (P6-06) : `discreet`, `mask_previews`, `pin_enabled`, `neutral_tab_title` (booléens,
 * chaque champ absent est laissé tel quel). Q37 b : un code PIN envoyé (`pin`, `pin_code`, `pin_hash`) est refusé en
 * 422 — il ne quitte jamais l'appareil.
 */
final class UpdateSecurityRequest extends FormRequest
{
    /** Champs qui porteraient un code PIN : interdits. */
    public const PIN_FIELDS = ['pin', 'pin_code', 'pin_hash'];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'discreet' => ['sometimes', 'boolean'],
            'mask_previews' => ['sometimes', 'boolean'],
            'pin_enabled' => ['sometimes', 'boolean'],
            'neutral_tab_title' => ['sometimes', 'boolean'],
        ];

        foreach (self::PIN_FIELDS as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];

        foreach (self::PIN_FIELDS as $field) {
            $messages[$field.'.prohibited'] = __('account.security.pin_never_sent');
        }

        return $messages;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'discreet' => __('profile.settings.security.discreet_mode'),
            'mask_previews' => __('profile.settings.security.mask_notifications'),
            'pin_enabled' => __('profile.settings.security.pin_enable'),
            'neutral_tab_title' => __('profile.settings.security.neutral_tab_title'),
        ];
    }

    /**
     * @return array{discreet?: bool, mask_previews?: bool, pin_enabled?: bool, neutral_tab_title?: bool}
     */
    public function toSecurity(): array
    {
        $data = [];

        foreach (['discreet', 'mask_previews', 'pin_enabled', 'neutral_tab_title'] as $field) {
            if ($this->has($field)) {
                $data[$field] = $this->boolean($field);
            }
        }

        /** @var array{discreet?: bool, mask_previews?: bool, pin_enabled?: bool, neutral_tab_title?: bool} $data */
        return $data;
    }
}
