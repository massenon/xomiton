<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GatewayDeliveryStatus;
use Database\Factories\OutboundDeliveryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Remise d'une publication sortante sur un réseau (DATABASE_DICTIONARY.md §8 `outbound_deliveries`) :
 * une ligne par couple publication ↔ passerelle ; `manuel` quand l'équipe a publié à la main (réseau sans API).
 */
class OutboundDelivery extends Model
{
    /** @use HasFactory<OutboundDeliveryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'outbound_post_id',
        'social_channel_id',
        'status',
        'external_id',
        'external_url',
        'error',
        'attempts',
        'published_at',
        'marked_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GatewayDeliveryStatus::class,
            'attempts' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<OutboundPost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(OutboundPost::class, 'outbound_post_id');
    }

    /**
     * @return BelongsTo<SocialChannel, $this>
     */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(SocialChannel::class, 'social_channel_id');
    }

    /**
     * Personne qui a marqué la publication manuelle.
     *
     * @return BelongsTo<User, $this>
     */
    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * @param  Builder<OutboundDelivery>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', GatewayDeliveryStatus::Pending->value);
    }

    /**
     * @param  Builder<OutboundDelivery>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', GatewayDeliveryStatus::Failed->value);
    }

    /**
     * Remises effectivement en ligne (API ou manuelles).
     *
     * @param  Builder<OutboundDelivery>  $query
     */
    public function scopeDelivered(Builder $query): void
    {
        $query->whereIn('status', [GatewayDeliveryStatus::Published->value, GatewayDeliveryStatus::Manual->value]);
    }

    /**
     * @param  Builder<OutboundDelivery>  $query
     */
    public function scopeForChannel(Builder $query, SocialChannel|int $channel): void
    {
        $query->where('social_channel_id', $channel instanceof SocialChannel ? $channel->getKey() : $channel);
    }

    /**
     * Remises à rejouer : en attente ou en échec, sous le plafond `xomiton.gateways.max_attempts`.
     *
     * @param  Builder<OutboundDelivery>  $query
     */
    public function scopeRetryable(Builder $query): void
    {
        $query->whereIn('status', [GatewayDeliveryStatus::Pending->value, GatewayDeliveryStatus::Failed->value])
            ->where('attempts', '<', self::maxAttempts());
    }

    public function isDelivered(): bool
    {
        return $this->status->isDelivered();
    }

    public function isManual(): bool
    {
        return $this->status === GatewayDeliveryStatus::Manual;
    }

    public function canRetry(): bool
    {
        return ! $this->isDelivered() && $this->attempts < self::maxAttempts();
    }

    public static function maxAttempts(): int
    {
        return (int) config('xomiton.gateways.max_attempts', 5);
    }
}
