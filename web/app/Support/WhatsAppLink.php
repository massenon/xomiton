<?php

declare(strict_types=1);

namespace App\Support;

use App\Rules\PhoneBj;

/**
 * Lien « WhatsApp » d'une fiche de l'annuaire (`/annuaire`, `/urgences`, colonne d'aide d'une thématique ; prototype
 * x_complement.php `x_wa($r['whatsapp'] ?? $r['phone'])`) : numéro WhatsApp, sinon le téléphone ; numéro béninois mis
 * au format international de wa.me (« 01 97 38 88 47 » → `https://wa.me/2290197388847`, PhoneBj) ; autre numéro
 * seulement s'il porte son indicatif (`+…` ou `00…`). Null quand aucun lien valable ne peut être formé.
 */
final class WhatsAppLink
{
    private function __construct() {}

    public static function for(?string $whatsapp, ?string $phone = null): ?string
    {
        foreach ([$whatsapp, $phone] as $number) {
            $number = $number === null ? '' : trim($number);

            if ($number !== '') {
                return self::fromNumber($number);
            }
        }

        return null;
    }

    private static function fromNumber(string $number): ?string
    {
        $benin = PhoneBj::normalize($number);

        if ($benin !== null) {
            return 'https://wa.me/'.preg_replace('/\D/', '', $benin);
        }

        $digits = (string) preg_replace('/\D/', '', $number);

        if (str_starts_with($number, '+')) {
            $international = $digits;
        } elseif (str_starts_with($digits, '00')) {
            $international = substr($digits, 2);
        } else {
            return null;
        }

        return strlen($international) >= 8 && strlen($international) <= 15 ? 'https://wa.me/'.$international : null;
    }
}
