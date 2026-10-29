<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/me/devices/{device_uuid}/wipe (P15-11) : la personne efface à distance un de SES appareils (téléphone perdu
 * ou volé) ; mot de passe actuel exigé, comme pour la suppression du compte (un appareil resté ouvert ne suffit pas).
 */
final class WipeDeviceRequest extends FormRequest
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
            'password' => ['required', 'string', 'current_password:sanctum'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password.current_password' => __('profile.settings.errors.current_password'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => __('profile.settings.password.current'),
        ];
    }
}
