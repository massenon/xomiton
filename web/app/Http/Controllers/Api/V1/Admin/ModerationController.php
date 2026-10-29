<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Moderation\DecideReport;
use App\Application\Moderation\ReportQueue;
use App\Http\Resources\Admin\AdminReportResource;
use App\Models\ModerationReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Modération essentielle sur mobile (`admin/moderation/reports`, P15-16 ; `moderation.manage`) — mêmes cas d'usage que
 * l'écran `/admin/moderation` (DecideReport, ReportQueue) : file des signalements (une ligne par contenu, nombre de
 * signalements et de personnes), **masquer** le contenu, le **rendre visible** (restaurer) ou **ignorer** le signalement.
 * Avertir et suspendre un compte restent sur le web. Une décision vaut pour tous les signalements en attente du même contenu.
 */
final class ModerationController extends AdminApiController
{
    public function index(Request $request, ReportQueue $queue): JsonResponse
    {
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('viewAny', ModerationReport::class);
        $state = $request->validate(['state' => ['sometimes', 'string', 'in:pending,handled,rejected,all']])['state'] ?? 'pending';

        $page = $queue->forState($state)->with(['reportedUser:id,pseudo', 'subject'])->orderByDesc('id')->paginate($this->perPage($request));
        $siblings = $queue->siblings($page->getCollection());

        return $this->page(
            $page,
            $page->getCollection()->map(static fn (ModerationReport $report): array => (new AdminReportResource($report, $siblings[$report->subject_type.':'.$report->subject_id] ?? null))->resolve($request))->values()->all(),
            ['state' => $state, 'pending' => $queue->pendingGroups()->toBase()->count()],
        );
    }

    public function hide(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $validated = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:'.DecideReport::REASON_MAX]]);
        $closed = $decide($report, DecideReport::HIDE, $this->admin($request), $validated['reason'] ?? null);

        return $this->done($request, $report, $closed);
    }

    public function dismiss(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $closed = $decide($report, DecideReport::DISMISS, $this->admin($request));

        return $this->done($request, $report, $closed);
    }

    /** Rendre visible un contenu masqué (RG-16 ou décision) sans toucher au signalement. */
    public function restore(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $decide->unhide($report, $this->admin($request));

        return $this->done($request, $report, 0);
    }

    private function done(Request $request, ModerationReport $report, int $closed): JsonResponse
    {
        $report->refresh()->load(['reportedUser:id,pseudo', 'subject']);

        return $this->data(AdminReportResource::make($report)->resolve($request), 200, ['closed' => $closed]);
    }
}
