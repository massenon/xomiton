<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Admin\Roles\TeamInvitations;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\Support\RegistrationGate;
use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /inscription, phase 1 « Compte » (P2-03, Q12 c) : la seule obligatoire. Le compte est créé et la session ouverte
 * aussitôt ; le code `signup` part en parallèle et la vérification reste différée. Inscriptions fermées par
 * l'administration : message configuré, formulaire newsletter et lien vers la connexion (FR-AUTH-06).
 */
final class RegisterController extends Controller
{
    public function create(Request $request, TeamInvitations $invitations): View
    {
        $code = $request->query('invitation');
        $invitation = null;

        if (is_string($code) && $code !== '') {
            try {
                $invitation = $invitations->peek($code);
            } catch (InvitationInvalid) {
                // Lien invalide ou expiré : la page l'annonce (le formulaire reste celui de l'inscription ouverte).
                $request->session()->now('status', __('auth.invitation_invalid'));
                $request->session()->now('status_type', 'warning');
                $code = null;
            }
        }

        // Une invitation valide ouvre l'inscription même fermée au public.
        if ($invitation === null && ! RegistrationGate::isOpen()) {
            return view('auth.register-closed', [
                'message' => RegistrationGate::closedMessage(),
                'reopensAt' => RegistrationGate::reopensAt(),
            ]);
        }

        return view('auth.register', [
            'step' => 'account',
            'steps' => RegistrationSteps::labels(),
            'invitationCode' => $invitation === null ? null : $code,
            'invitationRole' => $invitation?->role,
        ]);
    }

    public function store(RegisterRequest $request, RegisterUser $register): RedirectResponse
    {
        try {
            $user = $register($request->toRegisterData());
        } catch (AuthException $e) {
            return redirect()->route('inscription', $e instanceof InvitationInvalid ? [] : array_filter(['invitation' => $request->input('invitation')]))
                ->withErrors([$e instanceof InvitationInvalid ? 'invitation' : 'pseudo' => $e->userMessage()])
                ->withInput($request->only(['pseudo', 'email']));
        }

        auth('web')->login($user);
        $request->session()->regenerate();

        return redirect()->route(RegistrationSteps::ROUTES['profile'])
            ->with('toast', __('auth.register.account.created'))
            ->with('toast_type', 'success');
    }

    /** POST /inscription/{etape}/passer : « Passer » (phases 2 à 4) — rien n'est enregistré, les données restent. */
    public function skip(Request $request, string $etape): RedirectResponse
    {
        abort_unless(in_array($etape, RegistrationSteps::STEPS, true) && RegistrationSteps::isOptional($etape), 404);

        $next = RegistrationSteps::nextRoute($etape);

        return $next === null ? self::finished() : redirect()->to($next);
    }

    /** Fin du tunnel (après la phase 4 ou son « Passer ») : retour au fil avec un toast. */
    public static function finished(): RedirectResponse
    {
        return redirect()->route('fil')->with('toast', __('auth.register.done'))->with('toast_type', 'success');
    }
}
