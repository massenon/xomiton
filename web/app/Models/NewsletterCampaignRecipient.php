<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterCampaignRecipientFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Destinataire d'une campagne (DATABASE_DICTIONARY.md §9 `newsletter_campaign_recipients`) : porte
 * l'envoi, l'ouverture, le rebond et la désinscription qui alimentent les taux de la campagne.
 */
class NewsletterCampaignRecipient extends Model
{
    /** @use HasFactory<NewsletterCampaignRecipientFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'campaign_id',
        'subscriber_id',
        'sent_at',
        'opened_at',
        'bounced',
        'unsubscribed',
        'error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'opened_at' => 'datetime',
            'bounced' => 'boolean',
            'unsubscribed' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<NewsletterCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaign::class, 'campaign_id');
    }

    /**
     * @return BelongsTo<NewsletterSubscriber, $this>
     */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(NewsletterSubscriber::class, 'subscriber_id');
    }

    /**
     * @return HasMany<NewsletterClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(NewsletterClick::class, 'recipient_id');
    }

    /**
     * @param  Builder<NewsletterCampaignRecipient>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('sent_at')->whereNull('error');
    }

    /**
     * @param  Builder<NewsletterCampaignRecipient>  $query
     */
    public function scopeSent(Builder $query): void
    {
        $query->whereNotNull('sent_at');
    }

    /**
     * Envoyés et non rebondis : dénominateur des taux d'ouverture et de clic.
     *
     * @param  Builder<NewsletterCampaignRecipient>  $query
     */
    public function scopeDelivered(Builder $query): void
    {
        $query->sent()->where('bounced', false);
    }

    /**
     * @param  Builder<NewsletterCampaignRecipient>  $query
     */
    public function scopeOpened(Builder $query): void
    {
        $query->whereNotNull('opened_at');
    }

    /**
     * @param  Builder<NewsletterCampaignRecipient>  $query
     */
    public function scopeBounced(Builder $query): void
    {
        $query->where('bounced', true);
    }

    public function isDelivered(): bool
    {
        return $this->sent_at !== null && ! $this->bounced;
    }

    public function hasOpened(): bool
    {
        return $this->opened_at !== null;
    }

    /** Première ouverture seulement : les suivantes ne changent pas la date. */
    public function markOpened(): void
    {
        if ($this->opened_at !== null) {
            return;
        }

        $this->forceFill(['opened_at' => now()])->save();
        $this->subscriber?->forceFill(['last_opened_at' => now()])->save();
    }

    /**
     * Enregistre un clic : une ligne par URL, les clics répétés incrémentent `clicks_count`.
     */
    public function recordClick(string $url): NewsletterClick
    {
        $click = $this->clicks()->firstOrNew(['url_hash' => NewsletterClick::hashUrl($url)]);

        if ($click->exists) {
            $click->forceFill(['clicks_count' => $click->clicks_count + 1])->save();

            return $click;
        }

        $click->forceFill(['url' => $url, 'clicked_at' => now()])->save();

        return $click;
    }
}
