<?php

declare(strict_types=1);

namespace App\Application\Admin\Newsletter;

use App\Application\Mail\EmailTemplateService;
use App\Application\Newsletter\NewsletterLinks;
use App\Application\Newsletter\SubscribeToNewsletter;
use App\Application\Newsletter\UnsubscribeFromNewsletter;
use App\Domain\Auth\ConsentVersion;
use App\Domain\Newsletter\Exceptions\CommunicationRefused;
use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Abonné·es de la newsletter en administration (P14-02, `newsletter.manage_subscribers`) : ajout manuel, modification,
 * désabonnement, renvoi de la confirmation (double opt-in, RG-11), suppression définitive (RGPD), actions groupées et import
 * CSV avec attestation de consentement. L'adresse n'est jamais écrite dans le journal d'audit : l'identifiant suffit.
 * Les e-mails de confirmation partent par la boîte d'envoi (aucun SMTP dans une requête web).
 */
final class ManageSubscribers
{
    public function __construct(private readonly NewsletterLinks $links, private readonly UnsubscribeFromNewsletter $unsubscribe) {}

    /**
     * @param  array{email: string, name?: ?string, status: string, tags?: list<string>, interests?: list<string>, send_confirmation?: bool}  $data
     *
     * @throws CommunicationRefused
     */
    public function save(?NewsletterSubscriber $subscriber, array $data, User $actor): NewsletterSubscriber
    {
        $status = SubscriberStatus::from($data['status']);
        $name = Str::limit(trim((string) ($data['name'] ?? '')), 80, '');

        if ($subscriber === null) {
            $email = mb_strtolower(trim($data['email']));

            if (NewsletterSubscriber::query()->whereEmail($email)->exists()) {
                throw new CommunicationRefused('already_subscribed');
            }

            $subscriber = NewsletterSubscriber::query()->create([
                'email' => $email,
                'name' => $name === '' ? null : $name,
                'status' => SubscriberStatus::Pending,
                'source' => SubscriberSource::Admin,
                'consent_meta' => now()->format('Y-m-d H:i:s').' · v'.ConsentVersion::current().' · admin',
            ]);
            $this->applyStatus($subscriber, $status);
            $created = true;
        } else {
            $subscriber->forceFill(['name' => $name === '' ? null : $name])->save();

            if ($subscriber->status !== $status) {
                $this->applyStatus($subscriber, $status);
            }
            $created = false;
        }

        $subscriber->syncTags($this->known(NewsletterTag::class, $data['tags'] ?? []));
        $subscriber->syncInterests($this->known(NewsletterInterest::class, $data['interests'] ?? []));

        if ($created && $subscriber->isPending() && ($data['send_confirmation'] ?? false)) {
            $this->sendConfirmation($subscriber);
        }

        Audit::log($created ? 'newsletter.subscriber_added' : 'newsletter.subscriber_updated', $subscriber, 'abonné='.$subscriber->getKey(), $actor);

        return $subscriber;
    }

    public function unsubscribe(NewsletterSubscriber $subscriber, User $actor): void
    {
        ($this->unsubscribe)($subscriber, 'other');
        Audit::log('newsletter.subscriber_unsubscribed', $subscriber, 'abonné='.$subscriber->getKey(), $actor);
    }

    /**
     * @throws CommunicationRefused si l'abonné·e n'est pas en attente de confirmation
     */
    public function resendConfirmation(NewsletterSubscriber $subscriber, User $actor): void
    {
        if (! $subscriber->isPending()) {
            throw new CommunicationRefused('not_pending');
        }

        $this->sendConfirmation($subscriber);
        Audit::log('newsletter.confirmation_resent', $subscriber, 'abonné='.$subscriber->getKey(), $actor);
    }

    /** Suppression définitive (RGPD) : l'abonné·e et ses lignes de destinataire disparaissent (cascade). */
    public function delete(NewsletterSubscriber $subscriber, User $actor): void
    {
        $id = $subscriber->getKey();
        $subscriber->delete();
        Audit::log('newsletter.subscriber_deleted', null, 'abonné='.$id, $actor);
    }

    /**
     * Action groupée : `unsubscribe`, `delete` ou `tag:<slug>`. Renvoie le nombre d'abonné·es traité·es.
     *
     * @param  list<int|string>  $ids
     */
    public function bulk(string $operation, array $ids, User $actor): int
    {
        $count = 0;

        foreach (NewsletterSubscriber::query()->whereIn('id', array_map('intval', $ids))->get() as $subscriber) {
            if ($operation === 'delete') {
                $subscriber->delete();
            } elseif ($operation === 'unsubscribe') {
                ($this->unsubscribe)($subscriber, 'other');
            } elseif (str_starts_with($operation, 'tag:')) {
                $tags = $this->known(NewsletterTag::class, [...(array) $subscriber->tags, substr($operation, 4)]);
                $subscriber->syncTags($tags);
            } else {
                continue;
            }

            $count++;
        }

        Audit::log('newsletter.subscribers_bulk', null, sprintf('action=%s ; abonnés=%d', Str::before($operation, ':'), $count), $actor);

        return $count;
    }

