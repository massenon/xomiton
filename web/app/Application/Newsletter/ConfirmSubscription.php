<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Domain\Auth\ConsentVersion;
use App\Domain\Newsletter\SubscriptionPageState;
use App\Enums\ConsentKind;
use App\Enums\SubscriberStatus;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Confirmation du double opt-in (`/newsletter/confirmer/{jeton}`, P4-07). Le lien reçu par e-mail ouvre une page avec
 * un bouton ; seul le POST active l'abonnement, pour qu'un aperçu de lien (messagerie, antivirus) ne confirme pas à la
 * place de la personne. Le lien vaut `xomiton.newsletter.confirmation_ttl_hours` après l'envoi. Une adresse désabonnée
 * ne se réactive que par un e-mail de confirmation envoyé après sa désinscription (`confirmation_sent_at` >
 * `unsubscribed_at`, SubscribeToNewsletter) : l'ancien lien reste refusé (le prototype le permettait) ; une adresse en
 * rebond, jamais.
 * La confirmation est le consentement de la personne propriétaire de l'adresse : date de confirmation remise à jour,
 * ligne `consents` sans IP et audit, dans une seule transaction sur la ligne verrouillée (un double envoi simultané ne
 * crée qu'un consentement).
 */
final class ConfirmSubscription
{
    public function state(?NewsletterSubscriber $subscriber): SubscriptionPageState
    {
        return match ($subscriber?->status) {
            SubscriberStatus::Active => SubscriptionPageState::Confirmed,
            SubscriberStatus::Pending => $this->expired($subscriber) ? SubscriptionPageState::Expired : SubscriptionPageState::Confirm,
            SubscriberStatus::Unsubscribed => match (true) {
                ! $this->askedAgain($subscriber) => SubscriptionPageState::Invalid,
                $this->expired($subscriber) => SubscriptionPageState::Expired,
                default => SubscriptionPageState::Confirm,
            },
            default => SubscriptionPageState::Invalid,
        };
    }

    /** Active l'abonnement s'il attend sa confirmation ; renvoie l'état à afficher ensuite. */
    public function __invoke(?NewsletterSubscriber $subscriber): SubscriptionPageState
    {
        if ($subscriber === null) {
            return SubscriptionPageState::Invalid;
        }

        return DB::transaction(function () use ($subscriber): SubscriptionPageState {
            $locked = NewsletterSubscriber::query()->lockForUpdate()->find($subscriber->getKey());
            $state = $this->state($locked);

            if ($locked === null || $state !== SubscriptionPageState::Confirm) {
                return $state;
            }

            $locked->forceFill([
                'status' => SubscriberStatus::Active,
                'confirmed_at' => now(), // date du consentement en cours, pas d'un abonnement antérieur
                'unsubscribed_at' => null,
                'unsubscribe_reason' => null,
            ])->save();

            Consent::query()->create([
                'user_id' => $locked->user_id,
                'email' => $locked->email,
                'kind' => ConsentKind::Newsletter,
                'version' => ConsentVersion::current(),
                'granted' => true,
            ]);

            Audit::logWithoutRequest('newsletter.subscription_confirmed', $locked); // sans IP ni navigateur

            return SubscriptionPageState::Confirmed;
        });
    }

    public static function ttlHours(): int
    {
        return max(1, (int) config('xomiton.newsletter.confirmation_ttl_hours', 48));
    }

    private function expired(NewsletterSubscriber $subscriber): bool
    {
        // Aucun e-mail de confirmation parti (import, ajout manuel sans envoi) : rien à confirmer par ce lien.
        return $subscriber->confirmation_sent_at === null
            || $subscriber->confirmation_sent_at->lt(now()->subHours(self::ttlHours()));
    }

    /** Désabonnée qui a redemandé l'inscription : un e-mail de confirmation est parti après la désinscription. */
    private function askedAgain(NewsletterSubscriber $subscriber): bool
    {
        return $subscriber->confirmation_sent_at !== null
            && $subscriber->unsubscribed_at !== null
            && $subscriber->confirmation_sent_at->gt($subscriber->unsubscribed_at);
    }
}
