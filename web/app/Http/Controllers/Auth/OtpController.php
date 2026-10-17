<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\ExchangeResetCode;
use App\Application\Auth\OtpService;
use App\Application\Auth\RequestPasswordReset;
use App\Application\Auth\Support\SignedResetToken;
use App\Application\Auth\VerifyEmail;
use App\Application\Auth\VerifyLoginOtp;
use App\Domain\Auth\Exceptions\OtpException;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Domain\Auth\Exceptions\OtpTooSoon;
use App\Enums\OtpPurpose;
use App\Http\Controllers\Auth\Support\LoginRedirect;
use App\Http\Controllers\Auth\Support\PendingLogin;
use App\Http\Controllers\Auth\Support\PendingReset;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * /code-de-verification (P2-04, LOGIN §5) : un seul écran pour `login` (connexion en attente), `signup`
 * (personne connectée, e-mail non confirmé) et `reset` (adresse saisie sur « Mot de passe oublié »).
 */
final class OtpController extends Controller
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly PendingLogin $pendingLogin,
        private readonly PendingReset $pendingReset,
    ) {}

    public function show(Request $request): View|RedirectResponse
    {
        $purpose = $this->resolvePurpose($request);
        $email = $purpose === null ? null : $this->emailFor($request, $purpose);

        if ($purpose === null || $email === null) {
            return $this->nothingPending($request, $purpose);
        }

        $resendAt = $this->resendAvailableAt($email, $purpose);
        $length = max(4, (int) config('xomiton.otp.length', 6));

        return view('auth.otp', [
            'purpose' => $purpose,
            'maskedEmail' => self::mask($email),
            'ttlMinutes' => OtpService::ttlMinutes(),
            'resendInSeconds' => $resendAt === null ? 0 : max(0, (int) ceil(now()->diffInSeconds($resendAt, false))),
            'length' => $length,
        ]);
    }

    public function verify(VerifyOtpRequest $request, VerifyLoginOtp $verifyLogin, VerifyEmail $verifyEmail, ExchangeResetCode $exchange, LoginRedirect $redirect): RedirectResponse
    {
        $purpose = $request->purpose();

        try {
            return match ($purpose) {
                OtpPurpose::Login => $this->verifyLogin($request, $verifyLogin, $redirect),
                OtpPurpose::Signup => $this->verifySignup($request, $verifyEmail),
                OtpPurpose::Reset => $this->verifyReset($request, $exchange),
            };
        } catch (OtpInvalid $e) {
            return redirect()->route('otp', ['purpose' => $purpose->value])->withErrors(['code' => $e->userMessage()]);
        }
    }

    public function resend(ResendOtpRequest $request, RequestPasswordReset $requestReset): RedirectResponse
    {
        $purpose = $request->purpose();
        $email = $this->emailFor($request, $purpose);

        if ($email === null) {
            return $this->nothingPending($request, $purpose);
        }

        $back = redirect()->route('otp', ['purpose' => $purpose->value]);

        try {
            $purpose === OtpPurpose::Reset
                ? $this->resendReset($requestReset, $email, $request->ip())
                : $this->otp->issue($email, $purpose, $request->ip());
        } catch (OtpException $e) {
            return $back->withErrors(['code' => $e->userMessage()]);
        }

        return $back->with('status', __('auth.otp_screen.resent'))->with('status_type', 'success');
    }

    /**
     * Réinitialisation : même e-mail (code + lien) que la première demande, délai de renvoi tenu par la session —
     * identiques que le compte existe ou non (FR-AUTH-04).
     *
     * @throws OtpTooSoon
     */
    private function resendReset(RequestPasswordReset $requestReset, string $email, ?string $ip): void
    {
        $retryAt = $this->pendingReset->resendAvailableAt();

        if ($retryAt !== null) {
            throw new OtpTooSoon($retryAt);
        }

        $requestReset($email, $ip);
        $this->pendingReset->markSent();
    }

    private function verifyLogin(VerifyOtpRequest $request, VerifyLoginOtp $verify, LoginRedirect $redirect): RedirectResponse
    {
        $user = $this->pendingLogin->user();

        // Compte suspendu entre le mot de passe et le code : la connexion repart de zéro et affichera le bon message.
        if ($user === null || $user->isSuspended()) {
            $this->pendingLogin->forget();

            return redirect()->route('connexion')->withErrors(['identifier' => __('auth.login.otp_expired')]);
        }

        $verify($user, $request->code());

        $remember = $this->pendingLogin->remembers();
        $next = $this->pendingLogin->next();
        $this->pendingLogin->forget();

        return $redirect->open($request, $user, $remember, $next);
    }

    private function verifySignup(VerifyOtpRequest $request, VerifyEmail $verify): RedirectResponse
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('connexion');
        }

        $verify($user, $request->code());

        $destination = $user->profile === null || (int) $user->profile->completion === 0
            ? route(RegistrationSteps::ROUTES['profile'])
            : route('fil');

        return redirect()->to($destination)->with('toast', __('auth.otp_screen.verified'))->with('toast_type', 'success');
    }

    /**
     * Réinitialisation : le code saisi est échangé contre une preuve signée (ExchangeResetCode, comme l'API) que
     * ResetPassword consommera avec le nouveau mot de passe ; le code à 6 chiffres ne circule qu'une fois et n'est
     * jamais gardé en session. Adresse inconnue ou code faux : même message.
     *
     * @throws OtpInvalid
     */
    private function verifyReset(VerifyOtpRequest $request, ExchangeResetCode $exchange): RedirectResponse
    {
        $email = $this->pendingReset->email();

        if ($email === null) {
            return redirect()->route('mot-de-passe-oublie')->withErrors(['email' => __('auth.reset.expired')]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            throw new OtpInvalid;
        }

        $this->pendingReset->prove(SignedResetToken::make($exchange($user, $request->code())));

        return redirect()->route('reinitialisation');
    }

    private function resolvePurpose(Request $request): ?OtpPurpose
    {
        $requested = $request->query('purpose');

        if (is_string($requested) && OtpPurpose::tryFrom($requested) !== null) {
            return OtpPurpose::from($requested);
        }

        return match (true) {
            $request->user() !== null => OtpPurpose::Signup,
            $this->pendingLogin->user() !== null => OtpPurpose::Login,
            $this->pendingReset->email() !== null => OtpPurpose::Reset,
            default => null,
        };
    }

    /** Adresse concernée par l'usage demandé, ou null si rien n'est en attente pour cette personne. */
    private function emailFor(Request $request, OtpPurpose $purpose): ?string
    {
        return match ($purpose) {
            OtpPurpose::Login => $this->pendingLogin->user()?->email,
            OtpPurpose::Signup => $request->user()?->email_verified_at === null ? $request->user()?->email : null,
            OtpPurpose::Reset => $this->pendingReset->email(),
        };
    }

    /** Délai de renvoi : par la session pour `reset` (aucun indice sur l'existence du compte), par OtpService sinon. */
    private function resendAvailableAt(string $email, OtpPurpose $purpose): ?Carbon
    {
        return $purpose === OtpPurpose::Reset
            ? $this->pendingReset->resendAvailableAt()
            : $this->otp->canResendAt($email, $purpose);
    }

    private function nothingPending(Request $request, ?OtpPurpose $purpose): RedirectResponse
    {
        if ($request->user() !== null) {
            $message = $purpose === null || $purpose === OtpPurpose::Signup ? 'auth.otp_screen.already_verified' : 'auth.otp_screen.nothing_pending';

            return redirect()->route('fil')->with('status', __($message))->with('status_type', 'info');
        }

        return redirect()->route('connexion')->with('status', __('auth.otp_screen.nothing_pending'))->with('status_type', 'info');
    }

    /** « a•••@exemple.bj » : l'écran ne répète jamais l'adresse en clair (écran partagé, mode discret). */
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');

        return Str::substr($local, 0, 1).'•••@'.$domain;
    }
}
