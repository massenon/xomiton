<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SocialNetwork;
use Database\Factories\SocialChannelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Passerelle vers un réseau social (DATABASE_DICTIONARY.md §8 `social_channels`) : une ligne par réseau.
 * Les jetons sont chiffrés et ne sortent jamais du modèle ; aucune donnée de membre n'y transite (D4-bis).
 */
class SocialChannel extends Model
{
    /** @use HasFactory<SocialChannelFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'network',
        'label',
        'icon',
        'color',
        'supports_api',
        'is_connected',
        'account_handle',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'external_id',
        'connected_at',
        'connected_by',
        'last_error',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'access_token',
        'refresh_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'network' => SocialNetwork::class,
            'supports_api' => 'boolean',
            'is_connected' => 'boolean',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'connected_at' => 'datetime',
        ];
    }

    /**
     * Administrateur·rice qui a connecté le compte.
     *
     * @return BelongsTo<User, $this>
     */
    public function connector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'connected_by');
    }

    /**
     * @return HasMany<OutboundDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(OutboundDelivery::class);
    }

    /**
     * Publications ciblant ce réseau, via les remises (inverse de OutboundPost::channels()).
     *
     * @return BelongsToMany<OutboundPost, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(OutboundPost::class, 'outbound_deliveries')
            ->withPivot(['status', 'external_id', 'external_url', 'error', 'attempts', 'published_at', 'marked_by'])
            ->withTimestamps();
    }

    /**
     * @param  Builder<SocialChannel>  $query
     */
    public function scopeConnected(Builder $query): void
    {
        $query->where('is_connected', true);
    }

    /**
     * Réseaux publiables par l'API (les autres relèvent de la publication manuelle assistée).
     *
     * @param  Builder<SocialChannel>  $query
     */
    public function scopeWithApi(Builder $query): void
    {
        $query->where('supports_api', true);
    }

    /**
     * @param  Builder<SocialChannel>  $query
     */
    public function scopeOfNetwork(Builder $query, SocialNetwork $network): void
    {
        $query->where('network', $network->value);
    }

    public function isConnected(): bool
    {
        return $this->is_connected;
    }

    /** Connecté mais sans API : les publications sont marquées à la main (`GatewayDeliveryStatus::Manual`). */
    public function isManualOnly(): bool
    {
        return ! $this->supports_api;
    }

    public function hasExpiredToken(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }

    /** Peut publier automatiquement : connecté, API disponible, jeton présent et valide. */
    public function canPublishViaApi(): bool
    {
        return $this->isConnected()
            && $this->supports_api
            && $this->access_token !== null
            && ! $this->hasExpiredToken();
    }
}
