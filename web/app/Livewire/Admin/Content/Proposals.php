<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content;

use App\Application\Content\Proposals\ReviewResourceProposal;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ProposalStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\ResourceProposal;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * File de validation des ressources proposées par les membres (P18-12, `/admin/contenu/propositions`) : tableau filtré par
 * statut (« Proposées » par défaut), modale de relecture (résumé, lien, PDF par lien signé de 10 minutes, crédit
 * souhaité), « Accepter » (ressource en brouillon dans /admin/contenu, crédit facultatif) et « Refuser » (motif
 * obligatoire, envoyé à l'auteur·rice). `resources.manage` revérifiée à chaque action ; la proposition ouverte est
 * verrouillée (`#[Locked]`).
 */
final class Proposals extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    #[SafeUrl(as: 'st', except: 'proposee')]
    public string $status = 'proposee';

    #[Locked]
    public ?int $proposalId = null;

    public bool $credit = true;

    public string $reason = '';

    public function mount(): void
    {
        $this->authorizeManage();

        if (ProposalStatus::tryFrom($this->status) === null && $this->status !== '') {
            $this->status = ProposalStatus::Proposed->value;
        }
    }

    protected function sortableColumns(): array
    {
        return ['created' => 'created_at', 'title' => 'title'];
    }

    protected function searchableColumns(): array
    {
        return ['title'];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    #[Computed]
    public function opened(): ?ResourceProposal
    {
        return $this->proposalId === null ? null : ResourceProposal::query()->with(['user:id,pseudo', 'category:id,label'])->find($this->proposalId);
    }

    public function open(int $id): void
    {
        $this->authorizeManage();
        $this->proposalId = (int) ResourceProposal::query()->findOrFail($id)->getKey();
        $this->credit = true;
        $this->reason = '';
        $this->resetErrorBag();
        unset($this->opened);
        $this->openModal('xmProposal');
    }

    public function accept(ReviewResourceProposal $review): void
    {
        $this->authorizeManage();
        $proposal = $this->openedOrFail();

        try {
            $resource = $review->accept($proposal, $this->actor(), $this->credit);
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal('xmProposal');
        $this->toast((string) __('admin_content.proposals.toasts.accepted', ['title' => $resource->title]));
        $this->reset('proposalId', 'reason');
        unset($this->opened);
    }

    public function refuse(ReviewResourceProposal $review): void
    {
        $this->authorizeManage();
        $proposal = $this->openedOrFail();
        $this->validate(
            ['reason' => ['required', 'string', 'min:5', 'max:'.ReviewResourceProposal::REASON_MAX]],
            [],
            ['reason' => (string) __('admin_content.proposals.fields.reason')],
        );

        try {
            $review->refuse($proposal, $this->reason, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        $this->closeModal('xmProposal');
        $this->toast((string) __('admin_content.proposals.toasts.refused'), 'warning');
        $this->reset('proposalId', 'reason');
        unset($this->opened);
    }

    public function render(ReviewResourceProposal $review): View
    {
        $query = ResourceProposal::query()->with(['user:id,pseudo', 'category:id,label'])
            ->when($this->status !== '', fn (Builder $q) => $q->where('status', $this->status));
        [$sortKey, $sortDirection] = $this->currentSort();
        $opened = $this->opened();

        return view('livewire.admin.content.proposals', [
            'rows' => $this->paginateQuery($query),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'fileUrl' => $opened !== null ? $review->fileUrl($opened) : null,
            'pendingCount' => ResourceProposal::query()->pending()->count(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_content.proposals.title'),
            'adminPage' => 'contenu',
        ]);
    }

    private function openedOrFail(): ResourceProposal
    {
        $proposal = $this->opened();
        abort_if($proposal === null, 404);

        return $proposal;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('resources.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
