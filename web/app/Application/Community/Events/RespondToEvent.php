<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Domain\Auth\Exceptions\EmailNotVerified;
use App\Domain\Community\EventDisclosure;
use App\Domain\Community\Exceptions\EventFinished;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * « Je participe » / annuler (P8-04, events.php l.17-25 ; `POST events/{ulid}/rsvp {going, reminder?}`, simulateur.html
 * l.1162) : une seule réponse par personne et par événement (unique `(event_id, user_id)`), rappel de la veille choisi
 * avec la réponse (oui par défaut). Annuler supprime la réponse. Idempotent : répondre deux fois la même chose ne change
 * rien. `going_count` recompté dans la même transaction (verrou sur l'événement : deux réponses simultanées ne se
 * perdent pas). Participer exige un e-mail confirmé (403 `email_unverified`, comme créer : un compte jetable n'obtient
 * pas l'adresse exacte ni le lien) ; participer à un événement terminé → 422 `event_finished` ; annuler reste toujours
 * possible. Aucun journal d'audit : la participation à un événement de la communauté est une donnée sensible qu'on ne
 * duplique pas.
 */
final class RespondToEvent
{
    public function __invoke(User $user, Event $event, bool $going, bool $reminder = true): ?EventRsvp
    {
        if ($going && ! $user->hasVerifiedEmail()) {
            throw new EmailNotVerified;
        }

        if ($going && ! EventDisclosure::acceptsResponses($event->isPublished(), $event->isUpcoming())) {
            throw new EventFinished;
        }

        return DB::transaction(static function () use ($user, $event, $going, $reminder): ?EventRsvp {
            Event::query()->whereKey($event->getKey())->lockForUpdate()->first(['id']);

            $rsvp = EventRsvp::query()->where('event_id', $event->getKey())->where('user_id', $user->getKey())->first();

            if ($going) {
                $rsvp ??= new EventRsvp(['event_id' => $event->getKey(), 'user_id' => $user->getKey()]);
                $rsvp->fill(['status' => RsvpStatus::Going, 'reminder_opt_in' => $reminder])->save();
            } else {
                $rsvp?->delete();
                $rsvp = null;
            }

            $count = EventRsvp::query()->where('event_id', $event->getKey())->going()->count();
            Event::query()->whereKey($event->getKey())->update(['going_count' => $count]);
            $event->forceFill(['going_count' => $count])->syncOriginalAttribute('going_count');

            return $rsvp;
        });
    }
}
