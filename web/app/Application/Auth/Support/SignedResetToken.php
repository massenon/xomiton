<?php

declare(strict_types=1);

namespace App\Application\Auth\Support;

use App\Enums\OtpPurpose;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Lien magique de réinitialisation (Q8 c, WCAG 3.3.8) : jeton signé HMAC-SHA256 avec APP_KEY, lié au code OTP `reset`
 * émis en même temps et valable aussi longtemps que lui. Aucun stockage supplémentaire : la vérification retrouve
 * l'enregistrement `otp_codes` par son identifiant et le consomme comme un code saisi.
 */
final class SignedResetToken
{
    private function __construct() {}

    public static function make(OtpCode $otp): string
    {
        $payload = self::encode(sprintf('%d.%d.%s', $otp->getKey(), $otp->expires_at->getTimestamp(), self::emailDigest($otp->email)));

        return $payload.'.'.self::sign($payload);
    }

    /**
     * Code OTP encore valable désigné par le jeton pour cette adresse, ou null (jeton altéré, expiré, déjà utilisé).
     */
    public static function verify(string $token, string $email): ?OtpCode
    {
        $parts = explode('.', trim($token));

        if (count($parts) !== 2 || ! hash_equals(self::sign($parts[0]), $parts[1])) {
            return null;
        }

        $decoded = self::decode($parts[0]);
        $fields = $decoded === null ? [] : explode('.', $decoded);

        if (count($fields) !== 3 || ! ctype_digit($fields[0]) || ! ctype_digit($fields[1])) {
            return null;
        }

        [$id, $expires, $digest] = $fields;

        if ((int) $expires <= now()->getTimestamp() || ! hash_equals(self::emailDigest($email), $digest)) {
            return null;
        }

        return OtpCode::query()
            ->whereKey((int) $id)
            ->forEmail($email, OtpPurpose::Reset)
            ->valid()
            ->first();
    }

    /** Adresse du lien : route `reinitialisation` quand elle existe (lot écrans), sinon le chemin convenu. */
    public static function url(string $token, string $email): string
    {
        $parameters = ['email' => mb_strtolower(trim($email)), 'token' => $token];

        if (Route::has('reinitialisation')) {
            return route('reinitialisation', $parameters);
        }

        return url('/nouveau-mot-de-passe').'?'.http_build_query($parameters);
    }

    /** Un jeton de lien ressemble à « xxx.yyy » ; un code saisi n'a que des chiffres. */
    public static function looksLikeToken(string $value): bool
    {
        return str_contains($value, '.') && preg_match('/^[A-Za-z0-9_\-]+\.[a-f0-9]{64}$/', trim($value)) === 1;
    }

    private static function sign(string $payload): string
    {
        return hash_hmac('sha256', $payload, self::key());
    }

    private static function key(): string
    {
        $key = (string) config('app.key');

        if (Str::startsWith($key, 'base64:')) {
            $key = (string) base64_decode(Str::after($key, 'base64:'), true);
        }

        return $key;
    }

    private static function emailDigest(string $email): string
    {
        return substr(hash('sha256', mb_strtolower(trim($email))), 0, 16);
    }

    private static function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private static function decode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
