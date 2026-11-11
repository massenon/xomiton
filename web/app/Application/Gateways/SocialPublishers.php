<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Models\SocialChannel;

/**
 * Choisit le connecteur d'un réseau (ADR-15). `CONNECTORS` listera les connecteurs réels (P14-04) ; tout réseau sans
 * connecteur — ou dont le jeton manque ou a expiré (SocialChannel::canPublishViaApi) — passe par ManualPublisher.
 */
final class SocialPublishers
{
    /** @var list<class-string<SocialPublisher>> connecteurs à API (P14-04) ; TikTok : aucun, sans API de contenu publique */
    public const CONNECTORS = [FacebookPublisher::class, TelegramPublisher::class];

    public function for(SocialChannel $channel): SocialPublisher
    {
        if ($channel->canPublishViaApi()) {
            foreach (self::CONNECTORS as $class) {
                $connector = app($class);

                if ($connector->supports($channel)) {
                    return $connector;
                }
            }
        }

        return app(ManualPublisher::class);
    }
}
