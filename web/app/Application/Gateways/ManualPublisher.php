<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Models\OutboundPost;
use App\Models\SocialChannel;

/**
 * Publication manuelle assistée (ADR-15) : pour Roméo, GanXo et Tinder (aucune API publique) et, tant que son connecteur
 * n'existe pas (P14-04), pour tout réseau. Rien n'est envoyé : le connecteur prépare le texte prêt à copier, une personne
 * de l'équipe publie sur le compte de l'organisation puis marque la remise comme publiée (ManageGateways::markDelivered,
 * statut `manuel`).
 */
final class ManualPublisher implements SocialPublisher
{
    public function supports(SocialChannel $channel): bool
    {
        return true;
    }

    public function publish(OutboundPost $post, SocialChannel $channel): PublishResult
    {
        return PublishResult::awaitingManual(self::copyText($post));
    }

    /** Texte à coller dans le réseau : le message d'accompagnement tel qu'enregistré. */
    public static function copyText(OutboundPost $post): string
    {
        return trim((string) $post->message);
    }
}
