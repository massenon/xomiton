<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\ConversationsBd;

use App\Application\Moderation\ReportContent;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Enums\ContentReportReason;
use App\Http\Controllers\Controller;
use App\Models\BdConversation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Signaler une création publiée depuis sa page `/conversations-bd/{slug}` (P20-08, PRD P0-7) : formulaire POST + @csrf
 * (sans JavaScript), membre connecté·e, motifs du projet (ContentReportReason::selectable()), précisions ≤ 500 car. →
 * ReportContent::byMember (file de `/admin/moderation`, cloche de la modération, RG-16). Une seule fois par personne ;
 * jamais sa propre création. Limiteur `content_report`. Retour sur la page avec un message de statut.
 */
final class ConversationsBdReportController extends Controller
{
    public function __invoke(Request $request, string $slug, ReportContent $reports): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $conversation = BdConversation::query()->where('slug', $slug)->where('origin', BdConversationOrigin::Member->value)
            ->where('status', BdConversationStatus::Published->value)->first();
        abort_if($conversation === null, 404);
        abort_if($conversation->isAuthoredBy($user), 403, (string) __('pages/conversations_bd.report.own'));

        /** @var array{reason: string, details?: string|null} $data */
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(ContentReportReason::selectable())],
            'details' => ['nullable', 'string', 'max:500'],
        ], [], [
            'reason' => (string) __('pages/conversations_bd.report.reason'),
            'details' => (string) __('pages/conversations_bd.report.details'),
        ]);

        $outcome = $reports->byMember($user, $conversation, ContentReportReason::from($data['reason']), $data['details'] ?? null);

        return redirect()->to(route('conversations-bd.show', ['slug' => $slug]).'#signaler')
            ->with('bdc_report', (string) __($outcome->created ? 'pages/conversations_bd.report.sent' : 'pages/conversations_bd.report.already'));
    }
}
