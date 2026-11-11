<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationSecurityRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phase 3 « Sécurité » (facultative) : contact de confiance et mode discret. */
final class RegistrationSecurityController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing('profile');

        return view('auth.register.security', [
            'step' => 'security',
            'steps' => RegistrationSteps::labels(),
            'user' => $user,
            'profile' => $user?->profile,
        ]);
    }

    public function store(RegistrationSecurityRequest $request, CompleteRegistrationProfile $complete): RedirectResponse
    {
        $complete($request->user(), $request->toProfileData());

        return redirect()->to((string) RegistrationSteps::nextRoute('security'))
            ->with('toast', __('auth.register.security.saved'))
            ->with('toast_type', 'success');
    }
}
