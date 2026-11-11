<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\Profile\UpdateProfileImage;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\UpdateProfileImageRequest;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;

/**
 * `POST · DELETE me/profile/avatar` et `me/profile/cover` (P8-07, docs/API.md §3) : image ré-encodée sans métadonnées
 * (MediaUploader, EXIF retiré), ancienne image supprimée, complétion recalculée. Réponse : `avatar_url`, `cover_url`,
 * `completion`, `has_avatar`, `has_cover`. Envoi : e-mail confirmé (`verified`) ; envoi et retrait : `throttle:profile_image`.
 */
final class ProfileImageController extends ApiController
{
    public function __construct(private readonly UpdateProfileImage $images) {}

    public function store(UpdateProfileImageRequest $request, string $kind): JsonResponse
    {
        $user = $this->authorizedUser($request);
        /** @var UploadedFile $file */
        $file = $request->file('image');

        return $this->payload($user, $this->images->store($user, $kind, $file));
    }

    public function destroy(Request $request, string $kind): JsonResponse
    {
        $user = $this->authorizedUser($request);

        return $this->payload($user, $this->images->remove($user, $kind));
    }

    private function authorizedUser(Request $request): User
    {
        /** @var User $user */
        $user = $request->user();
        Gate::authorize('update', $user->profile ?? new Profile(['user_id' => $user->getKey()]));

        return $user;
    }

    private function payload(User $user, Profile $profile): JsonResponse
    {
        $user->setRelation('profile', $profile);

        return $this->data([
            'avatar_url' => $user->avatar_url,
            'cover_url' => $profile->coverUrl(),
            'completion' => (int) $profile->completion,
            'has_avatar' => filled($profile->avatar_path),
            'has_cover' => filled($profile->cover_path),
        ]);
    }
}
