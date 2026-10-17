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
        'is_active',
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

    public function displayMessage(): string
    {
        return $this->message !== null && $this->message !== '' ? $this->message : self::DEFAULT_MESSAGE;
    }
}
