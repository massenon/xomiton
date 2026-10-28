<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Normalisation E.164 des numéros de SMS. Un numéro sans « + » ni « 00 » est réputé béninois (indicatif configuré,
 * `xomiton.sms.default_country_code`, 229) : le « 01 » du plan à 10 chiffres fait partie du numéro et est conservé.
 * Empreinte : identifiant stable non réversible pour le journal (jamais le numéro en clair, même masqué).
 */
final class PhoneNumber
{
    private function __construct() {}

    /** @return string|null `+22901XXXXXXXX`, ou null si le numéro n'est pas plausible (E.164 : 8 à 15 chiffres) */
    public static function e164(string $raw, ?string $defaultCountryCode = null): ?string
    {
        $raw = trim($raw);
        $plus = str_starts_with($raw, '+');
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (! $plus && str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        } elseif (! $plus) {
            $country = $defaultCountryCode ?? (string) config('xomiton.sms.default_country_code', '229');
            $digits = $country.$digits;
        }

        return preg_match('/^[1-9]\d{7,14}$/', $digits) === 1 ? '+'.$digits : null;
    }

    public static function fingerprint(string $number): string
    {
        return substr(hash_hmac('sha256', $number, (string) config('app.key')), 0, 12);
    }
}
