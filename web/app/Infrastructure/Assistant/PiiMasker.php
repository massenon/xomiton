<?php

declare(strict_types=1);

namespace App\Infrastructure\Assistant;

/**
 * Minimisation avant tout envoi à un tiers (LLM) : retire de la question e-mails, adresses web, numéros de téléphone,
 * longues suites de chiffres (pièces d'identité, comptes), coordonnées GPS, identifiants @pseudo et les noms connus
 * de la personne (nom complet, pseudo et leurs mots de trois lettres ou plus). Un nom propre quelconque ne peut pas
 * être détecté de façon fiable : la consigne système demande aussi de ne pas en tenir compte, et le risque résiduel
 * est une raison de plus de laisser le LLM désactivé tant que l'avis juridique n'est pas rendu.
 */
final class PiiMasker
{
    /**
     * @param  list<string>  $knownNames  noms à retirer (nom, pseudo de la personne)
     */
    public static function mask(string $text, array $knownNames = []): string
    {
        $text = preg_replace('/[\w.+\-]+@[\w\-]+(?:\.[\w\-]+)+/u', '[e-mail]', $text) ?? $text;
        $text = preg_replace('~(?:https?://|www\.)\S+~iu', '[lien]', $text) ?? $text;
        // Coordonnées décimales « 6.3703, 2.3912 ».
        $text = preg_replace('/-?\d{1,3}\.\d{3,}\s*[,;]\s*-?\d{1,3}\.\d{3,}/u', '[position]', $text) ?? $text;
        // Téléphones : « +229 01 97 38 88 47 », « 97-38-88-47 »… (7 chiffres ou plus, séparateurs usuels tolérés).
        $text = preg_replace('/(?<![\w])\+?\d(?:[\s.\-()]?\d){6,}(?!\w)/u', '[numéro]', $text) ?? $text;
        $text = preg_replace('/(?<!\w)@\w{2,}/u', '[pseudo]', $text) ?? $text;

        $names = [];

        foreach ($knownNames as $name) {
            $names[] = trim($name);

            foreach (preg_split('/[\s\-_.]+/u', $name) ?: [] as $part) {
                $names[] = $part;
            }
        }

        $names = array_unique(array_filter($names, static fn (string $n): bool => mb_strlen($n) >= 3));
        usort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        foreach ($names as $name) {
            $text = preg_replace('/(?<!\p{L})'.preg_quote($name, '/').'(?!\p{L})/iu', '[nom]', $text) ?? $text;
        }

        return $text;
    }
}
