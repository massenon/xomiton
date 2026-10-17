<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\DeviceTrust;
use App\Application\Auth\OtpService;
use App\Application\Auth\ResetPassword;
use App\Application\Auth\Support\SignedResetToken;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Http\Controllers\Auth\Support\PendingReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /nouveau-mot-de-passe (P2-05) : atteint par le lien magique signé (`?email=&token=`, 403 s'il est invalide ou
 * expiré) ou après le code saisi à l'écran OTP. Le nouveau mot de passe révoque sessions, jetons et appareils reconnus.
 */
final class ResetPasswordController extends Controller
{
    public function __construct(private readonly PendingReset $pending, private readonly DeviceTrust $devices) {}

    public function create(Request $request): View|RedirectResponse
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if (is_string($token) && $token !== '') {
            $email = is_string($email) ? OtpService::normalizeEmail($email) : '';

            abort_if(SignedResetToken::verify($token, $email) === null, 403, __('auth.reset.link_invalid'));

            return view('auth.reset-password', ['email' => $email, 'token' => $token]);
        }

        if ($this->pending->proof() === null) {
            return redirect()->route('mot-de-passe-oublie')->withErrors(['email' => __('auth.reset.expired')]);
        }

        return view('auth.reset-password', ['email' => null, 'token' => null]);
    }

    public function store(ResetPasswordRequest $request, ResetPassword $reset): RedirectResponse
    {
        $token = $request->filled('token') ? (string) $request->string('token') : null;
        $email = $token !== null ? (string) $request->string('email') : $this->pending->email();
        $proof = $token ?? $this->pending->proof();

        if ($email === null || $proof === null) {
            return redirect()->route('mot-de-passe-oublie')->withErrors(['email' => __('auth.reset.expired')]);
        }

        try {
            $reset($email, $proof, (string) $request->string('password'));
        } catch (OtpInvalid $e) {
            $this->pending->forget();

            return redirect()->route('mot-de-passe-oublie')->withErrors(['email' => $e->userMessage()]);
        }

        $this->pending->forget();

        $response = redirect()->route('connexion')->with('toast', __('auth.reset.done'))->with('toast_type', 'success');
        $this->devices->forget($response);

        return $response;
    }
}
