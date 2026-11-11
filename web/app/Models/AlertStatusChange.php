<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AlertStatus;
use Database\Factories\AlertStatusChangeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Changement de statut d'une alerte (DATABASE_DICTIONARY.md §5 `alert_status_changes`) :
 * historique affiché par le suivi par code ; `from_status` null à la création.
 */
class AlertStatusChange extends Model
{
    /** @use HasFactory<AlertStatusChangeFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'user_id',
        'from_status',
        'to_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => AlertStatus::class,
            'to_status' => AlertStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Alert, $this>
     */
    public function alert(): BelongsTo
    {
        return $this->belongsTo(Alert::class);
    }

    /**
     * Personne qui a changé le statut (null : système ou compte supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<AlertStatusChange>  $query
     */
    public function scopeChronological(Builder $query): void
    {
        $query->orderBy('created_at')->orderBy('id');
    }

    /** Première entrée de l'historique (création de l'alerte). */
    public function isInitial(): bool
    {
        return $this->from_status === null;
    }
}
