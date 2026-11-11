<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Admin\Roles\TeamInvitations;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\Support\RegistrationGate;
use App\Application\Auth\Support\RegistrationOptions;
use App\Application\Shared\Referentials;
use App\Domain\Auth\Exceptions\AgeConfirmationRequired;
use App\Domain\Auth\Exceptions\AuthException;
use App\Domain\Auth\Exceptions\ConsentRequired;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Enums\UserStatus;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;

/**
 * /inscription : UN formulaire en 3 étapes obligatoires, envoyé une seule fois à la fin (décision client du 11/11/2026).
 * Le compte n'existe qu'à la fin ; il est créé, la session ouverte aussitôt, mais il reste en attente de la validation de
 * l'administration (bandeau « Ton compte attend la validation de l'équipe »). Inscriptions fermées par l'administration :
 * message configuré, formulaire newsletter et lien vers la connexion (FR-AUTH-06).
 */
final class RegisterController extends Controller
{
    public function create(Request $request, TeamInvitations $invitations, Referentials $referentials): View
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

        return view('auth.register', self::formData($request, $referentials) + [
            'invitationCode' => $invitation === null ? null : $code,
            'invitationRole' => $invitation?->role,
        ]);
    }

    public function store(RegisterRequest $request, RegisterUser $register): RedirectResponse
    {
        try {
            $user = $register($request->toRegisterData());
        } catch (AuthException $e) {
            $invalidInvitation = $e instanceof InvitationInvalid;

            return redirect()->route('inscription', $invalidInvitation ? [] : array_filter(['invitation' => $request->input('invitation')]))
                ->withErrors([self::errorField($e) => $e->userMessage()])
                // Les mots de passe ne sont jamais rejoués dans le formulaire.
                ->withInput($request->except(['password', 'password_confirmation']));
        }

        auth('web')->login($user);
        $request->session()->regenerate();

        // Compte en attente (cas général) : message rassurant ; compte actif d'emblée (mode `peers`) : toast de bienvenue.
        if ($user->status === UserStatus::Pending) {
            return redirect()->route('fil')->with('status', __('auth.register.created_pending'))->with('status_type', 'info');
        }

        return redirect()->route('fil')->with('toast', __('auth.register.created'))->with('toast_type', 'success');
    }

    /** Champ du formulaire qui porte le message d'une exception du cas d'usage (l'étape fautive est rouverte). */
    private static function errorField(AuthException $e): string
    {
        return match (true) {
            $e instanceof InvitationInvalid => 'invitation',
            $e instanceof AgeConfirmationRequired => 'age',
            $e instanceof ConsentRequired => 'cgu',
            default => 'pseudo',
        };
    }

    /**
     * Listes du formulaire (pays, pronoms, « Vous êtes ? », statuts, identités, orientations, zones → communes de tout pays) et étape à
     * ouvrir (la première en erreur après un envoi refusé, sinon la première).
     *
     * @return array<string, mixed>
     */
    private static function formData(Request $request, Referentials $referentials): array
    {
        $errors = $request->session()->get('errors');
        $keys = $errors instanceof ViewErrorBag ? array_keys($errors->getBag('default')->getMessages()) : [];

        $zones = $referentials->zones();

        return [
            'step' => RegistrationSteps::firstInvalid(array_map('strval', $keys)),
            'steps' => RegistrationSteps::labels(),
            'options' => RegistrationOptions::payload(),
            'zones' => array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name'], 'country_code' => $zone['country_code']], $zones),
            'zonesByCountry' => collect($zones)->groupBy('country_code')->map(static fn ($list): array => $list->map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']])->values()->all())->all(),
            'communesByZone' => collect($zones)->mapWithKeys(static fn (array $zone): array => [
                (string) $zone['id'] => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
            ])->all(),
            'minAge' => RegistrationOptions::minAge(),
        ];
    }
}
