<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Mobile\StepUp;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Socle des contrôleurs du groupe `admin/*` (P15-16) : compte courant, étape de confirmation (`step-up`) et liste paginée
 * dont les éléments sont déjà résolus. Les réponses portent `Cache-Control: no-store, private` (EnsureAdminMobile).
 * Chaque contrôleur reste fin : requête validée → cas d'usage existant (le même que l'écran Livewire, qui autorise via
 * policy) → Resource.
 */
abstract class AdminApiController extends ApiController
{
    protected function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** Exige et consomme le jeton de confirmation (mot de passe ressaisi) pour une action à fort impact. */
    protected function confirm(Request $request): void
    {
        app(StepUp::class)->consume($this->admin($request), $request);
    }

    /**
     * @param  LengthAwarePaginator<array-key, mixed>  $paginator
     * @param  list<array<string, mixed>>  $items
     * @param  array<string, mixed>  $meta
     */
    protected function page(LengthAwarePaginator $paginator, array $items, array $meta = []): JsonResponse
    {
        $paginator->withQueryString();

        return $this->data($items, 200, [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ] + $meta, [
            'first' => $paginator->url(1),
            'last' => $paginator->url($paginator->lastPage()),
            'prev' => $paginator->previousPageUrl(),
            'next' => $paginator->nextPageUrl(),
        ]);
    }
}
