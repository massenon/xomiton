<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reconnaissance grossière des clients automatiques (robots d'indexation, aperçus de liens des messageries et réseaux,
 * outils d'audit, scripts HTTP) d'après l'en-tête User-Agent. Sert aux compteurs de consultation : un faux négatif
 * gonfle un peu un compteur, sans autre effet. Un User-Agent absent ou vide est traité comme automatique.
 */
final class UserAgent
{
    /** Robots et outils ; les navigateurs intégrés aux applications (LinkedIn, X, Pinterest) restent comptés. */
    private const BOT_PATTERN = '/(?<!cu)bot\b|crawl|spider|slurp|archiver|facebookexternalhit|meta-externalagent|whatsapp\/|slack-imgproxy|embedly|preview|lighthouse|pagespeed|headless|phantomjs|curl\/|wget\/|python|java\/|go-http-client|okhttp|httpclient|axios|node-fetch|uptime|monitor/i';

    public static function isBot(?string $userAgent): bool
    {
        $userAgent = trim((string) $userAgent);

        return $userAgent === '' || preg_match(self::BOT_PATTERN, $userAgent) === 1;
    }
}
