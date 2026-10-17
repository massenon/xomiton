<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Auth\CompleteRegistrationProfile;
use App\Http\Controllers\Auth\Support\RegistrationSteps;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegistrationProfileRequest;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** Phase 2 « Profil » (facultative) : pronoms, département → commune en cascade, type de membre. */
final class RegistrationProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        $user?->loadMissing('profile');

        $zones = Zone::query()->active()->ordered()
            ->with(['communes' => static fn ($q) => $q->active()->ordered()])
            ->get(['id', 'name']);

        return view('auth.register.profile', [
            'step' => 'profile',
            'steps' => RegistrationSteps::labels(),
            'user' => $user,
            'profile' => $user?->profile,
            'zones' => $zones,
            'communesByZone' => $zones->mapWithKeys(static fn (Zone $zone): array => [
                (string) $zone->getKey() => $zone->communes->map(static fn ($c): array => ['id' => $c->getKey(), 'name' => $c->name])->values()->all(),
            ])->all(),
        ]);
    }

    public function store(RegistrationProfileRequest $request, CompleteRegistrationProfile $complete): RedirectResponse
    {
        $complete($request->user(), $request->toProfileData());

        return redirect()->to((string) RegistrationSteps::nextRoute('profile'))
            ->with('toast', __('auth.register.profile.saved'))
            ->with('toast_type', 'success');
    }
}
