<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Member;

use App\Application\Account\Profile\MemberProfiles;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Member\ProfileConnectionsRequest;
use App\Http\Resources\Member\ProfilePresenter;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Profil d'un·e membre (P8-07, docs/API.md §18) : `GET members/{pseudo}` (en-tête et À propos), `…/connections`,
 * `…/media`, `…/events` ; `GET me/activity` (journal, soi-même seulement). Publications : `GET users/{id}/posts`
 * (§11). Profil invisible (privé, abonné·es seulement non suivi·e, blocage, compte suspendu, anonymisé ou inconnu,
 * sans `social.access`) → 404, jamais 403 : on ne révèle pas qu'un compte existe. Réponses `no-store` (centres
 * d'intérêt, champs sensibles partagés : jamais gardées par le client d'un appareil partagé).
 */
final class MemberProfileController extends ApiController
{
    public function __construct(
        private readonly MemberProfiles $profiles,
        private readonly ProfilePresenter $presenter,
    ) {}

    public function show(Request $request, string $pseudo): JsonResponse
    {
        [$viewer, $owner] = $this->resolve($request, $pseudo);

        return $this->data($this->presenter->header($request, $viewer, $owner))->withHeaders(self::NO_STORE);
    }

    public function connections(ProfileConnectionsRequest $request, string $pseudo): JsonResponse
    {
        [$viewer, $owner] = $this->resolve($request, $pseudo);
        $page = $this->presenter->connections($request, $viewer, $owner, $request->term(), (int) config('xomiton.profile.connections_per_page', 24));

        return $this->data($page['data'], 200, $page['meta'])->withHeaders(self::NO_STORE);
    }

    public function media(Request $request, string $pseudo): JsonResponse
    {
        [$viewer, $owner] = $this->resolve($request, $pseudo);

        return $this->data($this->presenter->media($viewer, $owner, (int) config('xomiton.profile.media_limit', 24)))->withHeaders(self::NO_STORE);
    }

    public function events(Request $request, string $pseudo): JsonResponse
    {
        [$viewer, $owner] = $this->resolve($request, $pseudo);
        $events = $this->presenter->events($request, $viewer, $owner, (int) config('xomiton.profile.events_limit', 12));

        return $this->data($events['data'], 200, [], [], ['suggestions' => $events['suggestions']])->withHeaders(self::NO_STORE);
    }

    public function activity(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $profile = $user->profile ?? Profile::query()->firstOrCreate(['user_id' => $user->getKey()]);
        Gate::authorize('viewActivity', $profile);

        return $this->data($this->presenter->activity($user, (int) config('xomiton.profile.activity_limit', 40)))->withHeaders(self::NO_STORE);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function resolve(Request $request, string $pseudo): array
    {
        /** @var User $viewer */
        $viewer = $request->user();
        $owner = $this->profiles->byPseudo($viewer, $pseudo) ?? throw new NotFoundHttpException;

        return [$viewer, $owner];
    }
}
