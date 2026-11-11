<?php

declare(strict_types=1);

namespace App\Http\Resources\Events;

use App\Application\Community\Events\EventAccess;
use App\Application\Community\Events\EventLinks;
use App\Application\Community\Groups\GroupLinks;
use App\Domain\Community\EventDisclosure;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Support\LocaleFormatter;
use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Événement tel qu'il sort de l'API (P8-04, docs/API.md §15) : identifiant public ULID (ADR-27), titre, dates (ISO 8601 et
 * libellés prêts à afficher : jour, mois court, date longue, heure), ville ou zone (`place`), « en ligne », catégorie,
 * description, organisation (pseudo ou libellé, jamais d'autre donnée du compte), groupe, nombre de participant·es (jamais
 * leur liste), réponse de la personne qui lit (`rsvp`), rappel de la veille encore à venir (`reminder_available` : faux
 * une fois parti ou l'événement commencé — une réponse tardive n'en reçoit pas) et gestes permis (`can`).
 *
 * **`address` et `online_url` ne figurent dans la réponse que si la personne a répondu « Je participe »**, organise
 * l'événement ou modère (EventDisclosure::revealsExactPlace, simulateur.html l.1164) ; sinon les clés sont absentes et
 * `exact_place_reserved` dit qu'une adresse ou un lien attend les participant·es. La réponse est fournie par l'appelant
 * (`withRsvp`), chargée en une requête par page.
 *
 * @mixin Event
 */
final class EventResource extends JsonResource
{
    private ?EventRsvp $rsvp = null;

    private bool $rsvpKnown = false;

    public function withRsvp(?EventRsvp $rsvp): self
    {
        $this->rsvp = $rsvp;
        $this->rsvpKnown = true;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Event $event */
        $event = $this->resource;
        $viewer = $request->user('sanctum') ?? $request->user();
        $viewer = $viewer instanceof User ? $viewer : null;
        $rsvp = $this->rsvpKnown || $viewer === null
            ? $this->rsvp
            : EventRsvp::query()->where('event_id', $event->getKey())->where('user_id', $viewer->getKey())->first();

        $access = app(EventAccess::class);
        $reveal = $viewer !== null && $access->revealsExactPlace($viewer, $event, $rsvp);
        $going = $rsvp?->status === RsvpStatus::Going;
        $upcoming = $event->isUpcoming();
        $hasExact = ($event->address !== null && $event->address !== '') || ($event->is_online && $event->online_url !== null);
        $fmt = LocaleFormatter::make();
        $category = $event->category_id !== null ? $event->category : null;
        $group = $event->group_id !== null ? $event->group : null;
        $social = $viewer?->can('social.access') ?? false;

        return [
            'id' => (string) $event->ulid,
            'title' => (string) $event->title,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'day' => $fmt->pattern('d', $event->starts_at),
            'month_short' => $fmt->monthName((int) $fmt->pattern('M', $event->starts_at), true),
            'date_label' => $fmt->longDateWithDay($event->starts_at),
            'time_label' => $fmt->time($event->starts_at),
            'end_time_label' => $event->ends_at !== null ? $fmt->time($event->ends_at) : null,
            'place' => $event->place !== null && $event->place !== '' ? (string) $event->place : null,
            'is_online' => (bool) $event->is_online,
            'location_label' => self::locationLabel($event),
            $this->mergeWhen($reveal, [
                'address' => $event->address,
                'online_url' => $event->is_online ? $event->online_url : null,
            ]),
            'exact_place_reserved' => ! $reveal && $hasExact,
            'category' => $category !== null ? ['slug' => (string) $category->slug, 'label' => (string) $category->label] : null,
            'description' => $event->description,
            'image_url' => MediaUrl::resolve($event->image_path, Event::DEFAULT_COVER),
            'organizer' => $event->organizerLabel(),
            'group' => $group !== null && $group->is_active ? ['id' => (string) $group->ulid, 'name' => (string) $group->name, 'url' => GroupLinks::show($group)] : null,
            'going_count' => (int) $event->going_count,
            'is_public' => (bool) $event->is_public,
            'is_past' => ! $upcoming,
            'status' => $event->status->value,
            'url' => EventLinks::show($event),
            'rsvp' => $going ? ['status' => $rsvp->status->value, 'reminder' => (bool) $rsvp->reminder_opt_in] : null,
            'reminder_available' => $event->reminderAvailable(),
            'can' => [
                'rsvp' => $social && ! $going && EventDisclosure::acceptsResponses($event->isPublished(), $upcoming),
                'cancel' => $going,
                'update' => $viewer?->can('update', $event) ?? false,
            ],
            'created_at' => $event->created_at?->toIso8601String(),
        ];
    }

    /**
     * Éléments d'une page d'événements, chacun avec la réponse de la personne (chargée en une requête par l'appelant).
     *
     * @param  iterable<Event>  $events
     * @param  array<int, EventRsvp>  $rsvps  par identifiant d'événement
     * @return list<array<string, mixed>>
     */
    public static function list(iterable $events, array $rsvps, Request $request): array
    {
        $items = [];

        foreach ($events as $event) {
            $items[] = self::make($event)->withRsvp($rsvps[(int) $event->getKey()] ?? null)->resolve($request);
        }

        return $items;
    }

    /** « Cotonou », « En ligne », « Porto-Novo · en ligne », ou « Lieu à préciser ». */
    public static function locationLabel(Event $event): string
    {
        $place = $event->place !== null && trim($event->place) !== '' && mb_strtolower(trim($event->place)) !== 'en ligne' ? trim($event->place) : null;

        return match (true) {
            $place !== null && $event->is_online => (string) __('community.events.location.both', ['place' => $place]),
            $place !== null => $place,
            (bool) $event->is_online => (string) __('community.events.location.online'),
            default => (string) __('community.events.place_tbd'),
        };
    }
}
