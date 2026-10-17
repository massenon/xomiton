<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/auth/login : identifiant (e-mail ou pseudonyme), mot de passe, nom de l'appareil (nom du jeton Sanctum)
 * et, s'il existe, le `device_token` reçu après un OTP de connexion réussi (équivalent API du cookie remember_device, Q13 b).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'login' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:100'],
            'device_token' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
