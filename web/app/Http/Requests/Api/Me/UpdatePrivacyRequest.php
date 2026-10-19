<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Enums\ProfileVisibility;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PATCH /api/v1/me/privacy (P8-09, prototype settings.php « Confidentialité ») : `visibility` (ProfileVisibility),
 * `show_zone`, `searchable`, `sos_share_gps`, `sensitive_consent` (consentement aux données sensibles : le retirer efface
 * identité de genre et orientation). Chaque champ absent est laissé tel quel.
 */
final class UpdatePrivacyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'visibility' => ['sometimes', 'string', Rule::enum(ProfileVisibility::class)],
            'show_zone' => ['sometimes', 'boolean'],
            'searchable' => ['sometimes', 'boolean'],
            'sos_share_gps' => ['sometimes', 'boolean'],
            'sensitive_consent' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'visibility' => __('profile.settings.privacy.visibility'),
            'show_zone' => __('profile.settings.privacy.show_zone'),
            'searchable' => __('profile.settings.privacy.searchable'),
            'sos_share_gps' => __('profile.settings.privacy.sos_gps'),
            'sensitive_consent' => __('profile.settings.privacy.sensitive_consent'),
        ];
    }

    /**
     * @return array{visibility?: ProfileVisibility, show_zone?: bool, searchable?: bool, sos_share_gps?: bool, sensitive_consent?: bool}
     */
    public function toPrivacy(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();
        $data = [];

        if (isset($validated['visibility'])) {
            $data['visibility'] = ProfileVisibility::from((string) $validated['visibility']);
        }

        foreach (['show_zone', 'searchable', 'sos_share_gps', 'sensitive_consent'] as $field) {
            if (array_key_exists($field, $validated)) {
                $data[$field] = (bool) $validated[$field];
            }
        }

        return $data;
    }
}
