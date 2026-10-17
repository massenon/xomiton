<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\AuthenticateUser;
use App\Domain\Auth\Exceptions\AuthException;
use App\Http\Controllers\Auth\Support\LoginRedirect;
use App\Http\Controllers\Auth\Support\PendingLogin;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * /connexion (P2-02, P4-09, prototype sign-in.php) : formulaire → AuthenticateUser → session ouverte ou écran OTP.
 */
final class LoginController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.login', [
            'next' => LoginRequest::internalPath($request->query('next')),
            'showDemo' => app()->environment('local'),
        ]);
    }

    public function store(LoginRequest $request, AuthenticateUser $authenticate, PendingLogin $pending, LoginRedirect $redirect): RedirectResponse
    {
        try {
            $result = $authenticate($request->toLoginData());
        } catch (AuthException $e) {
            return back()
                ->withErrors(['identifier' => $e->userMessage()])
                ->withInput($request->only(['identifier', 'remember', 'next']));
        }

        if ($result->requiresOtp) {
            $pending->remember($result->user, $request->boolean('remember'), $request->safeNext());

            return redirect()->route('otp', ['purpose' => 'login']);
        }

        return $redirect->open($request, $result->user, $request->boolean('remember'), $request->safeNext());
    }
}
