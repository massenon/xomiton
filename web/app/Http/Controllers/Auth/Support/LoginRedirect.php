<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth\Support;

use App\Application\Auth\DeviceTrust;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Ouverture de la session web après un mot de passe (appareil reconnu) ou un code OTP `login` vérifié :
 * Auth::login, régénération de session, cookie d'appareil de confiance, puis destination (administration pour
 * `admin.access`, sinon `next` interne ou le fil). Un compte pair `en_attente` entre avec un bandeau.
 */
final class LoginRedirect
{
    public function __construct(private readonly DeviceTrust $devices) {}

    public function open(Request $request, User $user, bool $remember, ?string $next): RedirectResponse
    {
        Auth::guard('web')->login($user, $remember);
        $request->session()->regenerate();

        $response = redirect()->to($this->destination($user, $next));

        if ($user->status === UserStatus::Pending) {
            $response->with('status', __('auth.pending'))->with('status_type', 'warning');
        } else {
            $response->with('toast', __('auth.login.welcome_back'))->with('toast_type', 'success');
        }

        $this->devices->trustDevice($user, $response);

        return $response;
    }

    public function destination(User $user, ?string $next): string
    {
        if ($user->can('admin.access')) {
            return route('admin.dashboard');
        }

        return $next ?? route('fil');
    }
}
