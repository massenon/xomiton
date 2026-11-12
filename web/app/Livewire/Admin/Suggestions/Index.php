<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Suggestions;

use App\Application\Community\Suggestions\Suggestions;
use App\Enums\SuggestionCategory;
use App\Enums\SuggestionStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Suggestion;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Boîte à suggestions `/admin/suggestions` (P18-14) : tableau filtré par statut et catégorie (« Nouvelles » d'abord),
 * modale de lecture (message, statut, réponse de l'équipe envoyée en notification). Une suggestion anonyme n'affiche
 * jamais son compte. `suggestions.manage` (modération, admin, superadmin) revérifiée à chaque action ; la suggestion
 * ouverte est verrouillée (`#[Locked]`).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    #[SafeUrl(as: 'cat', except: '')]
    public string $category = '';

    #[Locked]
    public ?int $suggestionId = null;

    public string $newStatus = '';

    public string $reply = '';

    public function mount(): void
    {
        $this->authorizeManage();
    }

    protected function sortableColumns(): array
    {
        return ['created' => 'created_at'];
    }

    protected function searchableColumns(): array
    {
        return ['message'];
    }

    protected function defaultSort(): array
    {
        return ['created', 'desc'];
    }

    protected function filterProperties(): array
    {
        return ['status', 'category'];
    }

    #[Computed]
    public function opened(): ?Suggestion
    {
        return $this->suggestionId === null ? null : Suggestion::query()->with('user:id,pseudo')->find($this->suggestionId);
    }

    public function open(int $id): void
    {
        $this->authorizeManage();
        $suggestion = Suggestion::query()->findOrFail($id);
        $this->suggestionId = (int) $suggestion->getKey();
        $this->newStatus = $suggestion->suggestionStatus()->value;
        $this->reply = (string) $suggestion->reply;
        $this->resetErrorBag();
        unset($this->opened);
        $this->openModal('xmSuggestion');
    }

    public function saveStatus(Suggestions $suggestions): void
    {
        $this->authorizeManage();
        $suggestion = $this->openedOrFail();
        $status = SuggestionStatus::tryFrom($this->newStatus);
        abort_if($status === null, 422);

        if ($suggestions->setStatus($suggestion, $status, $this->actor())) {
            $this->toast((string) __('admin_suggestions.toasts.status', ['status' => $status->label()]));
        }

        unset($this->opened);
    }

    public function sendReply(Suggestions $suggestions): void
    {
        $this->authorizeManage();
        $suggestion = $this->openedOrFail();
        $this->validate(
            ['reply' => ['required', 'string', 'min:5', 'max:'.Suggestions::REPLY_MAX]],
            [],
            ['reply' => (string) __('admin_suggestions.fields.reply')],
        );
        $suggestions->reply($suggestion, $this->reply, $this->actor());
        $this->newStatus = $suggestion->suggestionStatus()->value;
        $this->toast((string) __('admin_suggestions.toasts.replied'));
        unset($this->opened);
    }

    public function render(): View
    {
        $query = Suggestion::query()->with('user:id,pseudo')
            ->when(SuggestionStatus::tryFrom($this->status) !== null, fn (Builder $q) => $q->where('status', $this->status))
            ->when(SuggestionCategory::tryFrom($this->category) !== null, fn (Builder $q) => $q->where('category', $this->category));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.suggestions.index', [
            'rows' => $this->paginateQuery($query),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'newCount' => Suggestion::query()->where('status', SuggestionStatus::New->value)->count(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_suggestions.title'),
            'adminPage' => 'suggestions',
        ]);
    }

    private function openedOrFail(): Suggestion
    {
        $suggestion = $this->opened();
        abort_if($suggestion === null, 404);

        return $suggestion;
    }

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('suggestions.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
