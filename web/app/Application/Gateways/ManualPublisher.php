<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\SocialNetwork;
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
        return PublishResult::awaitingManual(self::copyTextFor($post, $channel));
    }

    /**
     * Texte à coller dans ce réseau : le message puis, pour une BD publique, le lien `/bd/{key}` avec les paramètres UTM
     * du réseau (P19-11). Les images (visuel de partage, planches) sont proposées à l'écran pour être jointes à la main.
     */
    public static function copyTextFor(OutboundPost $post, SocialChannel $channel): string
    {
        $network = SocialNetwork::tryFrom((string) ($channel->getAttributes()['network'] ?? ''));

        return $network === null ? self::copyText($post) : app(OutboundContent::class)->for($post, $network)->fullText();
    }

    /** Texte à coller dans le réseau : le message d'accompagnement tel qu'enregistré. */
    public static function copyText(OutboundPost $post): string
    {
        return trim((string) $post->message);
    }
}
