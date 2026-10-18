<?php

declare(strict_types=1);

namespace App\Domain\Messaging;

use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Règles de la messagerie (P8-01), sans base de données : l'appelant fournit les faits.
 *
 *  - écrire : participant·e présent·e ; dans une conversation privée, l'autre personne doit être joignable (compte actif,
 *    ni suspendu ni anonymisé) et aucun blocage, dans un sens ou dans l'autre (FR-SOC-04) ; dans un groupe, un blocage
 *    n'empêche pas d'écrire (les messages des personnes bloquées sont masqués à la lecture de qui bloque) ;
 *  - aperçu du dernier message : masqué en mode discret ou quand la personne masque ses aperçus (`mask_previews`) ;
 *  - corps : espaces de bord retirés, fins de ligne unifiées, au plus deux lignes vides de suite ;
 *  - sourdine : durée en minutes, null = jusqu'à réactivation (date lointaine, la colonne est une date).
 */
final class ConversationRules
{
    /** Sourdine « jusqu'à réactivation » : `muted_until` posé à cette distance. */
    public const MUTE_FOREVER_YEARS = 100;

    private function __construct() {}

    public static function canSend(bool $isParticipant, bool $isGroup, bool $otherReachable = true, bool $blocked = false): bool
    {
        if (! $isParticipant) {
            return false;
        }

        return $isGroup || ($otherReachable && ! $blocked);
    }

    public static function previewVisible(bool $discreetMode, bool $maskPreviews): bool
    {
        return ! $discreetMode && ! $maskPreviews;
    }

    public static function normalizeBody(?string $body): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", (string) $body);
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);

        return trim($text);
    }

    public static function mutedUntil(?int $minutes, DateTimeInterface $now): CarbonImmutable
    {
        $start = CarbonImmutable::instance($now);

        return $minutes === null ? $start->addYears(self::MUTE_FOREVER_YEARS) : $start->addMinutes(max(1, $minutes));
    }

    /**
     * Dernier message lu après une lecture : jamais en arrière, jamais au-delà du dernier message de la conversation.
     */
    public static function readCursor(?int $current, ?int $requested, ?int $latest): ?int
    {
        if ($latest === null) {
            return $current;
        }

        $target = $requested === null ? $latest : min($requested, $latest);

        return max($current ?? 0, $target) ?: null;
    }
}
