<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterTag;
use Illuminate\Support\Str;

/**
 * newsletter_subscribers.json → newsletter_subscribers (email, name, status, source, token conservé,
 * created → created_at, confirmed_at, consent → consent_meta, unsub_at / unsub_reason, bounced_at /
 * bounce_reason, user_id si un compte porte l'e-mail) + tables de liaison tags / centres d'intérêt
 * (ADR-20). Clé naturelle : l'e-mail.
 */
final class ImportNewsletterSubscribers extends AbstractImporter
{
    public static function collection(): string
    {
        return 'newsletter_subscribers';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $email = mb_strtolower($this->required($row['email'] ?? null, 'email', 190));

        if (! str_contains($email, '@')) {
            $this->reject(sprintf('e-mail invalide « %s »', $email));
        }

        $tags = $this->slugs($row['tags'] ?? []);
        $interests = $this->slugs($row['interests'] ?? []);
        $token = $this->text($row['token'] ?? null, NewsletterSubscriber::TOKEN_LENGTH);

        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);

        $subscriber->fill([
            'name' => $this->text($row['name'] ?? null, 80),
            'status' => SubscriberStatus::fromPrototype((string) ($row['status'] ?? '')),
            'tags' => array_keys($tags),
            'interests' => array_values(array_map(static fn (string $label): string => $label, $interests)),
            'source' => SubscriberSource::fromPrototype((string) ($row['source'] ?? 'footer')),
            'confirmed_at' => $this->ctx->dateTime($row['confirmed_at'] ?? null),
            'unsubscribed_at' => $this->ctx->dateTime($row['unsub_at'] ?? $row['unsubscribed_at'] ?? null),
            'unsubscribe_reason' => $this->text($row['unsub_reason'] ?? $row['unsubscribe_reason'] ?? null, 200),
            'bounced_at' => $this->ctx->dateTime($row['bounced_at'] ?? $row['bounce_at'] ?? null),
            'bounce_reason' => $this->text($row['bounce_reason'] ?? null, 200),
            'consent_meta' => $this->text($row['consent'] ?? null, 255),
            'user_id' => $this->ctx->userIdByEmail($email),
        ]);

        if ($token !== null && preg_match('/^[a-f0-9]{32}$/i', $token) === 1
            && ! NewsletterSubscriber::query()->where('token', mb_strtolower($token))->where('email', '!=', $email)->exists()) {
            $subscriber->token = mb_strtolower($token);
        }

        if (($createdAt = $this->ctx->dateTime($row['created'] ?? null)) !== null) {
            $subscriber->created_at = $createdAt;
        }

        $subscriber->save();

        $subscriber->tagModels()->sync($this->tagIds($tags));
        $subscriber->interestModels()->sync($this->interestIds($interests));

        $this->ctx->remember('subscribers', $this->prototypeId($row, $index), (int) $subscriber->getKey());
        $this->track($subscriber);
    }

    /**
     * Libellés ou slugs du prototype → slug => libellé.
     *
     * @return array<string, string>
     */
    private function slugs(mixed $values): array
    {
        if (is_string($values)) {
            $values = explode(',', $values);
        }

        $result = [];

        foreach (is_array($values) ? $values : [] as $value) {
            $label = trim((string) $value);
            $slug = Str::slug($label);

            if ($slug !== '') {
                $result[$slug] = $label;
            }
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $tags
     * @return list<int>
     */
    private function tagIds(array $tags): array
    {
        $ids = [];

        foreach ($tags as $slug => $label) {
            $tag = NewsletterTag::query()->firstOrCreate(['slug' => $slug], ['label' => Str::ucfirst($label)]);

            if ($tag->wasRecentlyCreated) {
                $this->ctx->note(sprintf('tag newsletter « %s » créé (absent du référentiel)', $label));
            }

            $ids[] = (int) $tag->getKey();
        }

        return $ids;
    }

    /**
     * @param  array<string, string>  $interests
     * @return list<int>
     */
    private function interestIds(array $interests): array
    {
        $ids = [];

        foreach ($interests as $slug => $label) {
            $interest = NewsletterInterest::query()->firstOrCreate(['slug' => $slug], ['label' => $label]);

            if ($interest->wasRecentlyCreated) {
                $this->ctx->note(sprintf('centre d’intérêt newsletter « %s » créé (absent du référentiel)', $label));
            }

            $ids[] = (int) $interest->getKey();
        }

        return $ids;
    }
}
