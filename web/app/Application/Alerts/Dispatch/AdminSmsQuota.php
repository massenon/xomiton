<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;

/**
 * Plafond des SMS d'admins de garde par SOS (Q57, D5, D6 : `xomiton.alerts.sos_admin_sms_max`, 2 par défaut), compté
 * en personnes sur tous les objets SOS de l'alerte (`sos_triggered` et `sos_unacknowledged`) : un admin déjà joint par
 * SMS pour ce SOS le reste sans consommer de place, un troisième n'en reçoit pas. Lignes abandonnées non comptées.
 * Partagé par AlertDispatcher (requête) et RetryAlertNotifications::fallback() (cron).
 */
final class AdminSmsQuota
{
    /** Objets SOS dont les SMS d'admins partagent le plafond. */
    public const PURPOSES = [NotificationPurpose::SosTriggered, NotificationPurpose::SosUnacknowledged];

    /**
     * @param  array<string, true>  $used  références `user:<id>` déjà jointes par SMS pour ce SOS
     */
    private function __construct(
        private readonly int $max,
        private array $used,
    ) {}

    public static function forAlert(Alert|int $alert, ?int $max = null): self
    {
        $max ??= (int) config('xomiton.alerts.sos_admin_sms_max', 2);

        $used = AlertNotification::query()
            ->forAlert($alert)
            ->ofPurpose(...self::PURPOSES)
            ->onChannel(NotificationChannel::Sms)
            ->where('recipient', 'like', AlertNotification::USER_RECIPIENT_PREFIX.'%')
            ->whereNull('directory_contact_id')
            ->where('status', '!=', DeliveryStatus::Skipped)
            ->distinct()
            ->pluck('recipient')
            ->mapWithKeys(static fn (string $recipient): array => [$recipient => true])
            ->all();

        return new self(max(0, $max), $used);
    }

    /** Aucun SMS d'admin (objets sans repli SMS). */
    public static function none(): self
    {
        return new self(0, []);
    }

    public function allows(string $reference): bool
    {
        return isset($this->used[$reference]) || count($this->used) < $this->max;
    }

    public function take(string $reference): void
    {
        $this->used[$reference] = true;
    }
}
