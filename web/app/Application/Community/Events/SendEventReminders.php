<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Notifications\EventReminder;
use App\Support\LocaleFormatter;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rappel de la veille (P8-04, tâche `events:remind`, NOTIFICATIONS §2 « Rappel d'événement (veille) ») : pour chaque
 * événement publié qui commence dans les `xomiton.events.reminder_hours` (24 h) et dont le rappel n'est pas parti,
 * `reminder_sent_at` est posé **avant** l'envoi par une mise à jour conditionnelle (`reminder_sent_at IS NULL`) : deux
 * passages simultanés n'envoient jamais deux rappels (Event::scopeReminderDue). Destinataires : participant·es
 * confirmé·es qui ont gardé le rappel (EventRsvp::wantsReminder), comptes actifs, hors blocage avec
 * l'organisateur·rice (EventAccess::withoutBlocksWith). Le rappel part une fois par événement : une réponse arrivée
 * après son envoi n'en reçoit pas (EventResource `reminder_available`, toast `attending_no_reminder`). Contenu : titre,
 * date, heure et **ville seulement** (jamais l'adresse exacte ni le lien de connexion : cloche, push sur écran verrouillé et e-mail `rappel-evenement` ; l'adresse reste sur la fiche).
 * Par lots (`chunkById`) pour tenir dans la minute du cron cPanel.
 */
final class SendEventReminders
{
    /**
     * @return array{events: int, notified: int}
     */
    public function __invoke(int $chunk = 100): array
    {
        $events = 0;
        $notified = 0;
        $hours = max(1, (int) config('xomiton.events.reminder_hours', 24));

        Event::query()->reminderDue($hours)
            ->chunkById($chunk, function ($rows) use (&$events, &$notified): void {
                /** @var Event $event */
                foreach ($rows as $event) {
                    $claimed = Event::query()->whereKey($event->getKey())->whereNull('reminder_sent_at')->update(['reminder_sent_at' => now()]);

                    if ($claimed === 0) {
                        continue;
                    }

                    $events++;
                    $notified += $this->remind($event);
                }
            });

        return ['events' => $events, 'notified' => $notified];
    }

    private function remind(Event $event): int
    {
        $fmt = LocaleFormatter::make();
        $time = $fmt->time($event->starts_at);
        $notification = new EventReminder(
            eventId: (int) $event->getKey(),
            title: (string) $event->title,
            timeLabel: $time,
            place: $event->is_online && ($event->place === null || trim($event->place) === '') ? null : ($event->place ?: (string) __('community.events.place_tbd')),
            dateLabel: (string) __('community.events.reminder_date', ['date' => $fmt->longDateWithDay($event->starts_at), 'time' => $time]),
            url: EventLinks::show($event),
        );
        $sent = 0;

        $rsvps = EventRsvp::query()->where('event_id', $event->getKey())->wantsReminder()->with('user');

        if ($event->organizer_id !== null) {
            EventAccess::withoutBlocksWith($rsvps, 'user_id', (int) $event->organizer_id);
        }

        $rsvps->chunkById(100, static function ($rsvps) use ($notification, $event, &$sent): void {
            foreach ($rsvps as $rsvp) {
                $user = $rsvp->user;

                if (! $user instanceof User || ! $user->isActive()) {
                    continue;
                }

                try {
                    $user->notify($notification);
                    $sent++;
                } catch (Throwable $exception) {
                    Log::warning('Événements : rappel de la veille non envoyé.', ['event_id' => $event->getKey(), 'error' => $exception->getMessage()]);
                }
            }
        });

        return $sent;
    }
}
