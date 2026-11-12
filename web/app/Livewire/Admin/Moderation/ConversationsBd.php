<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Moderation;

use App\Application\Content\ConversationsBd\ConversationGrammar;
use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Application\Content\ConversationsBd\MemberConversations;
use App\Application\Content\ConversationsBd\ReviewMemberConversation;
use App\Domain\Content\Exceptions\BdConversationRefused;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\BdConversation;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Conversations en BD à relire `/admin/moderation/conversations-bd` (P20-08, PRD P0-7) : file des créations proposées
 * par les membres, **invisibles du public tant qu'elles ne sont pas publiées**, les plus anciennes d'abord. Placée dans la
 * modération (et non dans l'onglet « Conversations en BD » de `/admin/ccc`) parce que c'est une relecture avant parution
 * d'un contenu de membre, comme les vidéos (Q96) : les modérateur·rices y ont accès (`moderation.manage`), sans la
 * permission éditoriale `conversations_bd.manage`, et les signalements des créations publiées se traitent à côté.
 *
 * Chaque carte : aperçu rendu (cases et bulles, composant `x-bd.panel`, transcription), titre, résumé, auteur·rice (la
 * modération le voit même pour une création anonyme) et choix d'affichage, date de proposition, contrôles de la
 * grammaire, PDF. Actions : Publier (slug unique, page publique et plan du site) ou Refuser (motif obligatoire écrit pour
 * l'auteur·rice). Jamais sa propre création. `moderation.manage` à l'ouverture et à chaque action ; identifiant `#[Locked]`.
 */
final class ConversationsBd extends Component
{
    use InteractsWithUi;
    use WithPagination;

    public const PER_PAGE = 5;

    #[Locked]
    public ?int $conversationId = null;

    /** `publish` | `refuse` */
    #[Locked]
    public string $decision = '';

    public string $reason = '';

    public function mount(): void
    {
        Gate::authorize('moderation.manage');
    }

    /** @return array{pending: int, published: int, refused: int} */
    #[Computed]
    public function stats(): array
    {
        $count = static fn (BdConversationStatus $status): int => BdConversation::query()->where('origin', BdConversationOrigin::Member->value)->where('status', $status->value)->count();

        return [
            'pending' => $count(BdConversationStatus::Submitted),
            'published' => $count(BdConversationStatus::Published),
            'refused' => $count(BdConversationStatus::Refused),
        ];
    }

    public function render(ConversationsBdCatalog $catalog, MemberConversations $conversations, ConversationGrammar $grammar): View
    {
        Gate::authorize('moderation.manage');
        $actor = $this->actor();

        /** @var LengthAwarePaginator<int, BdConversation> $page */
        $page = BdConversation::query()->where('origin', BdConversationOrigin::Member->value)
            ->where('status', BdConversationStatus::Submitted->value)
            ->with(['author:id,pseudo', 'panels.scene', 'panels.bubbles'])
            ->orderBy('submitted_at')->orderBy('id')->paginate(self::PER_PAGE);

        $rows = $page->getCollection()->map(static fn (BdConversation $conversation): array => [
            'conversation' => $conversation,
            'panels' => $catalog->panels($conversation),
            'issues' => $grammar->check(['panels' => $conversations->grammarPanels($conversation)]),
            'own' => $conversation->isAuthoredBy($actor),
        ])->all();

        return view('livewire.admin.moderation.conversations-bd', [
            'rows' => $rows,
            'paginator' => $page,
            'names' => $catalog->names(),
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'reasonMax' => ReviewMemberConversation::REASON_MAX,
        ])->layout('components.layouts.admin', [
            'title' => __('admin.moderation.bd.title'),
            'adminPage' => 'moderation',
        ]);
    }

    public function openDecision(int $id, string $decision): void
    {
        Gate::authorize('moderation.manage');
        $conversation = $this->pending($id);

        if ($conversation === null || ! in_array($decision, ['publish', 'refuse'], true) || $conversation->isAuthoredBy($this->actor())) {
            return;
        }

        $this->conversationId = (int) $conversation->getKey();
        $this->decision = $decision;
        $this->reason = '';
        $this->resetErrorBag();
        $this->openModal('xmBdDecide');
    }

    public function closeDecision(): void
    {
        $this->reset('conversationId', 'decision', 'reason');
        $this->resetErrorBag();
    }

    public function publish(ReviewMemberConversation $review): void
    {
        Gate::authorize('moderation.manage');

        if ($this->attempt(fn () => $review->publish($this->current(), $this->actor()))) {
            $this->finish('published');
        }
    }

    public function refuse(ReviewMemberConversation $review): void
    {
        Gate::authorize('moderation.manage');
        $this->validate(['reason' => ['required', 'string', 'max:'.ReviewMemberConversation::REASON_MAX]], [], ['reason' => (string) __('admin.moderation.bd.reason')]);

        if ($this->attempt(fn () => $review->refuse($this->current(), $this->reason, $this->actor()))) {
            $this->finish('refused');
        }
    }

    private function finish(string $outcome): void
    {
        $this->closeModal('xmBdDecide');
        $this->closeDecision();
        unset($this->stats);
        $this->resetPage();
        $this->toast((string) __('admin.moderation.bd.toasts.'.$outcome), $outcome === 'published' ? 'success' : 'info');
    }

    private function current(): BdConversation
    {
        abort_if($this->conversationId === null, 404);
        $conversation = BdConversation::query()->find($this->conversationId);
        abort_if($conversation === null, 404);

        return $conversation;
    }

    private function pending(int $id): ?BdConversation
    {
        return BdConversation::query()->whereKey($id)->where('origin', BdConversationOrigin::Member->value)
            ->where('status', BdConversationStatus::Submitted->value)->first();
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function attempt(callable $action): bool
    {
        try {
            $action();
        } catch (BdConversationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');
            $this->closeModal('xmBdDecide');
            $this->closeDecision();
            unset($this->stats);

            return false;
        }

        return true;
    }
}
