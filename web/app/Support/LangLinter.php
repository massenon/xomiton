<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\Finder\Finder;

/**
 * Lint des textes de lang/<locale> (P16-06 ; VOICE_AND_TONE.md §6, FRONT_END_COPYWRITING_INSTRUCTION.md §2) :
 *
 *  - apostrophe typographique ’ (jamais ') entre deux lettres ;
 *  - espace insécable (U+00A0) avant « : ; ! ? », espace ordinaire ou absente = violation (les marqueurs « :nom »
 *    de Laravel, les entités HTML et les URL sont ignorés) ;
 *  - espace insécable à l'intérieur des guillemets « » ;
 *  - points de suspension … (jamais « ... ») ;
 *  - boutons (`buttons.php`) : au plus 4 mots, majuscule au premier mot ;
 *  - clés `label` : au plus 5 mots ; clés `badge` : au plus 2 mots.
 *
 * `check()` ne lit que des chaînes ; `fix()` réécrit les littéraux d'un fichier PHP (apostrophes, espaces, guillemets,
 * points de suspension) sans toucher aux clés, aux marqueurs ni au code, et ne corrige jamais les longueurs.
 */
final class LangLinter
{
    public const NBSP = "\u{00A0}";

    public const BUTTON_MAX_WORDS = 4;

    public const LABEL_MAX_WORDS = 5;

    public const BADGE_MAX_WORDS = 2;

    /**
     * @return list<array{file: string, key: string, rule: string, message: string}>
     */
    public function lintDirectory(string $directory): array
    {
        $violations = [];

        foreach (Finder::create()->files()->in($directory)->name('*.php')->sortByName() as $file) {
            $relative = str_replace(DIRECTORY_SEPARATOR, '/', $file->getRelativePathname());
            $array = require $file->getRealPath();

            if (is_array($array)) {
                foreach ($this->flatten($array, substr($relative, 0, -4)) as $key => $value) {
                    array_push($violations, ...$this->check($relative, $key, $value));
                }
            }
        }

        $json = dirname($directory).'/'.basename($directory).'.json';

        if (is_file($json)) {
            foreach (json_decode((string) file_get_contents($json), true) ?? [] as $key => $value) {
                if (is_string($value)) {
                    array_push($violations, ...$this->check(basename($json), (string) $key, $value));
                }
            }
        }

        return $violations;
    }

    /**
     * @return list<array{file: string, key: string, rule: string, message: string}>
     */
    public function check(string $file, string $key, string $value): array
    {
        $found = [];
        $add = function (string $rule, string $message) use (&$found, $file, $key): void {
            $found[] = ['file' => $file, 'key' => $key, 'rule' => $rule, 'message' => $message];
        };

        // Les entités HTML (&nbsp; &amp;) et les URL ne sont pas du texte typographié.
        $text = (string) preg_replace(['/&#?\w+;/', '~https?://\S+~', '/<[^>]+>/'], ['', '', ''], $value);

        if (preg_match("/\\p{L}'\\p{L}/u", $text) === 1) {
            $add('apostrophe', 'apostrophe droite \' à remplacer par ’');
        }

        if (preg_match('/ [;!?]| :(?=\s|$)/u', $text) === 1) {
            $add('espace-avant-ponctuation', 'espace ordinaire avant : ; ! ? (utiliser U+00A0)');
        }

        if (preg_match('/[\p{L}\d)»…][;!?](?=\s|$)|\p{L}:(?=\s|$)/u', $text) === 1) {
            $add('espace-manquant', 'espace insécable manquant avant : ; ! ?');
        }

        if (preg_match('/«(?!\x{00A0})|(?<!\x{00A0})»/u', $text) === 1) {
            $add('guillemets', 'espace insécable manquant à l’intérieur de « »');
        }

        if (str_contains($text, '...')) {
            $add('points-de-suspension', '« ... » à remplacer par …');
        }

        $segments = explode('|', $text);

        if (basename($file) === 'buttons.php') {
            foreach ($segments as $segment) {
                if ($this->words($segment) > self::BUTTON_MAX_WORDS) {
                    $add('bouton-longueur', 'bouton de plus de '.self::BUTTON_MAX_WORDS.' mots');
                }

                if (preg_match('/^\s*\p{Ll}/u', $segment) === 1) {
                    $add('bouton-majuscule', 'bouton sans majuscule initiale');
                }
            }
        }

        $last = (string) preg_replace('/^.*\./', '', $key);

        if ($last === 'label' && $this->words($text) > self::LABEL_MAX_WORDS) {
            $add('libelle-longueur', 'libellé de plus de '.self::LABEL_MAX_WORDS.' mots');
        }

        if ($last === 'badge' && $this->words($text) > self::BADGE_MAX_WORDS) {
            $add('badge-longueur', 'badge de plus de '.self::BADGE_MAX_WORDS.' mots');
        }

        return $found;
    }

    /**
     * Corrige les violations typographiques évidentes dans le contenu d'un fichier PHP de traductions.
     * Retourne le nouveau contenu (identique si rien à corriger).
     */
    public function fix(string $source): string
    {
        $tokens = token_get_all($source);
        $out = '';

        foreach ($tokens as $index => $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING || $this->isArrayKey($tokens, $index)) {
                $out .= is_array($token) ? $token[1] : $token;

                continue;
            }

            $out .= $this->fixLiteral($token[1]);
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $tokens
     */
    private function isArrayKey(array $tokens, int $index): bool
    {
        for ($i = $index + 1; $i < count($tokens); $i++) {
            $t = $tokens[$i];

            if (is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT], true)) {
                continue;
            }

            return is_array($t) && $t[0] === T_DOUBLE_ARROW;
        }

        return false;
    }

    private function fixLiteral(string $literal): string
    {
        $quote = $literal[0];
        $body = substr($literal, 1, -1);

        // Apostrophe : \' (guillemet simple échappé) ou ' (chaîne double) entre deux lettres devient ’.
        $body = (string) preg_replace($quote === "'" ? "/(?<=\\p{L})\\\\'(?=\\p{L})/u" : "/(?<=\\p{L})'(?=\\p{L})/u", '’', $body);
        // Points de suspension (hors URL et séquences de plus de trois points).
        $body = (string) preg_replace('/(?<!\.)\.{3}(?!\.)/', '…', $body);
        // Espace ordinaire avant : ; ! ? (marqueurs « :nom » et fin de chaîne exclus).
        $body = (string) preg_replace('/ (?=[;!?])| (?=:(?:\s|$|\\\\))/u', self::NBSP, $body);
        // Guillemets français.
        $body = (string) preg_replace(['/«[ ]?(?=\S)/u', '/(?<=\S)[ ]?»/u'], ['«'.self::NBSP, self::NBSP.'»'], $body);

        return $quote.$body.$quote;
    }

    private function words(string $text): int
    {
        $clean = trim((string) preg_replace('/\s+/u', ' ', str_replace(self::NBSP, ' ', $text)));

        return $clean === '' ? 0 : count(explode(' ', $clean));
    }

    /**
     * @param  array<mixed>  $array
     * @return array<string, string>
     */
    private function flatten(array $array, string $prefix): array
    {
        $lines = [];

        foreach ($array as $key => $value) {
            $flat = $prefix.'.'.$key;

            if (is_array($value)) {
                $lines += $this->flatten($value, $flat);
            } elseif (is_string($value)) {
                $lines[$flat] = $value;
            }
        }

        return $lines;
    }
}
