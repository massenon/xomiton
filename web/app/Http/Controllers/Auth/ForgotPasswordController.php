<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\RequestPasswordReset;
use App\Http\Controllers\Auth\Support\PendingReset;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * /mot-de-passe-oublie (P2-05, Q8 c) : même réponse que le compte existe ou non ; un seul e-mail avec code et lien.
 */
final class ForgotPasswordController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(ForgotPasswordRequest $request, RequestPasswordReset $requestReset, PendingReset $pending): RedirectResponse
    {
        $email = (string) $request->string('email');

        $requestReset($email, $request->ip());
        $pending->start($email);

        return redirect()->route('otp', ['purpose' => 'reset'])
            ->with('status', __('auth.forgot.sent'))
            ->with('status_type', 'success');
    }
}
