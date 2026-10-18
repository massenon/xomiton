<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;
use Illuminate\Notifications\AnonymousNotifiable;

/**
 * « SOS déclenché » (NOTIFICATIONS §2, RG-01) : contacts de confiance, point focal de zone, admin de garde ; cloche,
 * push, SMS de secours pour un destinataire sans abonnement push (texte court avec le code et le lieu, jamais les
 * coordonnées ; texte dédié sans lien d'administration pour le contact de confiance). Envoyée dans la requête, jamais en file (D7). Catégorie SOS : non désactivable. Sensible.
 * `place` est un libellé (commune, département), jamais une position brute ; `pseudo` est nul si l'alerte est anonyme.
 */
final class SosTriggered extends XomitonNotification
{
    public function __construct(
        public readonly int $alertId,
        public readonly string $code,
        public readonly ?string $place,
        public readonly ?string $zoneLabel,
        public readonly ?string $pseudo = null,
        public readonly ?string $url = null,
        // Faux pour une fiche sans compte qui ne peut pas accuser réception (prévenue en copie, P5-03 / Q57) : SMS
        // `sms_copy`, sans « Accusez réception » ni lien.
        public readonly bool $acknowledgeable = true,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SOS;
    }

    public function isSensitive(): bool
    {
        return true;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        if ($this->place === null) {
            $body = __('notifications.sos_triggered.body_no_position', ['zone' => $this->zoneLabel ?? '—']);
        } elseif ($this->pseudo !== null && $this->pseudo !== '') {
            $body = __('notifications.sos_triggered.body_with_pseudo', ['pseudo' => $this->pseudo, 'place' => $this->place]);
        } else {
            $body = __('notifications.sos_triggered.body', ['place' => $this->place]);
        }

        return [
            'title' => (string) __('notifications.sos_triggered.title'),
            'body' => (string) $body,
            'url' => $this->targetUrl(),
            'icon' => 'broadcast',
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.sos_triggered.discreet');
    }

    /**
     * SMS de secours quand le destinataire n'a aucun abonnement push. Provisoire : NOTIFICATIONS §1 et P5-03 visent
     * « si le push échoue » ; un push en échec (410, erreur FCM) ne déclenche pas encore de SMS. Le repli sur un
     * DeliveryResult échoué revient au dispatcher SOS de P5-03.
     */
    protected function wantsSms(object $notifiable): bool
    {
        return ! $this->hasAnyPushSubscription($notifiable);
    }

    protected function smsText(object $notifiable): ?string
    {
        // Contact de confiance (Notification::route('sms'), sans compte) : ni lien d'administration ni accusé de
        // réception, qui demande de l'aide (sauf alerte anonyme) et une consigne d'appel.
        if ($notifiable instanceof AnonymousNotifiable) {
            $named = $this->pseudo !== null && $this->pseudo !== '';

            return (string) __($named ? 'notifications.sos_triggered.sms_trusted' : 'notifications.sos_triggered.sms_trusted_unnamed', [
                'pseudo' => (string) $this->pseudo,
                'where' => $this->place ?? $this->zoneLabel ?? __('notifications.sos_triggered.where_unknown'),
                'code' => $this->code,
            ]);
        }

        if (! $this->acknowledgeable) {
            return (string) __('notifications.sos_triggered.sms_copy', [
                'code' => $this->code,
                'where' => $this->place ?? $this->zoneLabel ?? __('notifications.sos_triggered.where_unknown'),
            ]);
        }

        $key = $this->place === null ? 'notifications.sos_triggered.sms_no_position' : 'notifications.sos_triggered.sms';

        return (string) __($key, [
            'code' => $this->code,
            'place' => $this->place ?? '—',
            'zone' => $this->zoneLabel ?? '—',
            'url' => $this->targetUrl(),
        ]);
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['alert_id' => $this->alertId, 'code' => $this->code];
    }

    protected function subjectId(): int
    {
        return $this->alertId;
    }

    protected function pushType(): string
    {
        return 'sos';
    }

    private function targetUrl(): string
    {
        return $this->url ?? self::urlTo('admin.alertes', [], '/admin/alertes');
    }
}
