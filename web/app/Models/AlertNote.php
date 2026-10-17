<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlertNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Note interne sur une alerte (DATABASE_DICTIONARY.md §5 `alert_notes`) : corps chiffré, jamais modifiée.
 */
class AlertNote extends Model
{
    /** @use HasFactory<AlertNoteFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'user_id',
        'body',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
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
     * Auteur·rice de la note (null si le compte a été supprimé).
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<AlertNote>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }
}
