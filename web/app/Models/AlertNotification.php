<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Domain\Alerts\Dispatch\RowSnapshot;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\DirectoryRole;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use Carbon\CarbonInterface;
use Database\Factories\AlertNotificationFactory;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Lang;

/**
 * Notification émise pour une alerte (DATABASE_DICTIONARY.md §5 `alert_notifications`) : une ligne par objet
 * (`purpose`), canal et destinataire, planifiée avant tout envoi par App\Application\Alerts\Dispatch\AlertDispatcher,
 * envoyée dans la requête puis rejouée par le cron en cas d'échec (`notifications:retry`, P5-03).
 * `recipient` est une référence, jamais un numéro ni une adresse (ADR-33) : `user:<id>` (compte), `subscription:<id>`
 * (abonnement push d'un compte), `contact:<id>` (fiche d'annuaire sans compte), `trusted:<id>` (contact de confiance
 * de l'auteur·e, numéro chiffré dans `profiles`, relu à chaque tentative).
 */
class AlertNotification extends Model
{
    /** @use HasFactory<AlertNotificationFactory> */
    use HasFactory;

    /**
     * Préfixe du destinataire quand la cible est un compte (canal `database` / `push`).
     */
    public const USER_RECIPIENT_PREFIX = 'user:';

    /** Fiche d'annuaire sans compte lié (SMS, e-mail directs). */
    public const CONTACT_RECIPIENT_PREFIX = 'contact:';

    /** Contact de confiance de l'auteur·e d'un SOS (`profiles.trusted_contact_phone`). */
    public const TRUSTED_RECIPIENT_PREFIX = 'trusted:';

    /** Abonnement push d'un compte (une ligne par abonnement Web Push ou FCM). */
    public const SUBSCRIPTION_RECIPIENT_PREFIX = 'subscription:';

    /**
     * Raisons d'abandon (`last_error` d'une ligne `skipped`) et échecs connus : des codes, traduits à l'affichage
     * (`lang/fr/alerts.php` `delivery_reason`, lastErrorLabel()) ; tout autre `last_error` est le message masqué du
     * fournisseur (DeliveryError), affiché tel quel.
     */
    public const SKIP_GONE = 'gone';

    public const SKIP_NO_PHONE = 'no_phone';

    public const SKIP_NO_ADDRESS = 'no_address';

    public const SKIP_UNAVAILABLE = 'unavailable';

    public const SKIP_HANDLED = 'handled';

    public const SKIP_CANCELLED = 'cancelled';

    public const SKIP_EXHAUSTED = 'exhausted';

    /** Pool HTTP sans réponse pour cette remise (DeliveryRunner). */
    public const ERROR_NO_RESPONSE = 'no_response';

