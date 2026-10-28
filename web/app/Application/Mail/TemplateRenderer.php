<?php

declare(strict_types=1);

namespace App\Application\Mail;

use App\Models\EmailTemplate;

/**
 * Substitution des variables `{{nom}}` d'un texte de template (port de x_mail_render() du prototype).
 * Sans dépendance à la base : testable unitairement. Les valeurs sont échappées pour le HTML ; une variable
 * absente des valeurs fournies reste visible dans le texte (et est signalée par `unknownVariables()`).
 */
final class TemplateRenderer
{
    /**
     * @param  array<string, scalar|\Stringable|null>  $vars
     */
    public static function render(string $text, array $vars, bool $escape = true): string
    {
        $rendered = preg_replace_callback(
            EmailTemplate::VARIABLE_PATTERN,
            static function (array $match) use ($vars, $escape): string {
                $name = mb_strtolower($match[1]);

                if (! array_key_exists($name, $vars)) {
                    return $match[0];
                }

                $value = (string) ($vars[$name] ?? '');

                return $escape ? htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $value;
            },
            $text,
        );

        return $rendered ?? $text;
    }

    /**
     * Variables présentes dans les textes mais absentes des valeurs fournies, dans l'ordre d'apparition.
     *
     * @param  array<string, mixed>  $vars
     * @return list<string>
     */
    public static function unknownVariables(array $vars, string ...$texts): array
    {
        $known = array_map(mb_strtolower(...), array_map(strval(...), array_keys($vars)));

        return array_values(array_filter(
            EmailTemplate::extractVariables(...$texts),
            static fn (string $name): bool => ! in_array($name, $known, true),
        ));
    }

    /**
     * Version texte d'un corps HTML (port de la conversion du client SMTP du prototype) : sauts de ligne
     * sur les blocs, balises retirées, entités décodées, lignes vides compactées.
     */
    public static function toPlainText(string $html): string
    {
        // Texte brut : chaque lien https garde son adresse (« libellé : https://… »), sinon il serait perdu.
        $html = (string) preg_replace_callback(
            '#<a\s[^>]*?href="(https://[^"]+)"[^>]*>(.*?)</a>#is',
            static function (array $m): string {
                $label = trim(strip_tags($m[2]));

                return $label === '' || $label === $m[1] ? $m[1] : $label.' : '.$m[1];
            },
            $html,
        );

        $text = preg_replace(
            ['#<head.*?</head>#is', '#<div style="display:none[^>]*>.*?</div>#is', '#<(/p|/tr|/h\d|/div|/ul|/ol)[^>]*>#i', '#<(br|/li)[^>]*>#i'],
            ['', '', "\n\n", "\n"],
            $html,
        ) ?? $html;

        $text = str_replace("\u{A0}", ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace("/\n[ \t]+/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
