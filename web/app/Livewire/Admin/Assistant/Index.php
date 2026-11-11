<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Assistant;

use App\Application\Admin\Assistant\AssistantSettings;
use App\Application\Admin\Assistant\AssistantStats;
use App\Application\Admin\Assistant\ManageAssistantKb;
use App\Application\Admin\Assistant\ReviewAssistantConversations;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Livewire\Concerns\WithDataTable;
use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\User;
use App\Support\LocaleFormatter;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Assistant LLM `/admin/assistant` (P12-07 ; prototype admin/assistant.php) : état du fournisseur (interrupteur, pilote
 * effectif), consigne système, garde-fous et escalade, base de connaissances (tableau, modale), conversations signalées
 * (lecture journalisée, « traitée ») et statistiques. Le fournisseur LLM reste derrière l'interface AssistantProvider
 * (Q7 non tranchée : seul le pilote `kb` est livré) ; les garde-fous de crise intégrés s'appliquent toujours. Permission
 * `assistant_kb.manage` (admin, superadmin). Aucune conversation n'est affichée sans action explicite.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithDataTable;

    #[SafeUrl(as: 'etat', except: '')]
    public string $kbState = '';

    public string $prompt = '';

    /** @var array<string, bool> */
    public array $guardrails = [];

    public bool $escalationOn = true;

    public string $escalationKeywords = '';

    public string $escalationTarget = 'focal';

    #[Locked]
    public ?int $kbId = null;

    /** @var array<string, mixed> */
    public array $kbForm = [];

    #[Locked]
    public ?int $readingId = null;

    public function mount(): void
    {
        abort_unless($this->actor()->can('assistant_kb.manage'), 403);
        $this->loadSettings();
        $this->resetKbForm();
    }

    private function loadSettings(): void
    {
        $this->prompt = AssistantSettings::prompt();
        $this->guardrails = AssistantSettings::guardrails();
        $escalation = AssistantSettings::escalation();
        $this->escalationOn = $escalation['on'];
        $this->escalationKeywords = $escalation['keywords'];
        $this->escalationTarget = $escalation['target'];
    }

    // --- WithDataTable (base de connaissances) -----------------------------------------------------------------------

    protected function sortableColumns(): array
    {
        return ['question' => 'question', 'hits' => 'hits_count', 'updated' => 'updated_at'];
    }

    protected function searchableColumns(): array
    {
        return ['question', 'answer'];
    }

    protected function defaultSort(): array
    {
        return ['question', 'asc'];
    }

    protected function filterProperties(): array
    {
        return ['kbState'];
    }

    // --- Interrupteur, consigne, garde-fous --------------------------------------------------------------------------

    public function setEnabled(bool $enabled, AssistantSettings $settings): void
    {
        $settings->setEnabled($enabled, $this->actor());
        unset($this->state);
        $this->toast((string) __($enabled ? 'admin_assistant.toasts.enabled' : 'admin_assistant.toasts.disabled'), $enabled ? 'success' : 'warning');
    }

    public function savePrompt(AssistantSettings $settings): void
    {
        $this->validate([
            'prompt' => ['required', 'string', 'min:'.AssistantSettings::PROMPT_MIN, 'max:'.AssistantSettings::PROMPT_MAX],
        ], [
            'prompt.min' => (string) __('admin_assistant.errors.prompt_min', ['min' => AssistantSettings::PROMPT_MIN]),
            'prompt.max' => (string) __('admin_assistant.errors.prompt_max', ['max' => AssistantSettings::PROMPT_MAX]),
        ], ['prompt' => (string) __('admin_assistant.prompt.label')]);

        $settings->savePrompt($this->prompt, $this->actor());
        $this->toast((string) __('admin_assistant.toasts.prompt_saved'));
    }

    public function resetPrompt(AssistantSettings $settings): void
    {
        $this->prompt = $settings->resetPrompt($this->actor());
        $this->resetErrorBag('prompt');
        $this->toast((string) __('admin_assistant.toasts.prompt_reset'));
    }

    public function saveGuardrails(AssistantSettings $settings): void
    {
        $this->validate([
            'escalationKeywords' => ['nullable', 'string', 'max:'.AssistantSettings::KEYWORDS_MAX],
            'escalationTarget' => ['required', 'in:'.implode(',', AssistantSettings::TARGETS)],
        ], [], [
            'escalationKeywords' => (string) __('admin_assistant.guardrails.keywords'),
            'escalationTarget' => (string) __('admin_assistant.guardrails.target'),
        ]);

        // Une règle sur une valeur vide n'est pas évaluée : le contrôle « mots-clés obligatoires » est explicite.
        if ($this->escalationOn && trim($this->escalationKeywords) === '') {
            $this->addError('escalationKeywords', (string) __('admin_assistant.errors.keywords_required'));

            return;
        }

        $settings->saveGuardrails($this->guardrails, ['on' => $this->escalationOn, 'keywords' => $this->escalationKeywords, 'target' => $this->escalationTarget], $this->actor());
        $this->toast((string) __('admin_assistant.toasts.guardrails_saved'));
    }

    // --- Base de connaissances ---------------------------------------------------------------------------------------

    /** @return array<string, mixed> */
    private function blankKb(): array
    {
        return ['question' => '', 'answer' => '', 'keywords' => '', 'is_active' => true];
    }

    public function newKb(): void
    {
        $this->resetKbForm();
        $this->openModal('xmAssistantKb');
    }

    public function editKb(int $id): void
    {
        $entry = AssistantKb::query()->findOrFail($id);
        $this->resetKbForm();
        $this->kbId = (int) $entry->getKey();
        $this->kbForm = [
            'question' => (string) $entry->question, 'answer' => (string) $entry->answer,
            'keywords' => implode(', ', (array) $entry->keywords), 'is_active' => (bool) $entry->is_active,
        ];
        $this->openModal('xmAssistantKb');
    }

    public function resetKbForm(): void
    {
        $this->reset('kbId');
        $this->kbForm = $this->blankKb();
        $this->resetErrorBag();
    }

    public function saveKb(ManageAssistantKb $manage): void
    {
        $entry = $this->kbId === null ? null : AssistantKb::query()->findOrFail($this->kbId);

        $data = $this->validateForm('kbForm', $this->kbForm, [
            'question' => ['required', 'string', 'min:5', 'max:255'],
            'answer' => ['required', 'string', 'min:20', 'max:2000'],
            'keywords' => ['required', 'string', 'max:600', function (string $attribute, mixed $value, Closure $fail): void {
                $count = count(ManageAssistantKb::parseKeywords((string) $value));

                if ($count === 0) {
                    $fail((string) __('admin_assistant.errors.keywords_required'));
                } elseif ($count > ManageAssistantKb::KEYWORDS_MAX_COUNT) {
                    $fail((string) __('admin_assistant.errors.keywords_max', ['max' => ManageAssistantKb::KEYWORDS_MAX_COUNT]));
                }
            }],
            'is_active' => ['boolean'],
        ], (array) __('admin_assistant.kb.fields'));

        $manage->save($entry, [
            'question' => (string) $data['question'],
            'answer' => (string) $data['answer'],
            'keywords' => ManageAssistantKb::parseKeywords((string) $data['keywords']),
            'is_active' => (bool) ($data['is_active'] ?? false),
        ], $this->actor());

        $this->closeModal('xmAssistantKb');
        $this->resetKbForm();
        unset($this->summary);
        $this->toast((string) __('admin_assistant.toasts.kb_saved'));
    }

    public function toggleKb(int $id, ManageAssistantKb $manage): void
    {
        $entry = $manage->toggle(AssistantKb::query()->findOrFail($id), $this->actor());
        unset($this->summary);
        $this->toast((string) __($entry->is_active ? 'admin_assistant.toasts.kb_activated' : 'admin_assistant.toasts.kb_deactivated'));
    }

    public function deleteKb(int $id, ManageAssistantKb $manage): void
    {
        $manage->delete(AssistantKb::query()->findOrFail($id), $this->actor());
        unset($this->summary);
        $this->toast((string) __('admin_assistant.toasts.kb_deleted'), 'warning');
    }

    // --- Conversations signalées -------------------------------------------------------------------------------------

    public function openConversation(int $id, ReviewAssistantConversations $review): void
    {
        $conversation = AssistantConversation::query()->flagged()->findOrFail($id);
        $this->readingId = (int) $conversation->getKey();
        $review->read($conversation, $this->actor());
        $this->openModal('xmAssistantConversation');
    }

    public function closeConversation(): void
    {
        $this->reset('readingId');
    }

    public function resolveConversation(int $id, ReviewAssistantConversations $review): void
    {
        $review->resolve(AssistantConversation::query()->flagged()->findOrFail($id), $this->actor());
        $this->closeModal('xmAssistantConversation');
        $this->reset('readingId');
        unset($this->summary);
        $this->toast((string) __('admin_assistant.toasts.resolved'));
    }

    // --- Données -----------------------------------------------------------------------------------------------------

    /** @return array{enabled: bool, provider: string} */
    #[Computed]
    public function state(): array
    {
        return ['enabled' => AssistantSettings::enabled(), 'provider' => AssistantSettings::provider()];
    }

    /** @return array{conversations: int, flagged: int, escalated: int, answered_share: int|null, kb_active: int, kb_hits: int} */
    #[Computed]
    public function summary(): array
    {
        return app(AssistantStats::class)->summary();
    }

    /** @return Collection<int, AssistantKb> */
    #[Computed]
    public function topEntries(): Collection
    {
        return app(AssistantStats::class)->topEntries();
    }

    /** @return Collection<int, \App\Models\AssistantMessage> */
    #[Computed]
    public function reading(): Collection
    {
        if ($this->readingId === null) {
            return new Collection;
        }

        $conversation = AssistantConversation::query()->flagged()->find($this->readingId);

        return $conversation === null ? new Collection : app(ReviewAssistantConversations::class)->peek($conversation);
    }

    public function render(): View
    {
        $entries = $this->paginateQuery(AssistantKb::query()
            ->when($this->kbState === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->kbState === 'inactive', fn ($q) => $q->where('is_active', false)));
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.assistant.index', [
            'entries' => $entries,
            'flagged' => AssistantConversation::query()->flagged()->withCount('messages')->orderByDesc('updated_at')->orderByDesc('id')->paginate(5, pageName: 'signalees'),
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'promptMin' => AssistantSettings::PROMPT_MIN,
            'promptMax' => AssistantSettings::PROMPT_MAX,
        ])->layout('components.layouts.admin', [
            'title' => __('admin_assistant.title'),
            'adminPage' => 'assistant',
        ]);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
