<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Content;

use App\Application\Content\ConversationsBd\MemberConversations;
use App\Application\Moderation\ReportContent;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Enums\ContentReportReason;
use App\Http\Controllers\Api\V1\ApiController;
use App\Models\BdConversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Conversations en BD des membres (P20-07, P20-08 ; docs/API.md §11 ter) — éditeur de l'espace membre, réutilisable par
 * l'application. Exige `social.access` (comme les autres contributions des membres). Une création d'une autre personne,
 * un modèle de l'équipe ou une adresse inconnue : même 404.
 *
 *  - `GET bd-conversations/library` : scènes actives (image, description, casting), personnages, types, queues, limites ;
 *  - `GET bd-conversations` : « Mes conversations » (statut, dates, adresse publique si publiée) ;
 *  - `POST bd-conversations` `{title, summary?}` → 201, brouillon privé ;
 *  - `GET|PUT|DELETE bd-conversations/{ulid}` : lire, enregistrer (enregistrement automatique : titre, résumé, cases,
 *    bulles ; réponse `{data: {saved_at, issues}}`), supprimer (brouillon, refusée ou retirée) ;
 *  - `POST bd-conversations/{ulid}/submit` `{show_pseudonym: bool}` : proposer à la publication (anonyme par défaut) ;
 *  - `POST bd-conversations/{ulid}/withdraw` : retirer (proposée ou publiée) ;
 *  - `POST bd-conversations/{ulid}/report` `{reason, details?}` : signaler une création publiée (motifs du projet).
 * Écritures sous le limiteur `bd_editor` (signalement : `content_report`). Refus métier : 422 BdConversationRefused.
 */
final class BdConversationController extends ApiController
{
    public function __construct(private readonly MemberConversations $conversations) {}

    public function library(Request $request): JsonResponse
    {
        $this->member($request);

        return $this->data($this->conversations->library());
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->member($request);

        return $this->data($this->conversations->mine($user)->map(fn (BdConversation $conversation): array => $this->conversations->present($conversation, false))->values()->all());
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->member($request);
        $conversation = $this->conversations->create($user, $request->only(['title', 'summary']));

        return $this->created($this->conversations->present($conversation), route('mes-conversations-bd.edit', ['ulid' => $conversation->ulid]));
    }

    public function show(Request $request, string $ulid): JsonResponse
    {
        $user = $this->member($request);

        return $this->data($this->conversations->present($this->own($ulid, $user)));
    }

    public function update(Request $request, string $ulid): JsonResponse
    {
        $user = $this->member($request);
        $conversation = $this->own($ulid, $user);
        $issues = $this->conversations->save($conversation, $request->only(['title', 'summary', 'panels']), $user);

        return $this->data([
            'saved_at' => $conversation->refresh()->updated_at?->toIso8601String(),
            'issues' => $issues,
        ]);
    }

    public function destroy(Request $request, string $ulid): JsonResponse
    {
        $user = $this->member($request);
        $this->conversations->delete($this->own($ulid, $user), $user);

        return $this->data(['deleted' => true]);
    }

    public function submit(Request $request, string $ulid): JsonResponse
    {
        $user = $this->member($request);
        $data = $request->validate(['show_pseudonym' => ['sometimes', 'boolean']]);
        $conversation = $this->conversations->submit($this->own($ulid, $user), $user, (bool) ($data['show_pseudonym'] ?? false));

        return $this->data($this->conversations->present($conversation, false));
    }

    public function withdraw(Request $request, string $ulid): JsonResponse
    {
        $user = $this->member($request);
        $conversation = $this->conversations->withdraw($this->own($ulid, $user), $user);

        return $this->data($this->conversations->present($conversation, false));
    }

    public function report(Request $request, string $ulid, ReportContent $reports): JsonResponse
    {
        $user = $this->member($request);
        $conversation = BdConversation::query()->where('ulid', $ulid)->where('origin', BdConversationOrigin::Member->value)
            ->where('status', BdConversationStatus::Published->value)->first();
        abort_if($conversation === null, 404);
        abort_if($conversation->isAuthoredBy($user), 403, (string) __('pages/conversations_bd.report.own'));

        /** @var array{reason: string, details?: string|null} $data */
        $data = $request->validate([
            'reason' => ['required', 'string', Rule::in(ContentReportReason::selectable())],
            'details' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);
        $reason = ContentReportReason::from($data['reason']);
        $outcome = $reports->byMember($user, $conversation, $reason, $data['details'] ?? null);

        return $this->data(['reported' => true, 'reason' => $reason->value], $outcome->created ? 201 : 200);
    }

    private function member(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless($user->can('social.access'), 403);

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
