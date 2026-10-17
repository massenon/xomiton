<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CampaignStatus;
use Database\Factories\NewsletterCampaignFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Campagne newsletter (DATABASE_DICTIONARY.md §9 `newsletter_campaigns`). Le segment est résolu au
 * démarrage de l'envoi en lignes de `newsletter_campaign_recipients` ; les compteurs sont dénormalisés.
 */
class NewsletterCampaign extends Model
{
    /** @use HasFactory<NewsletterCampaignFactory> */
    use HasFactory, SoftDeletes;

    /** Taille des lots d'envoi (`NL_BATCH` du prototype, hébergement mutualisé). */
    public const BATCH_SIZE = 200;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'subject',
        'from_name',
        'reply_to',
        'preheader',
        'body',
        'segment',
        'status',
        'scheduled_at',
        'started_at',
        'sent_at',
        'recipients_count',
        'sent_count',
        'test_sent_to',
        'created_by',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'segment' => '[]',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment' => 'array',
            'status' => CampaignStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'sent_at' => 'datetime',
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<NewsletterCampaignRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(NewsletterCampaignRecipient::class, 'campaign_id');
    }

    /**
     * Abonné·es ciblé·es, via les destinataires.
     *
     * @return BelongsToMany<NewsletterSubscriber, $this>
     */
    public function subscribers(): BelongsToMany
    {
        return $this->belongsToMany(NewsletterSubscriber::class, 'newsletter_campaign_recipients', 'campaign_id', 'subscriber_id')
            ->withPivot(['sent_at', 'opened_at', 'bounced', 'unsubscribed', 'error'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeWithStatus(Builder $query, CampaignStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeDrafts(Builder $query): void
    {
        $query->where('status', CampaignStatus::Draft->value);
    }

    /**
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', CampaignStatus::Scheduled->value);
    }

    /**
     * Campagnes planifiées dont l'heure est passée : démarrées par `nl_process_due()` / la tâche planifiée.
     *
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->scheduled()->where('scheduled_at', '<=', now());
    }

    /**
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeSent(Builder $query): void
    {
        $query->where('status', CampaignStatus::Sent->value);
    }

    /**
     * Liste de l'administration : les plus récentes d'abord.
     *
     * @param  Builder<NewsletterCampaign>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isDue(): bool
    {
        return $this->status === CampaignStatus::Scheduled
            && $this->scheduled_at !== null
            && $this->scheduled_at->isPast();
    }

    /**
     * Abonné·es visé·es par le segment au moment de l'appel (`nl_segment()`).
     *
     * @return Builder<NewsletterSubscriber>
     */
    public function targetQuery(): Builder
    {
        /** @var array{tags?: list<string>, interests?: list<string>, since?: string|null, until?: string|null} $segment */
        $segment = $this->segment ?? [];

        return NewsletterSubscriber::query()->inSegment($segment);
    }

    /**
     * Libellé du segment pour l'administration (`nl_segment_label()`).
     */
    public function segmentLabel(): string
    {
        $segment = $this->segment ?? [];
        $parts = [];

        if (! empty($segment['tags'])) {
            $parts[] = 'tags : '.implode(', ', $segment['tags']);
        }

        if (! empty($segment['interests'])) {
            $parts[] = 'intérêts : '.implode(', ', $segment['interests']);
        }

        if (! empty($segment['since'])) {
            $parts[] = 'inscrit·es depuis le '.self::formatSegmentDate((string) $segment['since']);
        }

        if (! empty($segment['until'])) {
            $parts[] = 'jusqu’au '.self::formatSegmentDate((string) $segment['until']);
        }

        return $parts === [] ? 'Tous les abonné·es actifs' : implode(' · ', $parts);
    }

    /** Date du segment (`Y-m-d`) affichée à la française, comme le prototype ; valeur brute si illisible. */
    private static function formatSegmentDate(string $date): string
    {
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Throwable) {
            return $date;
        }
    }
}
