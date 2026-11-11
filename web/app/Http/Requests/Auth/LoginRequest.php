<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Application\Auth\DeviceTrust;
use App\Application\Auth\LoginData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Formulaire /connexion (LOGIN §3) : identifiant e-mail ou pseudonyme, mot de passe, « Rester connecté·e »,
 * destination `next` limitée aux chemins internes (DEVELOPMENT_STANDARDS §7 « Redirection ouverte »).
 */
final class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'identifier' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['nullable', 'boolean'],
            'next' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function toLoginData(): LoginData
    {
        $cookie = $this->cookie(DeviceTrust::COOKIE);

        return new LoginData(
            identifier: (string) $this->string('identifier'),
            password: (string) $this->string('password'),
            remember: $this->boolean('remember'),
            ip: $this->ip(),
            userAgent: $this->userAgent(),
            deviceCookie: is_string($cookie) ? $cookie : null,
        );
    }

    /** Chemin interne demandé après connexion, ou null (jamais d'URL absolue ni de « //evil »). */
    public function safeNext(): ?string
    {
        return self::internalPath($this->input('next'));
    }

    public static function internalPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '' || $value[0] !== '/' || str_starts_with($value, '//') || str_starts_with($value, '/\\')) {
            return null;
        }

        return preg_match('/^[\x21-\x7E]+$/', $value) === 1 ? $value : null;
    }
}
