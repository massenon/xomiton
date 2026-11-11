<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PUT /api/v1/me/password (P8-09) : mot de passe actuel vérifié (`current_password`, garde Sanctum : session de l'espace
 * Vue ou jeton mobile), nouveau mot de passe solide (StrongPassword = App\Domain\Auth\PasswordStrength, la jauge
 * password-meter applique les mêmes règles), différent de l'actuel, et confirmation identique (`same:password`, erreur
 * rangée sous `password_confirmation` : le champ fautif est celui que l'écran marque).
 */
final class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string', 'current_password:sanctum'],
            'password' => ['required', 'string', 'max:255', 'different:current_password', new StrongPassword],
            'password_confirmation' => ['required', 'string', 'same:password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'current_password.current_password' => __('profile.settings.errors.current_password'),
            'password_confirmation.required' => __('profile.settings.errors.password_confirmation_required'),
            'password_confirmation.same' => __('profile.settings.errors.password_confirmation'),
            'password.different' => __('profile.settings.errors.password_same'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'current_password' => __('profile.settings.password.current'),
            'password' => __('profile.settings.password.new'),
            'password_confirmation' => __('profile.settings.password.confirm'),
        ];
    }

    public function newPassword(): string
    {
        return (string) $this->validated('password');
    }
}
