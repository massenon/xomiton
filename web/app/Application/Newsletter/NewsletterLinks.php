<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Domain\Newsletter\LinkSignature;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Support\KeyedHash;

/**
 * Adresses publiques de la newsletter (P4-07), utilisées par l'e-mail de confirmation et par l'envoi des campagnes
 * (P14-03 : liens de suivi, pixel, `List-Unsubscribe`). Toujours absolues sur `app.url`, jamais sur l'hôte de la requête :
 * un en-tête Host forgé ne peut pas glisser un autre domaine dans un e-mail. Confirmation et désinscription sont
 * portées par le jeton de l'abonné·e ; pixel et clic par une signature HMAC (LinkSignature) dont la clé vient de
 * `xomiton.newsletter.signing_key` (XOMITON_NEWSLETTER_KEY, rotation propre), à défaut dérivée d'`APP_KEY`.
 */
final readonly class NewsletterLinks
{
    private LinkSignature $signature;

    public function __construct()
    {
        $key = (string) config('xomiton.newsletter.signing_key', '');

        $this->signature = new LinkSignature($key !== '' ? $key : KeyedHash::make('newsletter.tracking-links'));
    }

    public function confirmUrl(NewsletterSubscriber $subscriber): string
    {
        return self::absolute(route('newsletter.confirm', ['token' => $subscriber->token], false));
    }

    public function unsubscribeUrl(NewsletterSubscriber $subscriber, ?int $campaignId = null): string
    {
        $parameters = ['token' => $subscriber->token] + ($campaignId !== null && $campaignId > 0 ? ['c' => $campaignId] : []);

        return self::absolute(route('newsletter.unsubscribe', $parameters, false));
    }

    public function openPixelUrl(NewsletterCampaignRecipient $recipient): string
    {
        $id = (int) $recipient->getKey();

        return self::absolute(route('newsletter.open', [
            'recipient' => $id,
            'signature' => $this->signature->sign(LinkSignature::OPEN, $id),
        ], false));
    }

    /** Lien de suivi d'un clic : la cible (http ou https) fait partie de la signature. */
    public function clickUrl(NewsletterCampaignRecipient $recipient, string $url): string
    {
        $id = (int) $recipient->getKey();
        $url = trim($url);

        return self::absolute(route('newsletter.click', [
            'recipient' => $id,
            'signature' => $this->signature->sign(LinkSignature::CLICK, $id, $url),
            'url' => $url,
        ], false));
    }

    /**
     * En-têtes de désinscription en un clic (RFC 2369 et RFC 8058) pour un e-mail de campagne.
     *
     * @return array{List-Unsubscribe: string, List-Unsubscribe-Post: string}
     */
    public function unsubscribeHeaders(NewsletterSubscriber $subscriber, ?int $campaignId = null): array
    {
        return [
            'List-Unsubscribe' => '<'.$this->unsubscribeUrl($subscriber, $campaignId).'>',
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ];
    }

    public function verifyOpen(int $recipientId, string $signature): bool
    {
        return $this->signature->verify($signature, LinkSignature::OPEN, $recipientId);
    }

    public function verifyClick(int $recipientId, string $url, string $signature): bool
    {
        return self::isTrackableUrl($url) && $this->signature->verify($signature, LinkSignature::CLICK, $recipientId, trim($url));
    }

    /** Seules les cibles web absolues sont suivies (prototype : `^https?://`). */
    public static function isTrackableUrl(string $url): bool
    {
        return preg_match('#^https?://[^\s/?\#]+#i', trim($url)) === 1;
    }

    private static function absolute(string $path): string
    {
        return rtrim((string) config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
