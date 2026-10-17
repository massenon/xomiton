<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use Database\Factories\AlertNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification émise pour une alerte (DATABASE_DICTIONARY.md §5 `alert_notifications`) : une ligne par
 * canal et destinataire ; envoyée dans la requête puis rejouée par le cron en cas d'échec (`alerts:retry`).
 */
class AlertNotification extends Model
{
    /** @use HasFactory<AlertNotificationFactory> */
    use HasFactory;

    /**
     * Préfixe du destinataire quand la cible est un compte (canal `database` / `push`).
     */
    public const USER_RECIPIENT_PREFIX = 'user:';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'channel',
        'recipient',
        'directory_contact_id',
        'status',
        'attempts',
        'last_error',
        'sent_at',
        'next_retry_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'next_retry_at' => 'datetime',
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
    public function directoryContact(): BelongsTo
    {
        return $this->belongsTo(DirectoryContact::class);
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Pending);
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('status', DeliveryStatus::Failed);
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeOnChannel(Builder $query, NotificationChannel $channel): void
    {
        $query->where('channel', $channel);
    }

    /**
     * Notifications à rejouer par le cron : non envoyées, échéance de rejeu atteinte,
     * sous le plafond `xomiton.alerts.max_retry_attempts`.
     *
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeDueForRetry(Builder $query): void
    {
        $query->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Failed])
            ->where('attempts', '<', (int) config('xomiton.alerts.max_retry_attempts', 10))
            ->where(function (Builder $q): void {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', now());
            });
    }

    public function isSent(): bool
    {
        return $this->status === DeliveryStatus::Sent;
    }

    public function canRetry(): bool
    {
        return ! $this->isSent()
            && $this->attempts < (int) config('xomiton.alerts.max_retry_attempts', 10);
    }

    /** Identifiant du compte ciblé quand le destinataire est de la forme `user:<id>`. */
    public function recipientUserId(): ?int
    {
        if (! str_starts_with($this->recipient, self::USER_RECIPIENT_PREFIX)) {
            return null;
        }

        $id = substr($this->recipient, strlen(self::USER_RECIPIENT_PREFIX));

        return ctype_digit($id) ? (int) $id : null;
    }
}
