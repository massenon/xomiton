<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\Announcements\Announcements;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Annonce globale (P18-13, docs/API.md §5) : `GET announcements/active` rend l'annonce en cours pour le compte (`data`
 * null sinon) ; `POST announcements/{id}/dismiss` mémorise sa fermeture (204, idempotent ; 404 si elle n'est plus en
 * cours). Groupe `auth:sanctum` : jamais pour un·e visiteur·se (Q97).
 */
final class AnnouncementController extends ApiController
{
    public function __construct(private readonly Announcements $announcements) {}

    public function active(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->announcements->activeFor($this->user($request))])
            ->header('Cache-Control', 'no-store, private');
    }

    public function dismiss(Request $request, Announcement $announcement): Response
    {
        abort_unless($this->announcements->dismiss($announcement, $this->user($request)), 404);

        return $this->noContent();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
