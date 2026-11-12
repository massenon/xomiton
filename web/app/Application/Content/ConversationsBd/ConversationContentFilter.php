<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Application\Feed\BadWordFilter;
use App\Domain\Feed\ContentPatterns;

/**
 * Filtres de contenu des conversations en BD des membres (P20-07, PRD P0-6), appliqués à chaque enregistrement du
 * brouillon et à la proposition, sur le titre, le résumé et le texte de chaque bulle :
 *  - mots interdits : le filtre du projet (App\Application\Feed\BadWordFilter, table `bad_words` et liste blanche,
 *    `xomiton.moderation.content_filter`) en simulation (`test()` : aucune écriture, aucun signalement automatique) ;
 *    gravité « bloquer » ou « masquer » → refus ; « signaler » laisse passer (la modération relit toute création proposée) ;
 *  - numéros de téléphone (8 à 15 chiffres, « +229 97 00 00 00 »), adresses e-mail (y compris « nom (at) domaine point
 *    com ») et liens (`http://`, `www.`, `domaine.tld/…`, nom de domaine nu) : toujours refusés, quel que soit le réglage
 *    des motifs du fil, car une création est destinée à être publique et lue par des inconnu·es ; adresse postale et
 *    liens de messagerie (wa.me, t.me) : mêmes motifs que le fil (ContentPatterns), refusés aussi.
 * Le filtre peut être coupé comme celui du fil (`xomiton.moderation.content_filter.enabled`) ; les coordonnées et les
 * liens restent alors bloqués (protection des personnes, pas modération du ton).
 *
 * Résultat : une erreur par champ fautif (`title`, `summary`, `panels.N.bubbles.M.texte`), message clair qui dit quoi
 * retirer, jamais le terme filtré.
 */
final class ConversationContentFilter
{
    public const BANNED = 'banned';

    public const PHONE = 'phone';

    public const EMAIL = 'email';

    public const LINK = 'link';

    public const CONTACT = 'contact';

    /** Domaines nus fréquents (« monsite.com ») : suffixes reconnus sans schéma ni www. */
    private const TLDS = 'com|net|org|info|biz|fr|bj|tg|ci|sn|io|app|xyz|online|site|uk|ly|gl|gd';

    public function __construct(private readonly BadWordFilter $words) {}

    /**
     * @param  array<string, string|null>  $fields  chemin du champ => texte
     * @return array<string, list<string>> erreurs par champ (vide : tout passe)
     */
    public function check(array $fields): array
    {
        $errors = [];
        $patterns = new ContentPatterns;
        $wordsEnabled = (bool) config('xomiton.moderation.content_filter.enabled', true);

        foreach ($fields as $field => $text) {
            $text = trim((string) $text);

            if ($text === '') {
                continue;
            }

            $code = match (true) {
                self::hasEmail($text) => self::EMAIL,
                self::hasPhone($text) => self::PHONE,
                self::hasLink($text) => self::LINK,
                $patterns->hasContactDetails($text) => self::CONTACT,
                $wordsEnabled && in_array($this->words->test($text, 'conversation_bd')['outcome'], ['blocked', 'hidden'], true) => self::BANNED,
                default => null,
            };

            if ($code !== null) {
                $errors[$field] = [(string) __('conversations_bd.filter.'.$code)];
            }
        }

        return $errors;
    }

    public static function hasPhone(string $text): bool
    {
        if (preg_match_all('/(?<![\p{L}\d])\+?\(?\d[\d\s().\-\/]{6,}\d(?![\p{L}\d])/u', $text, $numbers) > 0) {
            foreach ($numbers[0] as $number) {
                $digits = strlen((string) preg_replace('/\D/', '', $number));

                if ($digits >= 8 && $digits <= 15) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function hasEmail(string $text): bool
    {
        return preg_match('/[\p{L}\d._%+\-]+\s*(?:@|\(\s*at\s*\)|\[\s*at\s*\]|\barobase\b)\s*[\p{L}\d.\-]+\s*(?:\.|\(\s*dot\s*\)|\bpoint\b)\s*\p{L}{2,}/iu', $text) === 1;
    }

    public static function hasLink(string $text): bool
    {
        $lower = mb_strtolower($text);

        if (preg_match('~(?:https?://|ftp://|www\.)\S~u', $lower) === 1) {
            return true;
        }

        // Nom de domaine sans schéma : « monsite.com », « bit.ly/abc », « exemple.bj/page ».
        return preg_match('~(?<![\p{L}\d@.\-])[\p{L}\d][\p{L}\d\-]*(?:\.[\p{L}\d\-]+)*\.(?:'.self::TLDS.')(?![\p{L}\d\-])(?:/\S*)?~u', $lower) === 1;
    }
}
