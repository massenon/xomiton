<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Alerts\EscalationPolicy;
use App\Domain\Alerts\TrackingCode;
use App\Domain\Alerts\ValueObjects\EscalationDelays;
use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;
use App\Models\Concerns\HasUlid;
use App\Support\EscalationPolicyFactory;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
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
     *
     * @deprecated utiliser App\Enums\RoutingFallback (valeurs identiques).
     */
    public const ROUTING_ROLE_ZONE = 'role_zone';

    /** @deprecated utiliser RoutingFallback::FocalZone. */
    public const ROUTING_FOCAL_ZONE = 'focal_zone';

    /** @deprecated utiliser RoutingFallback::FocalNational. */
    public const ROUTING_FOCAL_NATIONAL = 'focal_national';

    /** @deprecated utiliser RoutingFallback::FirstActive. */
    public const ROUTING_FIRST_ACTIVE = 'first_active';

    /**
     * Alphabet du code de suivi : sans 0/O, 1/I pour une lecture et une dictée sans ambiguïté (domaine : TrackingCode).
     */
    public const TRACKING_ALPHABET = TrackingCode::ALPHABET;

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
        'occurred_on',
        'description',
        'needs',
        'zone_id',
        'commune_id',
        'lat',
        'lng',
        'location_accuracy',
        'contact',
        'preferred_channel',
        'status',
        'priority',
        'routed_to',
        'routing_fallback',
        'acknowledged_at',
        'acknowledged_by',
        'acknowledged_by_contact_id',
        'escalated_at',
        'reminded_at',
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
            'occurred_on' => 'date',
            'description' => 'encrypted',
            'needs' => 'array',
            'lat' => 'encrypted',
            'lng' => 'encrypted',
            'location_accuracy' => 'integer',
            'contact' => 'encrypted',
            'preferred_channel' => ContactChannel::class,
            'status' => AlertStatus::class,
            'priority' => AlertPriority::class,
            'routing_fallback' => RoutingFallback::class,
            'acknowledged_at' => 'datetime',
            'escalated_at' => 'datetime',
            'reminded_at' => 'datetime',
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
     * Code de suivi unique (TrackingCode::random), de la longueur configurée (`xomiton.alerts.tracking_code_length`).
     */
    public static function generateTrackingCode(): string
    {
        $length = (int) config('xomiton.alerts.tracking_code_length', TrackingCode::LENGTH);

        do {
            $code = TrackingCode::random($length);
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
     * Fiche d'annuaire qui a accusé réception par lien signé (Q50).
     *
     * @return BelongsTo<DirectoryContact, $this>
     */
    public function acknowledgedByContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class, 'acknowledged_by_contact_id');
    }

    public function wasAcknowledgedBy(DirectoryContact $contact): bool
    {
        return $this->isAcknowledged() && (int) $this->acknowledged_by_contact_id === (int) $contact->getKey();
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
     * Preuves jointes (disque privé, lien signé).
     *
     * @return HasMany<AlertAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(AlertAttachment::class);
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
     * Statut final (`traite`, `clos`).
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeTerminal(Builder $query): void
    {
        $query->whereNotIn('status', AlertStatus::openStatuses());
    }

    /**
     * @param  Builder<Alert>  $query
     */
    public function scopeNotTerminal(Builder $query): void
    {
        $query->whereIn('status', AlertStatus::openStatuses());
    }

    /**
     * Alertes encore suivies par RG-05 : ouvertes, sans accusé, non annulées, la personne n'a pas indiqué être en sécurité.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeAwaitingFollowUp(Builder $query): void
    {
        $query->notTerminal()->unacknowledged()->whereNull('safe_at');
    }

    /**
     * Alertes à escalader (RG-05, Q27) : délai d'escalade de leur type échu (config `xomiton.alerts.escalation`,
     * SOS 5 min, signalement et besoin 48 h par défaut), sans accusé, pas encore escaladées, et pas plus vieilles que
     * ce délai + `xomiton.alerts.escalation_grace_minutes` (une alerte historique importée n'est jamais escaladée).
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeAwaitingEscalation(Builder $query, ?CarbonInterface $at = null, ?EscalationPolicy $policy = null): void
    {
        $query->awaitingFollowUp()
            ->whereNull('escalated_at')
            ->where(fn (Builder $types) => self::whereDue($types, $at, $policy, static fn (EscalationDelays $d): ?int => $d->escalateAfterMinutes));
    }

    /**
     * Alertes à rappeler au contact routé (Q27 : signalement et besoin à 24 h par défaut) : délai de rappel échu, ni
     * rappelées ni escaladées, sans accusé, et dans la même borne d'âge que l'escalade.
     *
     * @param  Builder<Alert>  $query
     */
    public function scopeAwaitingReminder(Builder $query, ?CarbonInterface $at = null, ?EscalationPolicy $policy = null): void
    {
        $query->awaitingFollowUp()
            ->whereNull('escalated_at')
            ->whereNull('reminded_at')
            ->where(fn (Builder $types) => self::whereDue($types, $at, $policy, static fn (EscalationDelays $d): ?int => $d->remindAfterMinutes));
    }

    /**
     * Par type : délai échu (`created_at <= at - délai`) et alerte encore suivie (`created_at >= at - (délai
     * d'escalade, sinon de rappel) - marge`). Sans délai configuré pour un type : aucune alerte de ce type.
     *
     * @param  Builder<Alert>  $types
     * @param  Closure(EscalationDelays): ?int  $delay
     */
    private static function whereDue(Builder $types, ?CarbonInterface $at, ?EscalationPolicy $policy, Closure $delay): void
    {
        $at = CarbonImmutable::instance($at ?? now());
        $policy ??= EscalationPolicyFactory::fromConfig();
        $grace = max(0, (int) config('xomiton.alerts.escalation_grace_minutes', 1440));

        $types->whereRaw('1 = 0');

        foreach ($policy->types() as $type) {
            $delays = $policy->delaysFor($type);
            $minutes = $delay($delays);
            $horizon = $delays->escalateAfterMinutes ?? $delays->remindAfterMinutes;

            if ($minutes !== null && $horizon !== null) {
                $types->orWhere(fn (Builder $q) => $q->where('type', $type)
                    ->where('created_at', '<=', $at->subMinutes($minutes))
                    ->where('created_at', '>=', $at->subMinutes($horizon + $grace)));
            }
        }
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
        $normalized = TrackingCode::normalize($code);

        $normalized === null ? $query->whereRaw('1 = 0') : $query->where('tracking_code', $normalized);
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

    /** Statut final (`traite`, `clos`) : plus aucune relance RG-05. */
    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
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

    /** Code de suivi affiché (« SIG-A7K2M9P4QX »). */
    public function displayCode(): string
    {
        return TrackingCode::display((string) $this->tracking_code);
    }

    /** Libellé du lieu transmis aux personnes prévenues (commune, sinon département) ; jamais la position (P5-03). */
    public function placeLabel(): ?string
    {
        return $this->commune?->name ?? $this->zone?->name;
    }

    public function zoneLabel(): ?string
    {
        return $this->zone?->name;
    }

    /** Fin du repli « fausse alerte » d'un SOS (création + `xomiton.alerts.cancel_window_seconds`, Q39). */
    public function cancelWindowEndsAt(): CarbonImmutable
    {
        $createdAt = CarbonImmutable::instance($this->created_at ?? now());

        return $createdAt->addSeconds((int) config('xomiton.alerts.cancel_window_seconds', 60));
    }

    public function canBeCancelledAt(CarbonInterface $now): bool
    {
        return $this->isSos() && ! $this->isCancelled() && $now->lessThanOrEqualTo($this->cancelWindowEndsAt());
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
