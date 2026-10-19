<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Application\Shared\Referentials;
use App\Enums\MembershipStatus;
use App\Models\Event;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;

/**
 * Réglages de la modale « Créer un événement » (P8-04, partials/modals.php l.166-190), transmis en props Inertia par
 * `/evenements` et par le fil (`composer.event`, pastille « Événement » de P7-03) : droit de créer, catégories
 * d'événement actives (Referentials), groupes où la personne peut publier (propriétaire ou membre active, groupes
 * actifs), ouverture aux visiteur·ses (modération seulement) et bornes — les mêmes que StoreEventRequest, pour que la
 * validation dans le navigateur ne contredise jamais le serveur.
 */
final class EventCreateOptions
{
    private const MAX_GROUPS = 50;

    public function __construct(private readonly Referentials $referentials) {}

    /**
     * @return array{
     *     can: bool, can_publish_public: bool, default_time: string,
     *     categories: list<array{value: string, label: string}>, groups: list<array{value: string, label: string}>,
     *     limits: array{title_min: int, title_max: int, place_max: int, address_max: int, description_max: int, max_duration_days: int}
     * }
     */
    public function for(User $user): array
    {
        $can = $user->can('create', Event::class);

        return [
            'can' => $can,
            'can_publish_public' => $can && $user->can('publishPublicly', Event::class),
            'default_time' => (string) config('xomiton.events.default_time', '15:00'),
            'categories' => array_map(static fn (array $category): array => [
                'value' => (string) $category['id'],
                'label' => $category['label'],
            ], $this->referentials->categories('event')),
            'groups' => $can ? $this->groups($user) : [],
            'limits' => self::limits(),
        ];
    }

    /**
     * @return array{title_min: int, title_max: int, place_max: int, address_max: int, description_max: int, max_duration_days: int}
     */
    public static function limits(): array
    {
        return [
            'title_min' => (int) config('xomiton.events.title_min', 3),
            'title_max' => (int) config('xomiton.events.title_max', 200),
            'place_max' => (int) config('xomiton.events.place_max', 120),
            'address_max' => (int) config('xomiton.events.address_max', 255),
            'description_max' => (int) config('xomiton.events.description_max', 2000),
            'max_duration_days' => (int) config('xomiton.events.max_duration_days', 31),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function groups(User $user): array
    {
        $id = (int) $user->getKey();
        $memberOf = GroupMember::query()->select('group_id')->where('user_id', $id)->where('status', MembershipStatus::Active->value);

        return Group::query()->active()
            ->where(static fn ($q) => $q->where('owner_id', $id)->orWhereIn('groups.id', $memberOf))
            ->orderBy('name')->limit(self::MAX_GROUPS)->get(['id', 'ulid', 'name'])
            ->map(static fn (Group $group): array => ['value' => (string) $group->ulid, 'label' => (string) $group->name])
            ->values()->all();
    }
}
