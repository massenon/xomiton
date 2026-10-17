<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriberSource;
use App\Enums\SubscriberStatus;
use Database\Factories\NewsletterSubscriberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Abonné·e à la newsletter (DATABASE_DICTIONARY.md §9 `newsletter_subscribers`). Les colonnes json `tags` et
 * `interests` servent à l'affichage ; la segmentation s'appuie sur les tables de liaison (ADR-20), tenues
 * en cohérence par `syncTags()` / `syncInterests()`. Le `token` signe les liens de confirmation et de
 * désinscription ; il n'est jamais sérialisé.
 */
class NewsletterSubscriber extends Model
{
    /** @use HasFactory<NewsletterSubscriberFactory> */
    use HasFactory;

    /** Longueur du jeton (`nl_token()` du prototype : 16 octets en hexadécimal). */
    public const TOKEN_LENGTH = 32;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'email',
        'name',
        'status',
        'tags',
        'interests',
        'source',
        'token',
        'confirmed_at',
        'confirmation_sent_at',
        'unsubscribed_at',
        'unsubscribe_reason',
        'bounced_at',
        'bounce_reason',
        'consent_meta',
        'user_id',
        'last_opened_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubscriberStatus::class,
            'tags' => 'array',
            'interests' => 'array',
            'source' => SubscriberSource::class,
            'confirmed_at' => 'datetime',
            'confirmation_sent_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
            'bounced_at' => 'datetime',
            'last_opened_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterSubscriber $subscriber): void {
            $subscriber->email = mb_strtolower(trim((string) $subscriber->email));

            if (empty($subscriber->token)) {
                $subscriber->token = self::generateToken();
            }
        });
    }

    /**
     * Compte membre relié, le cas échéant.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<NewsletterTag, $this>
     */
    public function tagModels(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterTag::class, 'newsletter_subscriber_tags', 'subscriber_id', 'tag_id')
            ->using(NewsletterSubscriberTag::class);
    }

    /**
     * @return BelongsToMany<NewsletterInterest, $this>
     */
    public function interestModels(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterInterest::class, 'newsletter_subscriber_interests', 'subscriber_id', 'interest_id')
            ->using(NewsletterSubscriberInterest::class);
    }

    /**
     * @return HasMany<NewsletterCampaignRecipient, $this>
     */
    public function campaignRecipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class, 'subscriber_id');
    }

    /**
     * Campagnes reçues, via les destinataires (inverse de NewsletterCampaign::subscribers()).
     *
     * @return BelongsToMany<NewsletterCampaign, $this>
     */
    public function campaigns(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterCampaign::class, 'newsletter_campaign_recipients', 'subscriber_id', 'campaign_id')
            ->withPivot(['sent_at', 'opened_at', 'bounced', 'unsubscribed', 'error'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', SubscriberStatus::Active->value);
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', SubscriberStatus::Pending->value);
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopeWithStatus(Builder $query, SubscriberStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopeWhereEmail(Builder $query, string $email): void
    {
        $query->where('email', mb_strtolower(trim($email)));
    }

    /**
     * @param  Builder<NewsletterSubscriber>  $query
     */
    public function scopeWhereToken(Builder $query, string $token): void
    {
        $query->where('token', $token);
    }

    /**
     * Abonné·es portant au moins un des tags donnés (slugs), via la table de liaison.
     *
     * @param  Builder<NewsletterSubscriber>  $query
     * @param  list<string>  $slugs
     */
    public function scopeWithAnyTag(Builder $query, array $slugs): void
    {
        $query->whereHas('tagModels', fn (Builder $tags) => $tags->whereIn('newsletter_tags.slug', $slugs));
    }

    /**
     * Abonné·es portant au moins un des centres d'intérêt donnés (slugs), via la table de liaison.
     *
     * @param  Builder<NewsletterSubscriber>  $query
     * @param  list<string>  $slugs
     */
    public function scopeWithAnyInterest(Builder $query, array $slugs): void
    {
        $query->whereHas('interestModels', fn (Builder $interests) => $interests->whereIn('newsletter_interests.slug', $slugs));
    }

    /**
     * Cible d'une campagne (`nl_segment()` du prototype) : actif·ves, filtré·es par tags, centres d'intérêt
     * et date d'inscription. Un segment vide vise tou·tes les abonné·es actif·ves.
     *
     * @param  Builder<NewsletterSubscriber>  $query
     * @param  array{tags?: list<string>, interests?: list<string>, since?: string|null, until?: string|null}  $segment
     */
    public function scopeInSegment(Builder $query, array $segment): void
    {
        $query->active();

        if (! empty($segment['tags'])) {
            $query->withAnyTag($segment['tags']);
        }

        if (! empty($segment['interests'])) {
            $query->withAnyInterest($segment['interests']);
        }

        if (! empty($segment['since'])) {
            $query->whereDate('created_at', '>=', $segment['since']);
        }

        if (! empty($segment['until'])) {
            $query->whereDate('created_at', '<=', $segment['until']);
        }
    }

    public function isActive(): bool
    {
        return $this->status === SubscriberStatus::Active;
    }

    public function isPending(): bool
    {
        return $this->status === SubscriberStatus::Pending;
    }

    /** Confirmation du double opt-in : active l'abonnement et date le consentement. */
    public function confirm(): void
    {
        $this->forceFill([
            'status' => SubscriberStatus::Active,
            'confirmed_at' => $this->confirmed_at ?? now(),
            'unsubscribed_at' => null,
            'unsubscribe_reason' => null,
        ])->save();
    }

    public function unsubscribe(?string $reason = null): void
    {
        $this->forceFill([
            'status' => SubscriberStatus::Unsubscribed,
            'unsubscribed_at' => now(),
            'unsubscribe_reason' => $reason !== null ? mb_substr($reason, 0, 200) : null,
        ])->save();
    }

    public function markBounced(?string $reason = null): void
    {
        $this->forceFill([
            'status' => SubscriberStatus::Bounced,
            'bounced_at' => now(),
            'bounce_reason' => $reason !== null ? mb_substr($reason, 0, 200) : null,
        ])->save();
    }

    /**
     * Remplace les tags, dans l’ordre donné : colonne json d’affichage et table de liaison ensemble (slugs inconnus ignorés).
     *
     * @param  list<string>  $slugs
     */
    public function syncTags(array $slugs): void
    {
        $tags = NewsletterTag::query()->whereIn('slug', $slugs)->get()
            ->sortBy(fn (NewsletterTag $tag): int => (int) array_search($tag->slug, $slugs, true))
            ->values();

        $this->tagModels()->sync($tags->modelKeys());
        $this->forceFill(['tags' => $tags->pluck('slug')->values()->all()])->save();
    }

    /**
     * Remplace les centres d'intérêt : colonne json d'affichage et table de liaison ensemble.
     *
     * @param  list<string>  $slugs
     */
    public function syncInterests(array $slugs): void
    {
        $interests = NewsletterInterest::query()->whereIn('slug', $slugs)->get()
            ->sortBy(fn (NewsletterInterest $interest): int => (int) array_search($interest->slug, $slugs, true))
            ->values();

        $this->interestModels()->sync($interests->modelKeys());
        $this->forceFill(['interests' => $interests->pluck('slug')->values()->all()])->save();
    }

    /** Jeton hexadécimal de 32 caractères (`nl_token()`). */
    public static function generateToken(): string
    {
        return Str::lower(bin2hex(random_bytes(self::TOKEN_LENGTH / 2)));
    }
}
