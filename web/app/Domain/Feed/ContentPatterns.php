<?php

declare(strict_types=1);

namespace App\Domain\Feed;

/**
 * Motifs de structure du filtre de contenus (lot IOS-10, D-IOS-9) : coordonnées personnelles dans l'espace public
 * (numéro de téléphone, e-mail, adresse postale, lien direct vers une messagerie) et liens suspects (raccourcisseurs,
 * adresses IP, suffixes à risque, identifiants dans l'adresse). Pur : aucune lecture de configuration ni de base ;
 * ne retourne que des catégories (`coordonnees`, `lien`), jamais le texte trouvé.
 */
final class ContentPatterns
{
    public const CONTACT = 'coordonnees';

    public const LINK = 'lien';

    private const MESSAGING_HOSTS = ['wa.me', 'api.whatsapp.com', 'chat.whatsapp.com', 't.me', 'telegram.me', 'm.me', 'signal.me'];

    /**
     * @param  list<string>  $allowedHosts  hôtes jamais suspects
     * @param  list<string>  $suspiciousHosts  raccourcisseurs et hôtes à risque
     * @param  list<string>  $suspiciousTlds  suffixes à risque (sans point)
     */
    public function __construct(
        private readonly array $allowedHosts = [],
        private readonly array $suspiciousHosts = [],
        private readonly array $suspiciousTlds = [],
    ) {}

    /** @return list<string> catégories trouvées parmi `coordonnees` et `lien` (selon `$categories`) */
    public function detect(string $text, array $categories = [self::CONTACT, self::LINK]): array
    {
        $found = [];

        if (in_array(self::CONTACT, $categories, true) && $this->hasContactDetails($text)) {
            $found[] = self::CONTACT;
        }

        if (in_array(self::LINK, $categories, true) && $this->hasSuspiciousLink($text)) {
            $found[] = self::LINK;
        }

        return $found;
    }

    public function hasContactDetails(string $text): bool
    {
        // Numéro : 8 à 15 chiffres, séparés au plus par espaces, points, tirets, parenthèses ; « +229 97 00 00 00 ».
        if (preg_match_all('/(?<![\p{L}\d])\+?\(?\d[\d\s().\-]{6,}\d(?![\p{L}\d])/u', $text, $numbers) > 0) {
            foreach ($numbers[0] as $number) {
                $digits = strlen((string) preg_replace('/\D/', '', $number));

                if ($digits >= 8 && $digits <= 15) {
                    return true;
                }
            }
        }

        // E-mail, y compris « nom (at) domaine . com », « nom [arobase] domaine point com ».
        if (preg_match('/[\p{L}\d._%+\-]+\s*(?:@|\(\s*at\s*\)|\[\s*at\s*\]|\barobase\b)\s*[\p{L}\d.\-]+\s*(?:\.|\(\s*dot\s*\)|\bpoint\b)\s*\p{L}{2,}/iu', $text) === 1) {
            return true;
        }

        // Adresse postale : « 12 rue … », « lot 45 », « maison n° 3 ».
        $lower = mb_strtolower($text);

        if (preg_match('/(?<![\p{L}\d])(?:\d{1,4}\s*(?:bis|ter)?\s*,?\s*(?:rue|avenue|av\.|boulevard|bd|impasse|chemin|route)(?![\p{L}])|(?:maison|villa|parcelle|lot|r[ée]sidence)\s*(?:n°|no\.?|num[ée]ro|#)?\s*\d+|(?:rue|avenue|boulevard|impasse)\s+(?:n°|no\.?)\s*\d+)/iu', $lower) === 1) {
            return true;
        }

        foreach ($this->urls($text) as $host) {
            if ($this->hostMatches($host, self::MESSAGING_HOSTS)) {
                return true;
            }
        }

        return false;
    }

    public function hasSuspiciousLink(string $text): bool
    {
        foreach ($this->urls($text) as $host) {
            if ($this->hostMatches($host, self::MESSAGING_HOSTS) || $this->hostMatches($host, $this->allowedHosts)) {
                continue;
            }

            $tld = substr(strrchr($host, '.') ?: '.', 1);

            if (filter_var($host, FILTER_VALIDATE_IP) !== false
                || str_contains($host, 'xn--')
                || $this->hostMatches($host, $this->suspiciousHosts)
                || in_array($tld, $this->suspiciousTlds, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hôtes des liens du texte : adresses `http(s)://` et `www.`, plus les raccourcisseurs écrits sans schéma.
     *
     * @return list<string>
     */
    private function urls(string $text): array
    {
        $text = mb_strtolower($text);
        $hosts = [];

        if (preg_match_all('~(?:https?://|www\.)([^\s/?#<>"\']+)~u', $text, $m) > 0) {
            foreach ($m[1] as $authority) {
                // Identifiants éventuels « utilisateur@hôte » : l'hôte réel est après l'arobase ; le port est retiré.
                $hosts[] = (string) preg_replace('/:\d+$/', '', (string) substr(strrchr('@'.$authority, '@'), 1));

                if (str_contains($authority, '@')) {
                    $hosts[] = 'xn--userinfo';
                }
            }
        }

        foreach ([...$this->suspiciousHosts, ...self::MESSAGING_HOSTS] as $host) {
            if (preg_match('~(?<![\p{L}\d.\-])'.preg_quote($host, '~').'/\S~u', $text) === 1) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique(array_filter($hosts, static fn (string $h): bool => $h !== '')));
    }

    /** @param  list<string>  $list */
    private function hostMatches(string $host, array $list): bool
    {
        foreach ($list as $entry) {
            $entry = mb_strtolower($entry);

            if ($host === $entry || str_ends_with($host, '.'.$entry)) {
                return true;
            }
        }

        return false;
    }
}
