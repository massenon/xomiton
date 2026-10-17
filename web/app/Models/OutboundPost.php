<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use Database\Factories\OutboundPostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Publication sortante planifiée vers un ou plusieurs réseaux (DATABASE_DICTIONARY.md §8 `outbound_posts`).
 * Le détail par réseau (état, identifiant externe) est porté par `outbound_deliveries`.
 */
class OutboundPost extends Model
{
    /** @use HasFactory<OutboundPostFactory> */
    use HasFactory;

    /** Bornes de validation du prototype (admin/passerelles.php `schedule`). */
    public const MESSAGE_MIN_LENGTH = 10;

    public const MESSAGE_MAX_LENGTH = 500;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kind',
        'reference',
        'message',
        'media_path',
        'scheduled_at',
        'status',
        'published_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => OutboundKind::class,
            'scheduled_at' => 'datetime',
            'status' => OutboundStatus::class,
            'published_at' => 'datetime',
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
     * @return HasMany<OutboundDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboundDelivery::class);
    }

    /**
     * Réseaux ciblés, via les remises.
     *
     * @return BelongsToMany<SocialChannel, $this>
     */
    public function channels(): BelongsToMany
    {
        return $this->belongsToMany(SocialChannel::class, 'outbound_deliveries')
            ->withPivot(['status', 'external_id', 'external_url', 'error', 'attempts', 'published_at', 'marked_by'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<OutboundPost>  $query
     */
    public function scopeWithStatus(Builder $query, OutboundStatus $status): void
    {
        $query->where('status', $status->value);
    }

    /**
     * @param  Builder<OutboundPost>  $query
     */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', OutboundStatus::Scheduled->value);
    }

    /**
     * Publications planifiées dont l'échéance est atteinte : traitées par la tâche planifiée de publication.
     *
     * @param  Builder<OutboundPost>  $query
     */
    public function scopeDue(Builder $query): void
    {
        $query->scheduled()->where('scheduled_at', '<=', now());
    }

    /**
     * @param  Builder<OutboundPost>  $query
     */
    public function scopeOfKind(Builder $query, OutboundKind $kind): void
    {
        $query->where('kind', $kind->value);
    }

    /**
     * File de publication de l'administration : les plus récentes d'abord.
     *
     * @param  Builder<OutboundPost>  $query
     */
    public function scopeQueue(Builder $query): void
    {
        $query->orderByDesc('scheduled_at')->orderByDesc('id');
    }

    public function isScheduled(): bool
    {
        return $this->status === OutboundStatus::Scheduled;
    }

    public function isDue(): bool
    {
        return $this->isScheduled() && $this->scheduled_at->isPast();
    }

    /** Modèle de message clé repris par la publication, s'il existe encore. */
    public function template(): ?GatewayTemplate
    {
        if ($this->kind !== OutboundKind::Template || $this->reference === null || ! ctype_digit($this->reference)) {
            return null;
        }

        return GatewayTemplate::query()->find((int) $this->reference);
    }
}
