<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Content\ConversationsBd\ConversationPdf;
use App\Application\Content\ConversationsBd\MemberConversations;
use App\Enums\BdConversationOrigin;
use App\Http\Controllers\Controller;
use App\Models\BdConversation;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * « Mes conversations en BD » (P20-07, P20-08, P20-09), pages Inertia du gabarit membre chargées à la demande (noindex,
 * titre d'onglet neutre si la personne l'a choisi, comme toute page membre) :
 *  - `/mes-conversations-bd` → `Member/ConversationsBd/Index` : mes créations et leur statut, créer, proposer, retirer,
 *    supprimer, exporter ;
 *  - `/mes-conversations-bd/{ulid}` → `Member/ConversationsBd/Editor` : éditeur (bibliothèque de scènes, bulles,
 *    enregistrement automatique), lecture seule et exports quand la création n'est pas modifiable ;
 *  - `/mes-conversations-bd/{ulid}/pdf` : export PDF d'une création, publiée ou non, pour son auteur·rice et pour la
 *    modération (`moderation.manage`) seulement — jamais public tant que la création n'est pas publiée.
 * Sans `social.access` : 403, comme l'API. Création d'une autre personne : 404.
 */
final class ConversationsBdPageController extends Controller
{
    public function __construct(private readonly MemberConversations $conversations) {}

    public function index(Request $request): Response
    {
        $user = $this->member($request);
        $title = (string) __('conversations_bd.member.title');

        return Inertia::render('Member/ConversationsBd/Index', [
            'title' => $title,
            'conversations' => $this->conversations->mine($user)->map(fn (BdConversation $conversation): array => $this->conversations->present($conversation, false))->values()->all(),
            'limits' => ['max' => MemberConversations::MAX_PER_AUTHOR, 'title_min' => MemberConversations::TITLE_MIN, 'title_max' => MemberConversations::TITLE_MAX, 'summary_max' => MemberConversations::SUMMARY_MAX],
        ])->withViewData(['title' => $title]);
    }

    public function edit(Request $request, string $ulid): Response
    {
        $user = $this->member($request);
        $conversation = $this->own($ulid, $user);
        $title = (string) __('conversations_bd.editor.title');

        return Inertia::render('Member/ConversationsBd/Editor', [
            'title' => $title,
            'conversation' => $this->conversations->present($conversation),
            'library' => $this->conversations->library(),
        ])->withViewData(['title' => $title]);
    }

    public function pdf(Request $request, string $ulid, ConversationPdf $pdf): HttpResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $conversation = BdConversation::query()->where('ulid', $ulid)->where('origin', BdConversationOrigin::Member->value)->first();
        abort_if($conversation === null || ! ($conversation->isAuthoredBy($user) || $user->can('moderation.manage')), 404);

        return response($pdf->render($conversation), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.ConversationPdf::filename($conversation).'"',
            'Cache-Control' => 'private, no-store',
            'X-Robots-Tag' => 'noindex, nofollow',
        ]);
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('social.access'), 403);

        return $user;
    }

    private function own(string $ulid, User $user): BdConversation
    {
        $conversation = BdConversation::query()->where('ulid', $ulid)->where('origin', BdConversationOrigin::Member->value)
            ->where('author_id', $user->getKey())->first();
        abort_if($conversation === null, 404);

        return $conversation;
    }
}
