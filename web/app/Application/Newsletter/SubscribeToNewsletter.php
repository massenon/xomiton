<?php

declare(strict_types=1);

namespace App\Application\Newsletter;

use App\Application\Mail\EmailTemplateService;
use App\Domain\Auth\ConsentVersion;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Timebox;

/**
 * Inscription publique à la newsletter, double opt-in (P4-07, FR-COM-02 ; prototype `nl_subscribe()`).
 *
 * Anti-énumération (DEVELOPMENT_STANDARDS.md §7) : l'appelant renvoie la même réponse quel que soit le cas, et tout le
 * travail tourne dans une Timebox à durée plancher (`xomiton.newsletter.response_floor_ms`) : adresse inconnue, en
 * attente, déjà abonnée, désabonnée, en rebond ou pot de miel rempli prennent le même temps. Aucun SMTP dans la
 * requête : l'e-mail `newsletter-confirmation` part par la boîte d'envoi (`EmailTemplateService::enqueue`, D7).
 *
 *  - inconnue : abonné·e `en_attente` (source, centres d'intérêt, trace de consentement sans IP), e-mail de confirmation ;
 *  - en attente : nouvel e-mail au plus une fois par `confirmation_resend_minutes`, avec le même jeton tant que le
 *    lien déjà envoyé vaut encore (`confirmation_ttl_hours`) : un tiers ne peut pas invalider le lien reçu par la
 *    personne ; nouveau jeton seulement si aucun lien valable n'est parti (expiré, import) ;
 *  - désabonnée : le refus reste enregistré (`desabonne`, date et motif, jeton et lien de désinscription inchangés)
 *    tant que la personne n'a pas confirmé ; seul l'e-mail part (même rythme), et `confirmation_sent_at` postérieur à
 *    `unsubscribed_at` est ce qui rend le lien acceptable par ConfirmSubscription ;
 *  - active ou en rebond : rien (aucun e-mail, aucune modification des préférences par un tiers).
 */
final readonly class SubscribeToNewsletter
{
    public const CONFIRMATION_TEMPLATE = 'newsletter-confirmation';

    public function __construct(private Timebox $timebox, private NewsletterLinks $links) {}

    /**
     * @param  list<string>  $interests  slugs de `newsletter_interests` (déjà validés)
     * @param  bool  $trapped  pot de miel rempli : on fait semblant (même réponse, même durée)
     */
    public function __invoke(string $email, array $interests = [], SubscriberSource $source = SubscriberSource::Footer, ?int $userId = null, bool $trapped = false): void
    {
        $this->timebox->call(function () use ($email, $interests, $source, $userId, $trapped): void {
            if (! $trapped) {
                $this->subscribe(mb_strtolower(trim($email)), $interests, $source, $userId);
            }
        }, max(0, (int) config('xomiton.newsletter.response_floor_ms', 600)) * 1000);
    }

    /**
     * @param  list<string>  $interests
     */
    private function subscribe(string $email, array $interests, SubscriberSource $source, ?int $userId): void
    {
        $subscriber = NewsletterSubscriber::query()->whereEmail($email)->first();

        if ($subscriber === null) {
            try {
                $subscriber = NewsletterSubscriber::query()->create([
                    'email' => $email,
                    'status' => SubscriberStatus::Pending,
                    'source' => $source,
                    'consent_meta' => $this->consentMeta($source),
                    'user_id' => $userId,
                ]);
            } catch (UniqueConstraintViolationException) {
                return; // double envoi simultané : la première requête s'en occupe
            }

            $this->sendConfirmation($subscriber, $interests);

            return;
        }

        $resend = match ($subscriber->status) {
            SubscriberStatus::Pending, SubscriberStatus::Unsubscribed => $this->cooledDown($subscriber),
            SubscriberStatus::Active, SubscriberStatus::Bounced => false,
        };

        if (! $resend) {
            return;
        }

        $subscriber->forceFill([
            'token' => $this->keepsToken($subscriber) ? $subscriber->token : NewsletterSubscriber::generateToken(),
            'consent_meta' => $this->consentMeta($source),
            'user_id' => $subscriber->user_id ?? $userId,
        ])->save();

        $this->sendConfirmation($subscriber, $interests);
    }

    /**
     * @param  list<string>  $interests
     */
    private function sendConfirmation(NewsletterSubscriber $subscriber, array $interests): void
    {
        if ($interests !== []) {
            $subscriber->syncInterests($interests);
        }

        $log = EmailTemplateService::enqueue(self::CONFIRMATION_TEMPLATE, $subscriber->email, [
            'activation_link' => $this->links->confirmUrl($subscriber),
            'unsubscribe_link' => $this->links->unsubscribeUrl($subscriber),
            'user_email' => $subscriber->email,
        ], 'newsletter-optin');

        if ($log->ok) {
            $subscriber->forceFill(['confirmation_sent_at' => now()])->save();
        }
    }

    private function cooledDown(NewsletterSubscriber $subscriber): bool
    {
        $minutes = max(0, (int) config('xomiton.newsletter.confirmation_resend_minutes', 10));

        return $subscriber->confirmation_sent_at === null || $subscriber->confirmation_sent_at->lte(now()->subMinutes($minutes));
    }

    /**
     * Désabonnée : toujours le même jeton (l'ancien lien de désinscription reste valable). En attente : le même tant
     * que le dernier lien envoyé n'a pas expiré.
     */
    private function keepsToken(NewsletterSubscriber $subscriber): bool
    {
        if ((string) $subscriber->token === '') {
            return false;
        }

        if ($subscriber->status === SubscriberStatus::Unsubscribed) {
            return true;
        }

        return $subscriber->confirmation_sent_at !== null
            && $subscriber->confirmation_sent_at->gte(now()->subHours(ConfirmSubscription::ttlHours()));
    }

    /** Trace de la demande : date, version des textes, origine ; jamais d'IP (minimisation). */
    private function consentMeta(SubscriberSource $source): string
    {
        return now()->format('Y-m-d H:i:s').' · v'.ConsentVersion::current().' · '.$source->value;
    }
}