    /**
     * Import CSV : colonnes « e-mail ; nom ; tags ; centres d'intérêt », séparateur `;` ou `,`, en-tête facultatif. Les
     * adresses déjà connues sont ignorées (un désabonné·e ou un rebond n'est jamais réactivé·e par import). Sans double
     * opt-in, l'attestation de consentement est obligatoire et les personnes sont actives ; avec, elles reçoivent le
     * message de confirmation.
     *
     * @return array{imported: int, duplicates: int, invalid: int}
     *
     * @throws CommunicationRefused
     */
    public function import(string $csv, bool $attested, bool $doubleOptIn, ?string $extraTag, User $actor): array
    {
        if (! $attested) {
            throw new CommunicationRefused('consent_required');
        }

        $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv) ?? $csv;
        $lines = preg_split('/\r\n|\n|\r/', trim($csv)) ?: [];
        $first = (string) ($lines[0] ?? '');
        $delimiter = substr_count($first, ';') >= substr_count($first, ',') ? ';' : ',';
        $rows = array_map(static fn (string $line): array => str_getcsv($line, $delimiter, '"', ''), array_filter($lines, static fn (string $l): bool => trim($l) !== ''));

        if ($rows !== [] && filter_var(trim((string) ($rows[array_key_first($rows)][0] ?? '')), FILTER_VALIDATE_EMAIL) === false) {
            array_shift($rows); // en-tête
        }

        $max = max(1, (int) config('xomiton.newsletter.import_max_rows', 5000));

        if (count($rows) > $max) {
            throw new CommunicationRefused('import_too_long', ['max' => $max]);
        }

        $result = ['imported' => 0, 'duplicates' => 0, 'invalid' => 0];
        $meta = now()->format('Y-m-d H:i:s').' · v'.ConsentVersion::current().' · import attesté';

        foreach ($rows as $row) {
            $email = mb_strtolower(trim((string) ($row[0] ?? '')));

            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 190) {
                $result['invalid']++;

                continue;
            }

            if (NewsletterSubscriber::query()->whereEmail($email)->exists()) {
                $result['duplicates']++;

                continue;
            }

            $subscriber = DB::transaction(function () use ($email, $row, $doubleOptIn, $extraTag, $meta): NewsletterSubscriber {
                $subscriber = NewsletterSubscriber::query()->create([
                    'email' => $email,
                    'name' => ($name = Str::limit(trim((string) ($row[1] ?? '')), 80, '')) === '' ? null : $name,
                    'status' => $doubleOptIn ? SubscriberStatus::Pending : SubscriberStatus::Active,
                    'source' => SubscriberSource::Import,
                    'confirmed_at' => $doubleOptIn ? null : now(),
                    'consent_meta' => $meta,
                ]);
                $subscriber->syncTags($this->catalogSlugs(NewsletterTag::class, [...explode(',', (string) ($row[2] ?? '')), (string) $extraTag]));
                $subscriber->syncInterests($this->catalogSlugs(NewsletterInterest::class, explode(',', (string) ($row[3] ?? ''))));

                return $subscriber;
            });

            if ($doubleOptIn) {
                $this->sendConfirmation($subscriber);
            }

            $result['imported']++;
        }

        Audit::log('newsletter.subscribers_imported', null, sprintf('ajoutés=%d ; doublons=%d ; invalides=%d', $result['imported'], $result['duplicates'], $result['invalid']), $actor);

        return $result;
    }

    private function applyStatus(NewsletterSubscriber $subscriber, SubscriberStatus $status): void
    {
        match ($status) {
            SubscriberStatus::Active => $subscriber->confirm(),
            SubscriberStatus::Unsubscribed => $subscriber->unsubscribe(),
            SubscriberStatus::Bounced => $subscriber->markBounced(),
            SubscriberStatus::Pending => $subscriber->forceFill(['status' => SubscriberStatus::Pending])->save(),
        };
    }

    private function sendConfirmation(NewsletterSubscriber $subscriber): void
    {
        $log = EmailTemplateService::enqueue(SubscribeToNewsletter::CONFIRMATION_TEMPLATE, $subscriber->email, [
            'activation_link' => $this->links->confirmUrl($subscriber),
            'unsubscribe_link' => $this->links->unsubscribeUrl($subscriber),
            'user_email' => $subscriber->email,
        ], 'newsletter-optin');

        if ($log->ok) {
            $subscriber->forceFill(['confirmation_sent_at' => now()])->save();
        }
    }

    /**
     * Slugs du catalogue parmi les valeurs données.
     *
     * @param  class-string<NewsletterTag|NewsletterInterest>  $model
     * @param  list<string>  $slugs
     * @return list<string>
     */
    private function known(string $model, array $slugs): array
    {
        return $model::query()->whereIn('slug', array_map('strval', $slugs))->orderBy('slug')->pluck('slug')->map(static fn (mixed $s): string => (string) $s)->all();
    }

    /**
     * Slugs du catalogue pour des valeurs saisies par slug **ou** par libellé (casse ignorée).
     *
     * @param  class-string<NewsletterTag|NewsletterInterest>  $model
     * @param  list<string>  $values
     * @return list<string>
     */
    private function catalogSlugs(string $model, array $values): array
    {
        $values = array_values(array_filter(array_map(static fn (string $v): string => mb_strtolower(trim($v)), $values), static fn (string $v): bool => $v !== ''));

        if ($values === []) {
            return [];
        }

        return $model::query()->get(['slug', 'label'])
            ->filter(static fn ($row): bool => in_array(mb_strtolower((string) $row->slug), $values, true) || in_array(mb_strtolower((string) $row->label), $values, true))
            ->pluck('slug')->map(static fn (mixed $s): string => (string) $s)->values()->all();
    }
}
