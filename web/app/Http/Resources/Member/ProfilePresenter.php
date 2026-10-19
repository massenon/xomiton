<?php

declare(strict_types=1);

namespace App\Http\Resources\Member;

use App\Application\Account\Profile\MemberProfiles;
use App\Application\Account\Profile\ProfileActivity;
use App\Application\Account\Profile\ProfileConnections;
use App\Application\Account\Profile\ProfileEvents;
use App\Application\Account\Profile\ProfileMedia;
use App\Application\Account\Profile\SensitiveDataConsent;
use App\Application\Community\Events\EventDirectory;
use App\Http\Resources\Events\EventResource;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Mise en forme commune du profil (P8-07) pour l'API (`GET members/{pseudo}…`, docs/API.md §18) et la page Inertia
 * `Member/Profile` (`/profil`, `/membres/{pseudo}`) : un seul format, l'écran Vue relit la suite par l'API.
 */
final class ProfilePresenter
{
    public function __construct(
        private readonly MemberProfiles $profiles,
        private readonly ProfileConnections $connections,
        private readonly ProfileMedia $media,
        private readonly ProfileEvents $events,
        private readonly ProfileActivity $activity,
        private readonly SensitiveDataConsent $consent,
        private readonly EventDirectory $directory,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function header(Request $request, User $viewer, User $owner): array
    {
        $isSelf = $viewer->is($owner);

        return MemberProfileResource::make($owner)->withContext(
            $isSelf,
            $this->profiles->stats($viewer, $owner, $this->connections),
            $this->profiles->relationship($viewer, $owner),
            $this->consent->granted($owner),
            ! $isSelf && $viewer->can('social.access') && ! MemberProfiles::blocked($viewer, $owner),
        )->resolve($request);
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array{current_page: int, last_page: int, per_page: int, total: int, q: string|null}}
     */
    public function connections(Request $request, User $viewer, User $owner, ?string $search, int $perPage): array
    {
        $paginator = $this->connections->query($viewer, $owner, $search)->paginate($perPage);

        return [
            'data' => ProfileConnectionResource::collection(collect($paginator->items()))->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'q' => $search,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function media(User $viewer, User $owner, int $limit): array
    {
        return $this->media->for($viewer, $owner, $limit);
    }

    /**
     * @return array{data: list<array<string, mixed>>, suggestions: list<array<string, mixed>>}
     */
    public function events(Request $request, User $viewer, User $owner, int $limit): array
    {
        $found = $this->events->for($viewer, $owner, $limit);
        $all = $found['events']->concat($found['suggestions']);
        $rsvps = $this->directory->rsvps($viewer, $all);

        return [
            'data' => EventResource::list($found['events'], $rsvps, $request),
            'suggestions' => EventResource::list($found['suggestions'], $rsvps, $request),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activity(User $user, int $limit): array
    {
        return $this->activity->for($user, $limit);
    }

    /**
     * Colonne du profil (prototype x_profile_side() : Présentation dans l'en-tête, 6 médias, 6 connexions).
     *
     * @return array{media: list<array<string, mixed>>, connections: list<array<string, mixed>>}
     */
    public function side(Request $request, User $viewer, User $owner): array
    {
        return [
            'media' => $this->media->for($viewer, $owner, (int) config('xomiton.profile.side_media', 6)),
            'connections' => ProfileConnectionResource::collection(
                $this->connections->query($viewer, $owner)->limit((int) config('xomiton.profile.side_connections', 6))->get(),
            )->resolve($request),
        ];
    }
}
