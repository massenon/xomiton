<?php

declare(strict_types=1);

namespace App\Application\Admin\Mobile;

use App\Domain\Account\Exceptions\StepUpRequired;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Étape de confirmation (`step-up`) des actions admin à fort impact de l'application mobile (maintenance, rôles,
 * invitations, refus d'une demande, réattribution d'une alerte ; PLAN_MOBILE_APP_UNIQUE.md §6.3) : la personne ressaisit
 * son mot de passe (`POST admin/step-up`) et reçoit un jeton court (`xomiton.accounts.step_up_minutes`, 5 min) **à usage
 * unique**, lié à son compte, renvoyé dans l'en-tête `X-Step-Up-Token` de l'action. Le jeton n'est gardé qu'en
 * empreinte (cache) ; un jeton d'un autre compte, expiré ou déjà utilisé donne 403 `step_up_required`.
 */
final class StepUp
{
    public const HEADER = 'X-Step-Up-Token';

    private const CACHE_PREFIX = 'admin-step-up:';

    /**
     * @return array{token: string, expires_in: int}
     *
     * @throws ValidationException mot de passe incorrect (422, message unique)
     */
    public function issue(User $user, string $password): array
    {
        if (! Hash::check($password, (string) $user->getAuthPassword())) {
            Audit::log('admin.step_up_failed', $user, null, $user);

            throw ValidationException::withMessages(['password' => (string) __('auth.failed')]);
        }

        $token = Str::random(48);
        $ttl = max(1, (int) config('xomiton.accounts.step_up_minutes', 5)) * 60;
        Cache::put(self::CACHE_PREFIX.hash('sha256', $token), (int) $user->getKey(), $ttl);

        return ['token' => $token, 'expires_in' => $ttl];
    }

    /**
     * Consomme le jeton de la requête (usage unique).
     *
     * @throws StepUpRequired
     */
    public function consume(User $user, Request $request): void
    {
        $token = trim((string) ($request->header(self::HEADER) ?? $request->input('step_up_token', '')));

        if ($token === '') {
            throw new StepUpRequired;
        }

        $owner = Cache::pull(self::CACHE_PREFIX.hash('sha256', $token));

        if ($owner === null || (int) $owner !== (int) $user->getKey()) {
            throw new StepUpRequired;
        }
    }
}
