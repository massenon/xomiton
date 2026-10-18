<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Notifications\XomitonNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Canal `sms` (SMS de secours du SOS, NOTIFICATIONS §1) : numéro par `routeNotificationFor('sms')` (téléphone du compte,
 * ou `Notification::route('sms', …)` pour un contact de confiance), texte court de `toSms()`, remise par le SmsSender du
 * conteneur (`log` tant que le fournisseur n'est pas choisi, Q7). Un échec est journalisé, jamais remonté.
 */
final class SmsChannel
{
    public function __construct(private readonly SmsSender $sender) {}

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): ?DeliveryResult
    {
        if (! $notification instanceof XomitonNotification || ! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $to = $notifiable->routeNotificationFor('sms', $notification);
        $text = $notification->toSms($notifiable);

        if (! is_string($to) || trim($to) === '' || $text === null) {
            return null;
        }

        try {
            $result = $this->sender->send(trim($to), $text);
        } catch (Throwable $e) {
            $result = DeliveryResult::failed($e->getMessage());
        }

        if (! $result->ok) {
            Log::warning('[sms] Échec d’envoi du SMS de secours.', ['notification' => $notification::class, 'error' => $result->error]);
        }

        return $result;
    }
}
