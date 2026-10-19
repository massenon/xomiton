<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaintenanceWindowFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Fenêtre de maintenance (DATABASE_DICTIONARY.md §10 `maintenance_windows`) : pendant une fenêtre active et en cours,
 * seule l'administration peut se connecter ; le message est affiché aux autres visiteur·ses.
 * P13-06 : motif interne (`reason`, jamais public), annonce préalable `announce_hours` heures avant le début (bandeau
 * P13-08), annulation d'une fenêtre à venir (`cancelled_at`, `is_active` faux : la ligne reste dans l'historique).
 */
class MaintenanceWindow extends Model
{
    /** @use HasFactory<MaintenanceWindowFactory> */
    use HasFactory;

    public const DEFAULT_MESSAGE = 'Xomiton est en maintenance. Nous revenons très vite.';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'starts_at',
        'ends_at',
        'message',
        'reason',
        'announce_hours',
        'is_active',
        'cancelled_at',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
            'announce_hours' => 'integer',
            'cancelled_at' => 'datetime',
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
     * @param  Builder<MaintenanceWindow>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Fenêtres actives en cours à l'instant donné (par défaut maintenant).
     *
     * @param  Builder<MaintenanceWindow>  $query
     */
    public function scopeOngoing(Builder $query, ?Carbon $at = null): void
    {
        $at ??= now();

        $query->active()->where('starts_at', '<=', $at)->where('ends_at', '>', $at);
    }

    /**
     * Fenêtres actives à venir, la plus proche en premier.
     *
     * @param  Builder<MaintenanceWindow>  $query
     */
    public function scopeUpcoming(Builder $query, ?Carbon $at = null): void
    {
        $query->active()->where('starts_at', '>', $at ?? now())->orderBy('starts_at')->orderBy('id');
    }

    /**
     * Fenêtres actives qui chevauchent l'intervalle [début, fin[ (en cours ou à venir ; les fenêtres closes ou
     * annulées ne comptent pas).
     *
     * @param  Builder<MaintenanceWindow>  $query
     */
    public function scopeOverlapping(Builder $query, Carbon $startsAt, Carbon $endsAt): void
    {
        $query->active()->where('starts_at', '<', $endsAt)->where('ends_at', '>', $startsAt);
    }

    /**
     * Y a-t-il une maintenance en cours ?
     */
    public static function isUnderMaintenance(?Carbon $at = null): bool
    {
        return static::query()->ongoing($at)->exists();
    }

    public function isOngoing(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active && $this->starts_at->lte($at) && $this->ends_at->gt($at);
    }

    public function isPast(?Carbon $at = null): bool
    {
        return $this->ends_at->lte($at ?? now());
    }

    /** Début de l'annonce préalable (bandeau P13-08) : `announce_hours` heures avant le début. */
    public function announcedFrom(): Carbon
    {
        return $this->starts_at->copy()->subHours(max(0, (int) $this->announce_hours));
    }

    /** La fenêtre, à venir, est-elle dans son délai d'annonce ? */
    public function isAnnounced(?Carbon $at = null): bool
    {
        $at ??= now();

        return $this->is_active && $this->starts_at->gt($at) && (int) $this->announce_hours > 0 && $this->announcedFrom()->lte($at);
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    public function displayMessage(): string
    {
        return $this->message !== null && $this->message !== '' ? $this->message : self::DEFAULT_MESSAGE;
    }
}