    /** E-mail refusé sans message d'erreur (EmailTemplateService). */
    public const ERROR_MAIL_NOT_SENT = 'mail_not_sent';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'alert_id',
        'channel',
        'purpose',
        'recipient',
        'directory_contact_id',
        'user_id',
        'push_subscription_id',
        'status',
        'provider',
        'attempts',
        'last_error',
        'sent_at',
        'seen_at',
        'next_retry_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'purpose' => NotificationPurpose::class,
            'status' => DeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'seen_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PushSubscription, $this>
     */
    public function pushSubscription(): BelongsTo
    {
        return $this->belongsTo(PushSubscription::class);
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
    public function scopeDueForRetry(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->whereIn('status', [DeliveryStatus::Pending, DeliveryStatus::Failed])
            ->where('attempts', '<', (int) config('xomiton.alerts.max_retry_attempts', 10))
            ->where(function (Builder $q) use ($at): void {
                $q->whereNull('next_retry_at')->orWhere('next_retry_at', '<=', $at ?? now());
            });
    }

    /**
     * Lignes rejouées par `notifications:retry` : échues et d'un objet rejouable (les retours « fausse alerte »,
     * « en sécurité », accusé et statut ne partent qu'une fois).
     *
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeRetryable(Builder $query, ?CarbonInterface $at = null): void
    {
        $query->dueForRetry($at)->whereIn('purpose', NotificationPurpose::retryable());
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeForAlert(Builder $query, Alert|int $alert): void
    {
        $query->where('alert_id', $alert instanceof Alert ? $alert->getKey() : $alert);
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeOfPurpose(Builder $query, NotificationPurpose ...$purposes): void
    {
        $query->whereIn('purpose', $purposes);
    }

    /**
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeSeen(Builder $query): void
    {
        $query->whereNotNull('seen_at');
    }

    /**
     * SOS d'abord (un SMS de SOS n'attend jamais derrière une file de rappels de signalements), puis les plus anciennes.
     *
     * @param  Builder<AlertNotification>  $query
     */
    public function scopeSosFirst(Builder $query): void
    {
        $query->orderByRaw(
            'CASE WHEN EXISTS (SELECT 1 FROM alerts WHERE alerts.id = alert_notifications.alert_id AND alerts.type = ?) THEN 0 ELSE 1 END',
            [AlertType::Sos->value],
        )->orderBy('alert_notifications.created_at')->orderBy('alert_notifications.id');
    }

    public function isSent(): bool
    {
        return $this->status === DeliveryStatus::Sent;
    }

    public function isSkipped(): bool
    {
        return $this->status === DeliveryStatus::Skipped;
    }

    public function isSeen(): bool
    {
        return $this->seen_at !== null;
    }

    /** Remise acceptée par le pilote (cloche écrite, e-mail déposé). */
    public function markSent(string $provider, ?CarbonInterface $at = null): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Sent,
            'provider' => mb_substr($provider, 0, 20),
            'sent_at' => $at ?? now(),
            'attempts' => min(255, $this->attempts + 1),
            'last_error' => null,
            'next_retry_at' => null,
        ])->save();
    }

    /** Échec d'une tentative réelle ; `$nextRetryAt` null : jamais rejouée (objet non rejouable ou plafond). */
    public function markFailed(string $error, ?string $provider = null, ?CarbonInterface $nextRetryAt = null): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Failed,
            'provider' => $provider === null ? $this->provider : mb_substr($provider, 0, 20),
            'attempts' => min(255, $this->attempts + 1),
            'last_error' => mb_substr($error, 0, 255),
            'next_retry_at' => $nextRetryAt,
        ])->save();
    }

    /** Abandon sans envoi (raison lisible, sans donnée personnelle). */
    public function markSkipped(string $reason, ?string $provider = null): void
    {
        $this->forceFill([
            'status' => DeliveryStatus::Skipped,
            'provider' => $provider === null ? $this->provider : mb_substr($provider, 0, 20),
            'last_error' => mb_substr($reason, 0, 255),
            'next_retry_at' => null,
        ])->save();
    }

    /** Remise non tentée (budget ou délai du fournisseur épuisé) : reprise à la minute suivante, sans tentative comptée. */
    public function markDeferred(?CarbonInterface $at = null): void
    {
        $this->forceFill(['next_retry_at' => $at ?? now()])->save();
    }

    /** Accusé de réception de la personne (idempotent : la première date est conservée). */
    public function markSeen(?CarbonInterface $at = null): void
    {
        if ($this->seen_at !== null) {
            return;
        }

        $this->forceFill(['seen_at' => $at ?? now()])->save();
    }

    /** Personne visée : un abonnement push appartient à son compte (`subscription:` → `user:`). */
    public function personKey(): string
    {
        if (str_starts_with($this->recipient, self::SUBSCRIPTION_RECIPIENT_PREFIX) && $this->user_id !== null) {
            return self::USER_RECIPIENT_PREFIX.$this->user_id;
        }

        return $this->recipient;
    }

    public function recipientKind(): RecipientKind
    {
        if (str_starts_with($this->recipient, self::TRUSTED_RECIPIENT_PREFIX)) {
            return RecipientKind::Trusted;
        }

        $contact = $this->directoryContact;

        if ($contact !== null) {
            return $contact->role === DirectoryRole::PointFocal ? RecipientKind::Focal : RecipientKind::Contact;
        }

        return RecipientKind::Team;
    }

    /** Identifiant numérique d'une référence (`contact:4` → 4), null si le préfixe diffère. */
    public function referenceId(string $prefix): ?int
    {
        if (! str_starts_with($this->recipient, $prefix)) {
            return null;
        }

        $id = substr($this->recipient, strlen($prefix));

        return ctype_digit($id) ? (int) $id : null;
    }

    public function snapshot(): RowSnapshot
    {
        return new RowSnapshot(
            $this->channel->value,
            $this->status->value,
            $this->seen_at === null ? null : DateTimeImmutable::createFromInterface($this->seen_at),
        );
    }

    /** Libellé d'une raison d'abandon ou d'échec : traduction du code, sinon le message (déjà masqué) tel quel. */
    public static function reasonLabel(?string $reason): ?string
    {
        if ($reason === null || $reason === '') {
            return null;
        }

        $key = 'alerts.delivery_reason.'.$reason;

        return Lang::has($key) ? (string) __($key) : $reason;
    }

    /** `last_error` lisible en administration (P10). */
    public function lastErrorLabel(): ?string
    {
        return self::reasonLabel($this->last_error);
    }

    public function canRetry(): bool
    {
        return ! $this->status->isFinal()
            && $this->attempts < (int) config('xomiton.alerts.max_retry_attempts', 10);
    }

    /** Identifiant du compte ciblé quand le destinataire est de la forme `user:<id>` (ou un abonnement de ce compte). */
    public function recipientUserId(): ?int
    {
        return $this->referenceId(self::USER_RECIPIENT_PREFIX)
            ?? ($this->referenceId(self::SUBSCRIPTION_RECIPIENT_PREFIX) !== null ? $this->user_id : null);
    }
}
