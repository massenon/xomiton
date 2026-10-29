<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Mobile\DutyDashboard;
use App\Application\Admin\Mobile\StepUp;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `GET admin/dashboard` (tableau d'astreinte : compteurs) et `POST admin/step-up` (mot de passe → jeton de confirmation
 * court à usage unique, en-tête `X-Step-Up-Token` des actions à fort impact ; docs/API.md §23).
 */
final class DashboardController extends AdminApiController
{
    public function show(Request $request, DutyDashboard $dashboard): JsonResponse
    {
        return $this->data($dashboard->for($this->admin($request)));
    }

    public function stepUp(Request $request, StepUp $stepUp): JsonResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:255']]);

        return $this->data($stepUp->issue($this->admin($request), (string) $validated['password']) + ['header' => StepUp::HEADER]);
    }
}
