<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Concerns\HasUlid;
use Database\Factories\AlertFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Alerte : dénonciation, besoin ou SOS (DATABASE_DICTIONARY.md §5 `alerts`). Jamais supprimée
 * (pas de soft delete, `restrict` depuis les tables filles). Récit, position et contact chiffrés.
 */
class Alert extends Model
{
    /** @use HasFactory<AlertFactory> */
    use HasFactory, HasUlid;

    /**
     * Niveaux de repli du routage RG-04 (`routing_fallback`).
     */
    public const ROUTING_ROLE_ZONE = 'role_zone';

    public const ROUTING_FOCAL_ZONE = 'focal_zone';

    public const ROUTING_FOCAL_NATIONAL = 'focal_national';

    public const ROUTING_FIRST_ACTIVE = 'first_active';

    /**
     * Alphabet du code de suivi : sans 0/O, 1/I pour une lecture et une dictée sans ambiguïté.
     */
    public const TRACKING_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'type',
        'user_id',
        'is_anonymous',
        'pseudo',
        'category',
        'description',
        'needs',
        'zone_id',
        'commune_id',
        'lat',
        'lng',
        'location_accuracy',
        'contact',
        'status',
        'priority',
        'routed_to',
        'routing_fallback',
        'acknowledged_at',
        'acknowledged_by',
        'escalated_at',
        'closed_at',
        'cancelled_at',
        'safe_at',
        'tracking_code',
        'source',
        'idempotency_key',
        'device_id',
    ];

    /**
     * Position, contact et clé technique ne sortent jamais par défaut (réservés au contact routé et à l'administration).
     *
     * @var list<string>
     */
    protected $hidden = [
        'lat',
        'lng',
        'contact',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => AlertType::class,
            'is_anonymous' => 'boolean',
            'description' => 'encrypted',
            'needs' => 'array',
            'lat' => 'encrypted',
            'lng' => 'encrypted',
            'location_accuracy' => 'integer',
            'contact' => 'encrypted',
            'status' => AlertStatus::class,
            'priority' => AlertPriority::class,
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'safe_at' => 'datetime',
            'source' => AlertSource::class,
        ];
    }

    protected static function booted(): void
    {
        // Le code de suivi anonyme est attribué à la création et ne change jamais.
        static::creating(function (Alert $alert): void {
            if (empty($alert->tracking_code)) {
                $alert->tracking_code = static::generateTrackingCode();
            }
        });
    }

    /**
     * Code de suivi unique, de la longueur configurée (`xomiton.alerts.tracking_code_length`).
     */
    public static function generateTrackingCode(): string
    {
        $length = (int) config('xomiton.alerts.tracking_code_length', 10);
        $max = strlen(self::TRACKING_ALPHABET) - 1;

        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= self::TRACKING_ALPHABET[random_int(0, $max)];
            }
        } while (static::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<Commune, $this>
     */
    public function commune(): BelongsTo
    {
        return $this->belongsTo(Commune::class);
    }

    /**
     * Contact de l'annuaire vers lequel l'alerte a été routée.
     *
     * @return BelongsTo<DirectoryContact, $this>
     */
    public function routedContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class, 'routed_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /**
     * @return BelongsTo<SyncDevice, $this>
     */
    public function device(): BelongsTo
    {
        return $this->belongsTo(SyncDevice::class, 'device_id');
    }

    /**
     * @return HasMany<AlertNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AlertNote::class);
    }

    /**
     * @return HasMany<AlertNotification, $this>
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(AlertNotification::class);
    }

    /**
     * @return HasMany<AlertEscalation, $this>
     */
    public function escalations(): HasMany
    {
        return $this->hasMany(AlertEscalation::class);
    }

    /**
     * @return HasMany<AlertStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(AlertStatusChange::class);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeOfType(Builder $query, AlertType $type): void
    {
        $query->where('type', $type);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeSos(Builder $query): void
    {
        $query->where('type', AlertType::Sos);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeWithStatus(Builder $query, AlertStatus $status): void
    {
        $query->where('status', $status);
    }

    /**
     * Alertes encore à traiter (tableau de garde).
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', AlertStatus::openStatuses());
    }

    /**
     * Alertes sans accusé de réception, non annulées.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeUnacknowledged(Builder $query): void
    {
        $query->whereNull('acknowledged_at')->whereNull('cancelled_at');
    }

    /**
     * SOS à escalader (RG-05) : sans accusé depuis plus de `xomiton.alerts.escalation_minutes`, pas encore escaladés.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeAwaitingEscalation(Builder $query): void
    {
        $query->sos()
            ->open()
            ->unacknowledged()
            ->whereNull('escalated_at')
            ->where('created_at', '<=', now()->subMinutes((int) config('xomiton.alerts.escalation_minutes', 5)));
    }

    /**
     * Alertes routées vers un contact de l'annuaire.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeRoutedTo(Builder $query, DirectoryContact|int $contact): void
    {
        $query->where('routed_to', $contact instanceof DirectoryContact ? $contact->getKey() : $contact);
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeWhereTrackingCode(Builder $query, string $code): void
    {
        $query->where('tracking_code', strtoupper(trim($code)));
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeInZone(Builder $query, Zone|int $zone): void
    {
        $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }

    /**
     * Ordre du tableau de garde : les plus récentes d'abord.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isSos(): bool
    {
        return $this->type === AlertType::Sos;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function isAcknowledged(): bool
    {
        return $this->acknowledged_at !== null;
    }

    public function isEscalated(): bool
    {
        return $this->escalated_at !== null;
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** La personne a indiqué « Je suis en sécurité ». */
    public function isSafe(): bool
    {
        return $this->safe_at !== null;
    }

    public function hasLocation(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Position déchiffrée en nombres (null si absente).
     *
     * @return array{lat: float, lng: float}|null
     */
    public function coordinates(): ?array
    {
        if (! $this->hasLocation()) {
            return null;
        }

        return ['lat' => (float) $this->lat, 'lng' => (float) $this->lng];
    }

    /**
     * Pseudo affiché : jamais celui du compte si l'alerte est anonyme (RG-01, RG-02).
     */
    public function authorLabel(): string
    {
        if ($this->is_anonymous) {
            return (string) __('alerts.anonymous');
        }

        if ($this->pseudo !== null && $this->pseudo !== '') {
            return $this->pseudo;
        }

        return $this->user?->pseudo ?? (string) __('alerts.anonymous');
    }

    /**
     * Slugs de besoins exprimés (toujours une liste).
     *
     * @return list<string>
     */
    public function needSlugs(): array
    {
        return array_values($this->needs ?? []);
    }
}
