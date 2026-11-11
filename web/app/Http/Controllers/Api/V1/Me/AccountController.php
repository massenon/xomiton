<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Me;

use App\Application\Account\RequestAccountDeletion;
use App\Http\Controllers\Api\V1\ApiController;
use App\Http\Requests\Api\Me\DeleteAccountRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * DELETE /api/v1/me (P6-06) : suppression du compte, effacement des données sous `deletion_grace_days` jours
 * (App\Application\Account\RequestAccountDeletion). 200 avec la date d'effacement (le client l'affiche), jamais 204.
 */
final class AccountController extends ApiController
{
    public function destroy(DeleteAccountRequest $request, RequestAccountDeletion $delete): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $erasedFrom = $delete($user);

        if ($request->hasSession()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        $days = (int) config('xomiton.accounts.deletion_grace_days', 30);

        return $this->data([
            'deleted' => true,
            'erased_from' => $erasedFrom->toIso8601String(),
            'message' => __('profile.settings.delete.done', ['days' => $days]),
        ]);
    }
}
