<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Application\Mail\EmailTemplateService;
use App\Models\MailLog;
use App\Notifications\TemplateMailData;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;

/**
 * Canal `mail` de Xomiton (P3-02) : quand `toMail()` renvoie une TemplateMailData, l'e-mail part par
 * `EmailTemplateService::send()` (template en base, mise en page Xomiton, journal `mail_logs`, repli boîte d'envoi) ;
 * sinon la notification est confiée au canal mail standard de Laravel (MailMessage / Mailable), pour ne rien casser.
 * Enregistré par `Notification::extend('mail', …)` dans AppServiceProvider.
 */
final class TemplateMailChannel
{
    public function __construct(private readonly MailChannel $standard) {}

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): ?MailLog
    {
        if (! method_exists($notification, 'toMail')) {
            return null;
        }

        $message = $notification->toMail($notifiable);

        if (! $message instanceof TemplateMailData) {
            $this->standard->send($notifiable, $notification);

            return null;
        }

        $to = $this->recipient($notifiable, $notification);

        if ($to === null) {
            return null;
        }

        return EmailTemplateService::send($message->slug, $to, $message->variables, $message->context);
    }

    /**
     * Adresse du destinataire : `routeNotificationFor('mail')` (chaîne, ou tableau adresse => nom).
     *
     * @param  mixed  $notifiable
     */
    private function recipient($notifiable, Notification $notification): ?string
    {
        if (! is_object($notifiable) || ! method_exists($notifiable, 'routeNotificationFor')) {
            return null;
        }

        $route = $notifiable->routeNotificationFor('mail', $notification);

        if (is_array($route)) {
            $route = array_key_first($route);
        }

        return is_string($route) && trim($route) !== '' ? trim($route) : null;
    }
}
