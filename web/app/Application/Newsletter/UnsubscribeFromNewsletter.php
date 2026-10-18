<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Domain\Auth\ConsentVersion;
use App\Domain\Newsletter\SubscriptionPageState;
use App\Enums\ConsentKind;
use App\Enums\SubscriberStatus;
use App\Models\Consent;
use App\Models\NewsletterCampaignRecipient;
use App\Models\NewsletterSubscriber;
use App\Support\Audit;

/**
 * Désinscription en un clic (`/newsletter/desinscription/{jeton}[?c=campagne]`, P4-07, RG-12 ; prototype `a=unsub`) :
 * le jeton suffit (POST du bouton ou « one-click » RFC 8058 des messageries), motif facultatif choisi dans une liste
 * fermée (libellé enregistré, comme le prototype et l'import). Une adresse en attente annule ainsi son inscription.
 * Trace : retrait du consentement (`consents`, sans IP), destinataire de la campagne marqué `unsubscribed`, audit.
 */
final class UnsubscribeFromNewsletter
{
    /** @var list<string> clés des motifs (lang/fr/newsletter.php `public.unsubscribe_reasons`) */
    public const REASONS = ['too_many', 'not_relevant', 'never_subscribed', 'other'];

    public function state(?NewsletterSubscriber $subscriber): SubscriptionPageState
    {
        return match ($subscriber?->status) {
            SubscriberStatus::Active, SubscriberStatus::Pending => SubscriptionPageState::Unsubscribe,
            SubscriberStatus::Unsubscribed, SubscriberStatus::Bounced => SubscriptionPageState::Unsubscribed,
            default => SubscriptionPageState::Invalid,
        };
    }

    public function __invoke(?NewsletterSubscriber $subscriber, ?string $reason = null, ?int $campaignId = null): SubscriptionPageState
    {
        if ($subscriber === null || $this->state($subscriber) !== SubscriptionPageState::Unsubscribe) {
            return $this->state($subscriber);
        }

        $subscriber->unsubscribe(self::reasonLabel($reason));

        Consent::query()->create([
            'user_id' => $subscriber->user_id,
            'email' => $subscriber->email,
            'kind' => ConsentKind::Newsletter,
            'version' => ConsentVersion::current(),
            'granted' => false,
        ]);

        if ($campaignId !== null && $campaignId > 0) {
            NewsletterCampaignRecipient::query()
                ->where('campaign_id', $campaignId)
                ->where('subscriber_id', $subscriber->getKey())
                ->update(['unsubscribed' => true, 'updated_at' => now()]);
        }

        Audit::logWithoutRequest('newsletter.unsubscribed', $subscriber); // sans IP ni navigateur

        return SubscriptionPageState::Unsubscribed;
    }

    /** Libellé du motif connu, sinon null (valeur libre ignorée). */
    public static function reasonLabel(?string $reason): ?string
    {
        return $reason !== null && in_array($reason, self::REASONS, true)
            ? (string) __('newsletter.public.unsubscribe_reasons.'.$reason)
            : null;
    }
}
