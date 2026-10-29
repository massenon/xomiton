<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Admin\Alerts\AlertDesk;
use App\Application\Alerts\AcknowledgeAlert;
use App\Application\Alerts\AddInternalNote;
use App\Application\Alerts\ChangeAlertStatus;
use App\Application\Alerts\ReassignAlert;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Http\Resources\Admin\AdminAlertResource;
use App\Http\Resources\Alerts\AlertResource;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Policies\Scopes\AlertScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Alertes et SOS pour l'admin d'astreinte (`admin/alerts`, P15-16) — mêmes cas d'usage que l'écran `/admin/alertes`
 * (AcknowledgeAlert, ChangeAlertStatus, ReassignAlert, AddInternalNote ; autorisation par AlertPolicy dans chacun) :
 * liste filtrable (type, statut, priorité, département, ouvertes, sans accusé), détail avec position ARRONDIE, accuser
 * réception (arrête l'escalade), prendre en charge, changer de statut / clore, réattribuer (confirmation `step-up`),
 * note interne. Écritures rejouables (Idempotency-Key), journal `source=mobile`.
 */
final class AlertController extends AdminApiController
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('viewAny', Alert::class);

        $filters = $request->validate([
            'type' => ['sometimes', 'string', Rule::in(array_values(AlertResource::API_TYPES))],
            'status' => ['sometimes', 'string', Rule::in(array_map(static fn (AlertStatus $s): string => $s->value, AlertStatus::cases()))],
            'priority' => ['sometimes', 'string', Rule::in(array_map(static fn (AlertPriority $p): string => $p->value, AlertPriority::cases()))],
            'zone_id' => ['sometimes', 'integer', 'min:1'],
            'state' => ['sometimes', 'string', 'in:open,all'],
            'unacknowledged' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('xomiton.api.max_per_page', 50)],
        ]);

        $query = AlertScope::routedTo($user)->with(['zone:id,name', 'commune:id,name', 'routedContact:id,name']);

        if (isset($filters['type'])) {
            $query->where('type', AlertType::from((string) array_search($filters['type'], AlertResource::API_TYPES, true)));
        }

        if (isset($filters['status'])) {
            $query->where('status', AlertStatus::from($filters['status']));
        }

        if (isset($filters['priority'])) {
            $query->where('priority', AlertPriority::from($filters['priority']));
        }

        if (isset($filters['zone_id'])) {
            $query->where('zone_id', (int) $filters['zone_id']);
        }

        if (($filters['state'] ?? 'open') === 'open') {
            $query->open();
        }

        if ($request->boolean('unacknowledged')) {
            $query->unacknowledged();
        }

        $page = $query->latestFirst()->paginate($this->perPage($request));

        return $this->page(
            $page,
            $page->getCollection()->map(fn (Alert $alert): array => AdminAlertResource::make($alert)->resolve($request))->values()->all(),
            ['state' => $filters['state'] ?? 'open', 'new' => AlertScope::routedTo($user)->where('status', AlertStatus::New)->count()],
        );
    }

    public function show(Request $request, Alert $alert): JsonResponse
    {
        return $this->detail($request, $alert);
    }

    public function acknowledge(Request $request, Alert $alert, AcknowledgeAlert $acknowledge): JsonResponse
    {
        $result = $acknowledge->byUser($alert, $this->admin($request));

        return $this->detail($request, $result->alert, [
            'acknowledged_now' => $result->acknowledgedNow(),
            'already_acknowledged' => $result->alreadyAcknowledged,
            'closed' => $result->closed,
        ]);
    }

    /** Prendre en charge : statut « en cours » (vaut accusé de réception s'il manque). */
    public function take(Request $request, Alert $alert, ChangeAlertStatus $change): JsonResponse
    {
        return $this->detail($request, $change($alert, AlertStatus::InProgress, $this->admin($request)));
    }

    public function status(Request $request, Alert $alert, ChangeAlertStatus $change): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(array_map(static fn (AlertStatus $s): string => $s->value, AlertStatus::cases()))],
        ]);

        return $this->detail($request, $change($alert, AlertStatus::from((string) $validated['status']), $this->admin($request)));
    }

    public function close(Request $request, Alert $alert, ChangeAlertStatus $change): JsonResponse
    {
        return $this->detail($request, $change($alert, AlertStatus::Closed, $this->admin($request)));
    }

    /** Contacts de l'annuaire proposés à la réattribution (même département d'abord). */
    public function contacts(Request $request, Alert $alert, AlertDesk $desk): JsonResponse
    {
        Gate::forUser($this->admin($request))->authorize('reassign', $alert);
        $role = (string) $request->query('role', '');

        $contacts = $desk->contactsFor($alert, $role, ! $request->boolean('all_zones'));

        return $this->data($contacts->map(static fn (DirectoryContact $contact): array => [
            'id' => (int) $contact->getKey(),
            'name' => $contact->name,
            'role' => $contact->role->value,
            'zone' => $contact->zone?->name,
        ])->values()->all());
    }

    public function reassign(Request $request, Alert $alert, ReassignAlert $reassign): JsonResponse
    {
        $validated = $request->validate(['contact_id' => ['required', 'integer', 'min:1']]);
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('reassign', $alert);
        $this->confirm($request);

        $contact = DirectoryContact::query()->findOrFail((int) $validated['contact_id']);

        return $this->detail($request, $reassign($alert, $contact, $user));
    }

    public function note(Request $request, Alert $alert, AddInternalNote $note): JsonResponse
    {
        $validated = $request->validate(['body' => ['required', 'string']]);
        $note($alert, $this->admin($request), (string) $validated['body']);

        return $this->detail($request, $alert, status: 201);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function detail(Request $request, Alert $alert, array $meta = [], int $status = 200): JsonResponse
    {
        Gate::forUser($this->admin($request))->authorize('view', $alert);
        $alert->load(['zone:id,name', 'commune:id,name', 'routedContact:id,name', 'statusChanges', 'notes.author:id,pseudo', 'escalations']);

        return $this->respond(AdminAlertResource::make($alert)->detail(), $status, $meta);
    }
}
