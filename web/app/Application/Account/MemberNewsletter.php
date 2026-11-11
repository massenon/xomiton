<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Newsletter\SubscribeToNewsletter;
use App\Application\Newsletter\UnsubscribeFromNewsletter;
use App\Domain\Account\Exceptions\NewsletterPreferenceRefused;
use App\Domain\Auth\ConsentVersion;
use App\Enums\ConsentKind;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Newsletter de la personne connectée (GET / PATCH me/newsletter, P6-06, prototype `simulateur.html` l.1279) :
 * abonnement (`subscribed`) et centres d'intérêt (`interests[]`, slugs de `newsletter_interests`, segments de
 * l'administration). « Une inscription depuis l'app vaut consentement explicite (horodaté) » :
 *  - e-mail du compte confirmé (OTP) : abonnement actif tout de suite, source `app`, ligne `consents` accordée — y
 *    compris pour une adresse désabonnée, puisque c'est la personne elle-même qui le demande (Q66 ne vise que les tiers) ;
 *  - e-mail non confirmé : double opt-in public (SubscribeToNewsletter, e-mail de confirmation) ;
 *  - adresse en rebond : jamais réactivée (422 `newsletter_bounced`).
 * `subscribed: false` passe par UnsubscribeFromNewsletter (retrait du consentement tracé). Aucune fréquence : le
 * schéma n'en a pas (prototype « mensuelle / bimensuelle », question ouverte du plan §7).
 */
final readonly class MemberNewsletter
{
    public function __construct(
        private SubscribeToNewsletter $subscribe,
        private UnsubscribeFromNewsletter $unsubscribe,
    ) {}

    public function find(User $user): ?NewsletterSubscriber
    {
        $byAccount = NewsletterSubscriber::query()->where('user_id', $user->getKey())->first();

        if ($byAccount !== null || $user->email === null || trim((string) $user->email) === '') {
            return $byAccount;
        }

        return NewsletterSubscriber::query()->whereEmail(mb_strtolower(trim((string) $user->email)))->first();
    }

    /**
     * @param  list<string>|null  $interests
     *
     * @throws NewsletterPreferenceRefused
     */
    public function update(User $user, ?bool $subscribed, ?array $interests): ?NewsletterSubscriber
    {
        $subscriber = $this->find($user);

        if ($subscribed === true) {
            $subscriber = $this->subscribe($user, $subscriber, $interests ?? []);
        } elseif ($subscribed === false && $subscriber !== null) {
            ($this->unsubscribe)($subscriber);
            $subscriber->refresh();
        }

        if ($interests !== null) {
            if ($subscriber === null || $subscriber->status === SubscriberStatus::Bounced) {
                throw NewsletterPreferenceRefused::notSubscribed();
            }

            $subscriber->syncInterests(array_values(array_unique($interests)));
        }

        return $subscriber?->refresh();
    }

    /**
     * @param  list<string>  $interests
     */
    private function subscribe(User $user, ?NewsletterSubscriber $subscriber, array $interests): ?NewsletterSubscriber
    {
        $email = mb_strtolower(trim((string) $user->email));

        if ($email === '') {
            throw NewsletterPreferenceRefused::emailMissing();
        }

        if ($subscriber?->status === SubscriberStatus::Bounced) {
            throw NewsletterPreferenceRefused::bounced();
        }

        if ($subscriber?->isActive()) {
            if ($subscriber->user_id === null) {
                $subscriber->forceFill(['user_id' => $user->getKey()])->save();
            }

            return $subscriber;
        }

        if (! $user->hasVerifiedEmail()) {
            ($this->subscribe)($email, $interests, SubscriberSource::App, (int) $user->getKey());

            return $this->find($user);
        }

        return DB::transaction(function () use ($user, $subscriber, $email): NewsletterSubscriber {
            $meta = now()->format('Y-m-d H:i:s').' · v'.ConsentVersion::current().' · '.SubscriberSource::App->value;

            $subscriber ??= new NewsletterSubscriber(['email' => $email, 'source' => SubscriberSource::App]);
            $subscriber->forceFill([
                'status' => SubscriberStatus::Active,
                'confirmed_at' => now(),
                'unsubscribed_at' => null,
                'unsubscribe_reason' => null,
                'consent_meta' => $meta,
                'user_id' => $user->getKey(),
            ])->save();

            Consent::query()->create([
                'user_id' => $user->getKey(),
                'email' => $email,
                'kind' => ConsentKind::Newsletter,
                'version' => ConsentVersion::current(),
                'granted' => true,
            ]);

            Audit::log('newsletter.subscribed_from_account', $subscriber, 'source=app', $user);

            return $subscriber;
        });
    }
}
