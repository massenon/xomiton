<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Appareil reconnu (Q13 b) : cookie `remember_device` chiffré et signé (Crypt, authentifié par MAC), valable 30 jours,
 * lié au compte et à son mot de passe courant — une réinitialisation du mot de passe invalide tous les appareils.
 * Sans cookie valable, ou pour les rôles admin / superadmin, la connexion exige un code OTP `login`.
 */
class DeviceTrust
{
    public const COOKIE = 'remember_device';

    public const TTL_DAYS = 30;

    /** Ajoute le cookie de confiance à la réponse, après un OTP `login` réussi. */
    public function trustDevice(User $user, Response $response): Response
    {
        $response->headers->setCookie($this->makeCookie($user));

        return $response;
    }

    /** L'appareil de la requête est-il déjà reconnu pour ce compte ? */
    public function deviceIsTrusted(User $user, Request $request): bool
    {
        $value = $request->cookie(self::COOKIE);

        return $this->cookieMatches($user, is_string($value) ? $value : null);
    }

    /** Oublie l'appareil (déconnexion « sur tous les appareils », changement de mot de passe). */
    public function forget(Response $response): Response
    {
        $response->headers->setCookie(Cookie::create(self::COOKIE)->withValue('')->withExpires(1)->withPath('/'));

        return $response;
    }

    /** Vérifie une valeur brute de cookie contre le compte (utilisable hors HTTP, par les cas d'usage). */
    public function cookieMatches(User $user, ?string $value): bool
    {
        if ($value === null || $value === '') {
            return false;
        }

        try {
            $payload = json_decode(Crypt::decryptString($value), true, 8, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return false;
        }

        if (! is_array($payload) || ! isset($payload['u'], $payload['e'], $payload['f'])) {
            return false;
        }

        return (int) $payload['u'] === (int) $user->getKey()
            && (int) $payload['e'] > now()->getTimestamp()
            && hash_equals($this->fingerprint($user), (string) $payload['f']);
    }

    public function makeCookie(User $user): Cookie
    {
        $expires = now()->addDays(self::TTL_DAYS);

        $payload = json_encode([
            'u' => $user->getKey(),
            'e' => $expires->getTimestamp(),
            'f' => $this->fingerprint($user),
            'n' => bin2hex(random_bytes(8)),
        ], JSON_THROW_ON_ERROR);

        return Cookie::create(self::COOKIE)
            ->withValue(Crypt::encryptString($payload))
            ->withExpires($expires->getTimestamp())
            ->withPath('/')
            ->withSecure((bool) config('session.secure', false))
            ->withHttpOnly(true)
            ->withSameSite(Cookie::SAMESITE_LAX);
    }

    /** Empreinte du mot de passe courant : jamais le hachage lui-même dans le cookie. */
    private function fingerprint(User $user): string
    {
        return substr(hash('sha256', (string) $user->getKey().'|'.(string) $user->getAuthPassword()), 0, 24);
    }
}
