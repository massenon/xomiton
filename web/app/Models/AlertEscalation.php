<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlertEscalationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Escalade d'une alerte vers un autre contact (DATABASE_DICTIONARY.md §5 `alert_escalations`) :
 * automatique après 5 minutes sans accusé (RG-05) ou manuelle ; journal immuable.
 */
class AlertEscalation extends Model
{
    /** @use HasFactory<AlertEscalationFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const REASON_NO_ACK = 'no_ack_5min';

    public const REASON_MANUAL = 'manual';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'from_contact_id',
        'to_contact_id',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
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
     * @return BelongsTo<DirectoryContact, $this>
     */
    public function fromContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class, 'from_contact_id');
    }

    /**
     * @return BelongsTo<DirectoryContact, $this>
     */
    public function toContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class, 'to_contact_id');
    }

    /**
     * @param  Builder<AlertEscalation>  $query
     */
    public function scopeAutomatic(Builder $query): void
    {
        $query->where('reason', self::REASON_NO_ACK);
    }

    /**
     * @param  Builder<AlertEscalation>  $query
     */
    public function scopeManual(Builder $query): void
    {
        $query->where('reason', self::REASON_MANUAL);
    }

    public function isAutomatic(): bool
    {
        return $this->reason === self::REASON_NO_ACK;
    }
}
