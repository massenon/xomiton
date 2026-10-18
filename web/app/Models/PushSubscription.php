<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\DeliveryResult;
use Database\Factories\PushSubscriptionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Abonnement push d'un membre (DATABASE_DICTIONARY.md §9 `push_subscriptions`) : Web Push (VAPID,
 * `public_key` + `auth_token`) ou FCM (jeton d'appareil dans `endpoint`). Les secrets ne sont jamais sérialisés.
 */
class PushSubscription extends Model
{
    /** @use HasFactory<PushSubscriptionFactory> */
    use HasFactory;

    public const KIND_WEBPUSH = 'webpush';

    public const KIND_FCM = 'fcm';

    /** @var list<string> */
    public const KINDS = [self::KIND_WEBPUSH, self::KIND_FCM];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'kind',
        'endpoint',
        'endpoint_hash',
        'public_key',
        'auth_token',
        'device_label',
        'last_used_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'endpoint',
        'public_key',
        'auth_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (PushSubscription $subscription): void {
            // L'unicité porte sur l'empreinte : toujours dérivée de l'endpoint.
            $subscription->endpoint_hash = self::hashEndpoint((string) $subscription->endpoint);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<PushSubscription>  $query
     */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * @param  Builder<PushSubscription>  $query
     */
    public function scopeWebPush(Builder $query): void
    {
        $query->where('kind', self::KIND_WEBPUSH);
    }

    /**
     * @param  Builder<PushSubscription>  $query
     */
    public function scopeFcm(Builder $query): void
    {
        $query->where('kind', self::KIND_FCM);
    }

    /**
     * @param  Builder<PushSubscription>  $query
     */
    public function scopeForEndpoint(Builder $query, string $endpoint): void
    {
        $query->where('endpoint_hash', self::hashEndpoint($endpoint));
    }

    public function isWebPush(): bool
    {
        return $this->kind === self::KIND_WEBPUSH;
    }

    public function isFcm(): bool
    {
        return $this->kind === self::KIND_FCM;
    }

    public function touchLastUsed(): void
    {
        $this->forceFill(['last_used_at' => now()])->save();
    }

    /**
     * Applique le résultat d'une remise (P5-03) : abonnement disparu (404 / 410, jeton FCM désinscrit) → supprimé ;
     * succès → `last_used_at`. Une seule règle pour les canaux push de la Phase 3 et le DeliveryRunner des alertes.
     */
    public function recordDelivery(DeliveryResult $result): void
    {
        if ($result->gone) {
            $this->delete();

            return;
        }

        if ($result->ok) {
            $this->touchLastUsed();
        }
    }

    public static function hashEndpoint(string $endpoint): string
    {
        return hash('sha256', trim($endpoint));
    }
}
