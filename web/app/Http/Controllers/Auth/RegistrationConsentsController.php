<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\RecordOptionalConsents;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationConsentsRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phase 4 « Consentements » (facultative) : données sensibles, newsletter — chaque réponse est horodatée. */
final class RegistrationConsentsController extends Controller
{
    public function show(Request $request): View
    {
        return view('auth.register.consents', [
            'step' => 'consents',
            'steps' => RegistrationSteps::labels(),
            'user' => $request->user(),
        ]);
    }

    public function store(RegistrationConsentsRequest $request, RecordOptionalConsents $record): RedirectResponse
    {
        $record($request->user(), $request->boolean('sensitive_data'), $request->boolean('newsletter'), $request->ip(), $request->userAgent());

        return RegisterController::finished();
    }
}
