<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\OnCallShiftFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Plage de garde (P1-17 `on_call_shifts`) : un compte est admin de garde (`admin`) ou point focal remplaçant
 * (`focal`, national sans zone, sinon pour le département) entre `starts_at` inclus et `ends_at` exclu.
 */
class OnCallShift extends Model
{
    /** @use HasFactory<OnCallShiftFactory> */
    use HasFactory;

    /** Admin de garde : destinataire des SOS et des alertes routées (NOTIFICATIONS_INSTRUCTION.md §2). */
    public const KIND_ADMIN = 'admin';

    /** Point focal remplaçant pendant la plage (le compte doit être lié à une fiche d'annuaire). */
    public const KIND_FOCAL = 'focal';

    /** @var list<string> */
    public const KINDS = [self::KIND_ADMIN, self::KIND_FOCAL];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'starts_at',
        'ends_at',
        'kind',
        'zone_id',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Département couvert (null : garde nationale).
     *
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * Plages en cours à l'instant donné (début inclus, fin exclue).
     *
     * @param  Builder<OnCallShift>  $query
     */
    public function scopeActive(Builder $query, ?CarbonInterface $at = null): void
    {
        $at ??= now();

        $query->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    /**
     * @param  Builder<OnCallShift>  $query
     */
    public function scopeForKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Plages d'un département (null : plages nationales, sans zone).
     *
     * @param  Builder<OnCallShift>  $query
     */
    public function scopeForZone(Builder $query, Zone|int|null $zone): void
    {
        $zone === null
            ? $query->whereNull('zone_id')
            : $query->where('zone_id', $zone instanceof Zone ? $zone->getKey() : $zone);
    }

    /**
     * Plages à venir, de la plus proche à la plus lointaine.
     *
     * @param  Builder<OnCallShift>  $query
     */
    public function scopeUpcoming(Builder $query, ?CarbonInterface $from = null): void
    {
        $query->where('starts_at', '>', $from ?? now())->orderBy('starts_at');
    }

    /**
     * @param  Builder<OnCallShift>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('starts_at')->orderBy('id');
    }

    public function isActive(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->starts_at->lessThanOrEqualTo($at) && $this->ends_at->greaterThan($at);
    }

    public function isAdmin(): bool
    {
        return $this->kind === self::KIND_ADMIN;
    }

    public function isFocal(): bool
    {
        return $this->kind === self::KIND_FOCAL;
    }

    public function isNational(): bool
    {
        return $this->zone_id === null;
    }
}
