<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\NewsletterClickFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Clic sur un lien d'une campagne (DATABASE_DICTIONARY.md §9 `newsletter_clicks`) : une ligne par
 * (destinataire, URL) ; `url_hash` porte l'unicité car l'URL (2048) est trop longue pour un index.
 */
class NewsletterClick extends Model
{
    /** @use HasFactory<NewsletterClickFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'recipient_id',
        'url',
        'url_hash',
        'clicked_at',
        'clicks_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'clicks_count' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterClick $click): void {
            if (empty($click->url_hash)) {
                $click->url_hash = self::hashUrl((string) $click->url);
            }

            if ($click->clicked_at === null) {
                $click->clicked_at = now();
            }
        });
    }

    /**
     * @return BelongsTo<NewsletterCampaignRecipient, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(NewsletterCampaignRecipient::class, 'recipient_id');
    }

    /**
     * @param  Builder<NewsletterClick>  $query
     */
    public function scopeForUrl(Builder $query, string $url): void
    {
        $query->where('url_hash', self::hashUrl($url));
    }

    /**
     * Clics d'une campagne, pour les totaux et le classement des liens.
     *
     * @param  Builder<NewsletterClick>  $query
     */
    public function scopeForCampaign(Builder $query, NewsletterCampaign $campaign): void
    {
        $query->whereHas('recipient', fn (Builder $recipients) => $recipients->where('campaign_id', $campaign->getKey()));
    }

    public static function hashUrl(string $url): string
    {
        return hash('sha256', trim($url));
    }
}
