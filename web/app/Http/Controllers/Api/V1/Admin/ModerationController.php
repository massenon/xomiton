<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Moderation\DecideReport;
use App\Application\Moderation\ReportQueue;
use App\Enums\ModerationKind;
use App\Http\Resources\Admin\AdminReportResource;
use App\Models\ModerationReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Modération essentielle sur mobile (`admin/moderation/reports`, P15-16 ; `moderation.manage`) — mêmes cas d'usage que
 * l'écran `/admin/moderation` (DecideReport, ReportQueue) : file des signalements (une ligne par contenu, nombre de
 * signalements et de personnes), détail d'un signalement, **masquer** le contenu, le **rendre visible** (restaurer),
 * **ignorer** le signalement (« conserver ») ou **avertir** l'auteur·e (motif obligatoire) ; `decision` réunit masquer /
 * conserver / avertir. Suspendre un compte reste sur le web. **Les messages privés** (kind `message`) ne sont ni listés
 * ni lisibles ni décidables ici (404) : ils se traitent sur le web. Une décision vaut pour tous les signalements en attente du même contenu.
 */
final class ModerationController extends AdminApiController
{
    public function index(Request $request, ReportQueue $queue): JsonResponse
    {
        $user = $this->admin($request);
        Gate::forUser($user)->authorize('viewAny', ModerationReport::class);
        $state = $request->validate(['state' => ['sometimes', 'string', 'in:pending,handled,rejected,all']])['state'] ?? 'pending';

        $page = $queue->forState($state)->where('kind', '!=', ModerationKind::Message->value)->with(['reportedUser:id,pseudo', 'subject'])->orderByDesc('id')->paginate($this->perPage($request));
        $siblings = $queue->siblings($page->getCollection());

        return $this->page(
            $page,
            $page->getCollection()->map(static fn (ModerationReport $report): array => (new AdminReportResource($report, $siblings[$report->subject_type.':'.$report->subject_id] ?? null))->resolve($request))->values()->all(),
            ['state' => $state, 'pending' => $queue->pendingGroupsForMobile()->toBase()->count()],
        );
    }

    /** Un signalement (détail de l'écran « Contenu signalé »), avec le nombre de signalements du même contenu. */
    public function show(Request $request, ModerationReport $report, ReportQueue $queue): JsonResponse
    {
        $this->ensureMobile($report);
        Gate::forUser($this->admin($request))->authorize('view', $report);
        $report->load(['reportedUser:id,pseudo', 'subject']);
        $siblings = $queue->siblings(collect([$report]));

        return $this->data((new AdminReportResource($report, $siblings[$report->subject_type.':'.$report->subject_id] ?? null))->resolve($request));
    }

    /**
     * Décision unique de l'écran de modération (plan §11.2) : `{action: hide|keep|warn, reason?}` — `keep` conserve le
     * contenu (ignore le signalement), `hide` le masque, `warn` avertit l'auteur·e (motif obligatoire, écrit pour la personne).
     * Les mêmes cas d'usage que les routes `hide`, `dismiss` et `warn`.
     */
    public function decision(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $this->ensureMobile($report);
        $validated = $request->validate([
            'action' => ['required', 'string', 'in:hide,keep,warn'],
            'reason' => ['required_if:action,warn', 'nullable', 'string', 'min:3', 'max:'.DecideReport::REASON_MAX],
        ]);

        $decision = match ($validated['action']) {
            'hide' => DecideReport::HIDE,
            'warn' => DecideReport::WARN,
            default => DecideReport::DISMISS,
        };

        return $this->done($request, $report, $decide($report, $decision, $this->admin($request), $validated['reason'] ?? null));
    }

    /** Avertir l'auteur·e du contenu (motif obligatoire) ; la suspension du compte reste sur le web. */
    public function warn(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $this->ensureMobile($report);
        $validated = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:'.DecideReport::REASON_MAX]]);

        return $this->done($request, $report, $decide($report, DecideReport::WARN, $this->admin($request), (string) $validated['reason']));
    }

    public function hide(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $this->ensureMobile($report);
        $validated = $request->validate(['reason' => ['sometimes', 'nullable', 'string', 'max:'.DecideReport::REASON_MAX]]);
        $closed = $decide($report, DecideReport::HIDE, $this->admin($request), $validated['reason'] ?? null);

        return $this->done($request, $report, $closed);
    }

    public function dismiss(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $this->ensureMobile($report);
        $closed = $decide($report, DecideReport::DISMISS, $this->admin($request));

        return $this->done($request, $report, $closed);
    }

    /** Rendre visible un contenu masqué (RG-16 ou décision) sans toucher au signalement. */
    public function restore(Request $request, ModerationReport $report, DecideReport $decide): JsonResponse
    {
        $this->ensureMobile($report);
        $decide->unhide($report, $this->admin($request));

        return $this->done($request, $report, 0);
    }

    /** Les messages privés ne sont jamais proposés à la modération mobile (P15-24) : 404 neutre, comme un identifiant inconnu. */
    private function ensureMobile(ModerationReport $report): void
    {
        abort_if($report->kind === ModerationKind::Message, 404);
    }

    private function done(Request $request, ModerationReport $report, int $closed): JsonResponse
    {
        $report->refresh()->load(['reportedUser:id,pseudo', 'subject']);

        return $this->data(AdminReportResource::make($report)->resolve($request), 200, ['closed' => $closed]);
    }
}
