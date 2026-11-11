<?php

declare(strict_types=1);

namespace App\Application\Mail;

/**
 * Habille le corps d'un template (balises simples h1/h2/h3/p/ul/ol/li/a/strong, déjà purgé) avec des styles EN LIGNE
 * issus d'EmailTheme et des classes de mode sombre. Un paragraphe réduit à un lien devient un bouton (14 + 20 + 14 = 48 px, ≥ 44 px ;
 * pas de min-height : boîte en content-box) ; un `h1` est le code de vérification (grand, espacé, centré).
 */
final class EmailStyler
{
    public static function apply(string $html): string
    {
        $t = EmailTheme::LIGHT;
        $body = EmailTheme::FONT_BODY;
        $head = EmailTheme::FONT_HEADING;

        // 1. Paragraphe réduit à un lien (éventuellement en gras) : bouton principal.
        $html = (string) preg_replace_callback(
            '#<p(?:\s[^>]*)?>\s*<a\s([^>]*?)>\s*(?:<strong>)?(.*?)(?:</strong>)?\s*</a>\s*</p>#is',
            static function (array $m) use ($t, $body): string {
                if (! preg_match('#href="([^"]+)"#i', $m[1], $href)) {
                    return $m[0];
                }

                return '<table role="presentation" class="xm-btn-tbl" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 24px"><tr>'
                    .'<td align="center" bgcolor="'.$t['primary'].'" class="xm-btn-cell" style="background-color:'.$t['primary'].';border-radius:10px">'
                    .'<a href="'.$href[1].'" class="xm-btn" target="_blank" rel="noopener noreferrer" style="display:inline-block;padding:14px 28px;'
                    .'font-family:'.$body.';font-size:16px;line-height:20px;font-weight:700;color:'.$t['on_primary'].';text-decoration:none;border-radius:10px;background-color:'.$t['primary'].'">'
                    .trim($m[2]).'</a></td></tr></table>';
            },
            $html,
        );

        // 2. Balises restantes : style en ligne + classe de mode sombre (sauf si un style existe déjà).
        $map = [
            'h1' => ['xm-code', "margin:8px 0 20px;padding:16px 12px;text-align:center;border-radius:12px;font-family:{$head};font-size:34px;line-height:40px;font-weight:800;letter-spacing:8px;background-color:{$t['subtle']};color:{$t['subtle_text']}"],
            'h2' => ['xm-text', "margin:0 0 16px;font-family:{$head};font-size:24px;line-height:30px;font-weight:800;color:{$t['text']}"],
            'h3' => ['xm-text', "margin:0 0 12px;font-family:{$head};font-size:19px;line-height:26px;font-weight:700;color:{$t['text']}"],
            'p' => ['xm-text', "margin:0 0 16px;font-family:{$body};font-size:16px;line-height:25px;color:{$t['text']}"],
            'ul' => ['xm-text', "margin:0 0 16px;padding-left:22px;font-family:{$body};font-size:16px;line-height:25px;color:{$t['text']}"],
            'ol' => ['xm-text', "margin:0 0 16px;padding-left:22px;font-family:{$body};font-size:16px;line-height:25px;color:{$t['text']}"],
            'li' => ['xm-text', "margin:0 0 6px;color:{$t['text']}"],
            'a' => ['xm-link', "color:{$t['primary']};text-decoration:underline"],
        ];

        foreach ($map as $tag => [$class, $style]) {
            $html = (string) preg_replace_callback(
                '#<'.$tag.'(\s[^>]*)?>#i',
                static function (array $m) use ($tag, $class, $style): string {
                    $attrs = $m[1] ?? '';

                    if (stripos($attrs, 'xm-btn') !== false || stripos($attrs, 'style=') !== false) {
                        return $m[0];
                    }

                    return '<'.$tag.$attrs.' class="'.$class.'" style="'.$style.'">';
                },
                $html,
            );
        }

        return $html;
    }
}
