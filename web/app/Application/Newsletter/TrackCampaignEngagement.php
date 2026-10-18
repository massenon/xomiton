<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Models\NewsletterCampaignRecipient;

/**
 * Pixel d'ouverture et clic suivi d'une campagne (P4-07, ADR-14 ; prototype `a=open` / `a=click`). Les signatures
 * sont vérifiées par l'appelant (NewsletterLinks) ; ici on n'enregistre que pour un destinataire existant. Un clic vaut
 * ouverture (images bloquées par la messagerie). Aucune IP ni empreinte de navigateur n'est enregistrée.
 */
final class TrackCampaignEngagement
{
    /** Longueur de `newsletter_clicks.url`. */
    public const MAX_URL_LENGTH = 2048;

    public function open(int $recipientId): void
    {
        NewsletterCampaignRecipient::query()->with('subscriber')->find($recipientId)?->markOpened();
    }

    public function click(int $recipientId, string $url): void
    {
        $recipient = NewsletterCampaignRecipient::query()->with('subscriber')->find($recipientId);

        if ($recipient === null || mb_strlen(trim($url)) > self::MAX_URL_LENGTH) {
            return;
        }

        $recipient->markOpened();
        $recipient->recordClick(trim($url));
    }
}
