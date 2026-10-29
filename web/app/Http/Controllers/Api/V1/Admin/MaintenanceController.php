<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Platform\DisableMaintenance;
use App\Application\Platform\EnableMaintenance;
use App\Application\Platform\MaintenanceOverview;
use App\Application\Platform\MaintenanceStatus;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Mode maintenance (`admin/maintenance`, P15-16 ; `platform.maintenance`, RG-14) — mêmes cas d'usage que l'écran
 * `/admin/maintenance` : état (inactif · annoncé · en cours · terminé), activer (motif interne obligatoire, durée
 * estimée facultative, message public) et désactiver. **Confirmation en deux temps** : case `confirm: true` dans le corps
 * et jeton `step-up` ; personne ne peut activer une maintenance qui l'exclurait (garde-fou anti-verrouillage, 403
 * `maintenance_lockout`) ; une maintenance déjà active n'est pas réactivée (422).
 */
final class MaintenanceController extends AdminApiController
{
    public function show(Request $request): JsonResponse
    {
        Gate::forUser($this->admin($request))->authorize('toggleMaintenance', Setting::class);

        return $this->data($this->state());
    }

    public function enable(Request $request, EnableMaintenance $enable): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:3', 'max:'.EnableMaintenance::REASON_MAX_LENGTH],
            'duration_minutes' => ['sometimes', 'nullable', 'integer', 'min:5', 'max:1440'],
            'message' => ['sometimes', 'nullable', 'string', 'max:'.MaintenanceStatus::MESSAGE_MAX_LENGTH],
            'confirm' => ['required', 'accepted'],
        ]);
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('toggleMaintenance', Setting::class);
        $this->confirm($request);

        $minutes = $validated['duration_minutes'] ?? null;
        $enable($admin, $validated['message'] ?? null, $minutes === null ? null : now()->addMinutes((int) $minutes), (string) $validated['reason']);

        return $this->data($this->state());
    }

    public function disable(Request $request, DisableMaintenance $disable): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['sometimes', 'nullable', 'string', 'max:'.EnableMaintenance::REASON_MAX_LENGTH],
            'confirm' => ['required', 'accepted'],
        ]);
        $admin = $this->admin($request);
        Gate::forUser($admin)->authorize('toggleMaintenance', Setting::class);
        $this->confirm($request);

        $disable($admin, $validated['reason'] ?? null);

        return $this->data($this->state());
    }

    /**
     * @return array<string, mixed>
     */
    private function state(): array
    {
        $overview = MaintenanceOverview::make(5);

        return [
            'state' => $overview->state->value,
            'state_label' => $overview->state->label(),
            'active' => $overview->snapshot->active,
            'message' => $overview->snapshot->message,
            'starts_at' => $overview->snapshot->startsAt?->toIso8601String(),
            'ends_at' => $overview->snapshot->endsAt?->toIso8601String(),
            'overdue' => $overview->snapshot->overdue,
            'announced_at' => $overview->announced?->starts_at?->toIso8601String(),
            'upcoming' => $overview->upcoming->map(static fn ($window): array => [
                'id' => (int) $window->getKey(),
                'starts_at' => $window->starts_at?->toIso8601String(),
                'ends_at' => $window->ends_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
