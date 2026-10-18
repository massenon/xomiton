<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE /api/v1/me (P6-06) : mot de passe actuel exigé (un appareil resté ouvert ne suffit pas à supprimer le compte).
 * Policy UserPolicy::deleteOwnAccount : un compte de l'équipe d'administration ne se supprime pas lui-même (403).
 */
final class DeleteAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->can('deleteOwnAccount', $user);
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
