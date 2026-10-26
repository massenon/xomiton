<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\EmailTemplate;
use Mews\Purifier\Facades\Purifier;

/**
 * Nettoyage du HTML des e-mails saisi en administration (DEVELOPMENT_STANDARDS.md §7 : HTMLPurifier pour les templates et
 * les campagnes, profil `email` de config/purifier.php). Les variables `{{nom}}` sont mises à l'abri avant le nettoyage
 * (un `href="{{reset_link}}"` n'est pas une adresse valide : HTMLPurifier l'encoderait ou la supprimerait) puis rétablies.
 */
final class EmailHtml
{
    private const OPEN = 'xmvar0';

    private const CLOSE = '0xmvar';

    private function __construct() {}

    public static function clean(string $html): string
    {
        $protected = preg_replace_callback(
            EmailTemplate::VARIABLE_PATTERN,
            static fn (array $match): string => self::OPEN.mb_strtolower($match[1]).self::CLOSE,
            $html,
        ) ?? $html;

        $clean = (string) Purifier::clean($protected, 'email');

        return trim((string) preg_replace_callback(
            '/'.self::OPEN.'([a-z][a-z0-9_]*)'.self::CLOSE.'/',
            static fn (array $match): string => '{{'.$match[1].'}}',
            $clean,
        ));
    }
}
