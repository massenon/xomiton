<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Ccc;

use App\Application\Admin\Content\ManageConversationsBd;
use App\Application\Content\ConversationsBd\ConversationGrammar;
use App\Application\Content\ConversationsBd\ConversationsBdCatalog;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\BdConversationOrigin;
use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\BdCharacter;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Models\BdScene;
use App\Models\User;
use App\Support\MediaUrl;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Onglet « Conversations en BD » de `/admin/ccc` (P20, PRD P0-9 ; composant enfant de Admin\Ccc\Index) : liste
 * (statut, cases, images manquantes), publier / dépublier, ordonner (monter / descendre), modifier les textes d'une
 * conversation, la description et les bulles d'une case (formulaire simple : type, personnage, texte, emphase, position
 * x / y en %, queue, bulle associée, superposée, masquée) avec aperçu de la case et contrôles de la grammaire en direct ;
 * bibliothèque de scènes (description, active / inactive, image présente ou non).
 * Permission `conversations_bd.manage` (admin, superadmin) revérifiée à chaque action ; identifiants verrouillés ;
 * chaque écriture est journalisée par ManageConversationsBd.
 */
final class Conversations extends Component
{
    use InteractsWithUi;

    public const SECTIONS = ['conversations', 'scenes'];

    public const BUBBLES_MAX = 8;

    public string $section = 'conversations';

    /** Conversation ouverte dans l'éditeur (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $editingId = null;

    /** Case ouverte dans l'éditeur de bulles. */
    #[Locked]
    public ?int $panelId = null;

    /** Scène dont la description est en cours de modification. */
    #[Locked]
    public ?int $sceneId = null;

    public string $title = '';

    public string $summary = '';

    public string $theme = '';

    public string $category = '';

    public string $audience = '';

    public string $keyMessage = '';

    public string $contentWarning = '';

    /** Questions d'animation, une par ligne. */
    public string $questions = '';

    public string $sceneDescription = '';

    /**
     * Bulles de la case ouverte : lignes `{type, personnage, texte, emphase, x, y, vers, tx, ty, associee, superpose, masque}`.
     *
     * @var array<int, mixed>
     */
    public array $bubbles = [];

    public string $sceneText = '';

    public string $announce = '';

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function setSection(string $section): void
    {
        if (in_array($section, self::SECTIONS, true)) {
            $this->section = $section;
            $this->closeEditor();
        }
    }

    public function toggle(int $id, ManageConversationsBd $manage): void
    {
        $conversation = $this->conversation($id);
        $publish = ! $conversation->isPublished();

        try {
            $manage->setPublished($conversation, $publish, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->conversations);
        $this->toast((string) __($publish ? 'admin_conversations_bd.toasts.published' : 'admin_conversations_bd.toasts.unpublished', ['title' => $conversation->title]), $publish ? 'success' : 'warning');
    }

    public function move(int $id, int $direction, ManageConversationsBd $manage): void
    {
        $conversation = $this->conversation($id);

        try {
            $manage->move($conversation, $direction, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'warning');

            return;
        }

        unset($this->conversations);
        $this->announce = (string) __($direction < 0 ? 'admin_conversations_bd.moved_up' : 'admin_conversations_bd.moved_down', ['title' => $conversation->title]);
    }

    public function edit(int $id): void
    {
        $conversation = $this->conversation($id);
        $this->resetErrorBag();
        $this->editingId = (int) $conversation->getKey();
        $this->panelId = null;
        $this->bubbles = [];
        $this->title = (string) $conversation->title;
        $this->summary = (string) $conversation->summary;
        $this->theme = (string) $conversation->theme;
        $this->category = (string) $conversation->category;
        $this->audience = (string) $conversation->audience;
        $this->keyMessage = (string) $conversation->key_message;
        $this->contentWarning = (string) $conversation->content_warning;
        $this->questions = implode("\n", $conversation->questions());
    }

    public function closeEditor(): void
    {
        $this->editingId = null;
        $this->panelId = null;
        $this->sceneId = null;
        $this->bubbles = [];
        $this->resetErrorBag();
    }

    public function saveTexts(ManageConversationsBd $manage): void
    {
        $conversation = $this->editing() ?? abort(404);
        $text = ['string', 'not_regex:/<\/?[a-z!][^>]*>/i'];
        $this->validate([
            'title' => ['required', ...$text, 'max:'.ManageConversationsBd::TITLE_MAX],
            'summary' => ['nullable', ...$text, 'max:'.ManageConversationsBd::TEXT_MAX],
            'theme' => ['nullable', ...$text, 'max:'.ManageConversationsBd::SHORT_MAX],
            'category' => ['nullable', ...$text, 'max:120'],
            'audience' => ['nullable', ...$text, 'max:'.ManageConversationsBd::SHORT_MAX],
            'keyMessage' => ['nullable', ...$text, 'max:'.ManageConversationsBd::TEXT_MAX],
            'contentWarning' => ['nullable', ...$text, 'max:'.ManageConversationsBd::TEXT_MAX],
            'questions' => ['nullable', ...$text, 'max:2000'],
        ], ['not_regex' => (string) __('admin_conversations_bd.no_markup')], $this->attributeNames());

        $questions = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/u', $this->questions) ?: []))), 0, ManageConversationsBd::QUESTIONS_MAX);
        $manage->updateTexts($conversation, [
            'title' => $this->title,
            'summary' => $this->summary,
            'theme' => $this->theme,
            'category' => $this->category,
            'audience' => $this->audience,
            'key_message' => $this->keyMessage,
            'content_warning' => $this->contentWarning,
            'questions' => $questions,
        ], $this->actor());

        unset($this->conversations, $this->editing);
        $this->toast((string) __('admin_conversations_bd.toasts.saved', ['title' => $conversation->title]));
    }

    public function openPanel(int $panelId): void
    {
        $conversation = $this->editing() ?? abort(404);
        $panel = BdPanel::query()->where('conversation_id', $conversation->getKey())->with('bubbles', 'scene')->findOrFail($panelId);
        $this->resetErrorBag();
        $this->panelId = (int) $panel->getKey();
        $this->sceneDescription = (string) ($panel->scene_description ?: $panel->scene?->description);
        $this->bubbles = [];

        foreach ($panel->bubbles as $bubble) {
            $grammar = $bubble->toGrammar();
            $this->bubbles[] = [
                'type' => $grammar['type'],
                'personnage' => (string) $grammar['personnage'],
                'texte' => $grammar['texte'],
                'emphase' => implode(', ', $grammar['emphase']),
                'x' => (string) $grammar['position']['x'],
                'y' => (string) $grammar['position']['y'],
                'vers' => (string) ($grammar['queue']['vers'] ?? ''),
                'tx' => isset($grammar['queue']['x']) ? (string) $grammar['queue']['x'] : '',
                'ty' => isset($grammar['queue']['y']) ? (string) $grammar['queue']['y'] : '',
                'associee' => $grammar['associee_a'] === null ? '' : (string) ($grammar['associee_a'] + 1),
                'superpose' => $grammar['superpose'],
                'masque' => $grammar['masque'],
            ];
        }
    }

    public function addBubble(): void
    {
        $this->authorizeManage();

        if ($this->panelId === null || count($this->bubbles) >= self::BUBBLES_MAX) {
            return;
        }

        $this->bubbles[] = ['type' => 'parole', 'personnage' => '', 'texte' => '', 'emphase' => '', 'x' => '4', 'y' => '4', 'vers' => 'personnage', 'tx' => '25', 'ty' => '60', 'associee' => '', 'superpose' => false, 'masque' => false];
    }

    public function removeBubble(int $index): void
    {
        $this->authorizeManage();

        if (isset($this->bubbles[$index])) {
            unset($this->bubbles[$index]);
            $this->bubbles = array_values($this->bubbles);
        }
    }

    public function savePanel(ManageConversationsBd $manage): void
    {
        $this->editing() ?? abort(404);
        $panel = $this->openedPanel() ?? abort(404);
        $this->validate($this->bubbleRules(), ['not_regex' => (string) __('admin_conversations_bd.no_markup')], $this->attributeNames());

        try {
            $manage->updatePanel($panel, $this->sceneDescription, $this->formBubbles(), $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('bubbles', $refused->userMessage());
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->conversations, $this->editing);
        $this->toast((string) __('admin_conversations_bd.toasts.panel_saved', ['n' => $panel->position]));
    }

    public function editScene(int $id): void
    {
        $scene = $this->scene($id);
        $this->sceneId = (int) $scene->getKey();
        $this->sceneText = (string) $scene->description;
        $this->resetErrorBag();
    }

    public function saveScene(ManageConversationsBd $manage): void
    {
        $scene = $this->sceneId === null ? abort(404) : $this->scene($this->sceneId);
        $this->validate(['sceneText' => ['required', 'string', 'not_regex:/<\/?[a-z!][^>]*>/i', 'max:'.ManageConversationsBd::TEXT_MAX]], ['not_regex' => (string) __('admin_conversations_bd.no_markup')], ['sceneText' => (string) __('admin_conversations_bd.fields.scene_description')]);
        $manage->updateScene($scene, $this->sceneText, $this->actor());
        $this->sceneId = null;
        unset($this->scenes);
        $this->toast((string) __('admin_conversations_bd.toasts.scene_saved', ['key' => $scene->key]));
    }

    public function toggleScene(int $id, ManageConversationsBd $manage): void
    {
        $scene = $this->scene($id);
        $active = ! $scene->is_active;
        $manage->setSceneActive($scene, $active, $this->actor());
        unset($this->scenes);
        $this->toast((string) __($active ? 'admin_conversations_bd.toasts.scene_on' : 'admin_conversations_bd.toasts.scene_off', ['key' => $scene->key]), $active ? 'success' : 'warning');
    }

    /** @return Collection<int, BdConversation> */
    #[Computed]
    public function conversations(): Collection
    {
        // Modèles de l'équipe seulement : les créations des membres se relisent dans /admin/moderation/conversations-bd (P20-08).
        return BdConversation::query()->where('origin', BdConversationOrigin::Template->value)->ordered()->with('panels.scene')->withCount('panels')->get();
    }

    /** Nombre de cases dont l'image manque encore (P20-11). */
    private static function missingImages(BdConversation $conversation): int
    {
        return $conversation->panels->filter(static fn (BdPanel $panel): bool => ! MediaUrl::exists($panel->scene?->image_path))->count();
    }

    #[Computed]
    public function editing(): ?BdConversation
    {
        return $this->editingId === null ? null : BdConversation::query()->with('panels.scene')->find($this->editingId);
    }

    public function openedPanel(): ?BdPanel
    {
        $conversation = $this->editing();

        return $conversation === null || $this->panelId === null ? null : BdPanel::query()->where('conversation_id', $conversation->getKey())->with('scene')->find($this->panelId);
    }

    /** @return Collection<int, BdScene> */
    #[Computed]
    public function scenes(): Collection
    {
        return $this->section === 'scenes' ? BdScene::query()->ordered()->get() : new Collection;
    }

    /** @return array<string, string> clé => nom */
    #[Computed]
    public function characters(): array
    {
        /** @var array<string, string> $names */
        $names = BdCharacter::query()->ordered()->pluck('name', 'key')->map(static fn (mixed $name): string => (string) $name)->all();

        return $names;
    }

    /**
     * Aperçu de la case ouverte (bulles du formulaire) et contrôles de la grammaire sur toute la conversation.
     *
     * @return array{bubbles: list<array<string, mixed>>, image: string|null, issues: list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>}
     */
    private function preview(ManageConversationsBd $manage, ConversationsBdCatalog $catalog): array
    {
        $conversation = $this->editing();
        $panel = $this->openedPanel();

        if ($conversation === null || $panel === null) {
            return ['bubbles' => [], 'image' => null, 'issues' => []];
        }

        $bubbles = array_map(ImportConversationsBd::bubble(...), $this->formBubbles());
        $issues = (new ConversationGrammar)->check(['panels' => $manage->grammarPanels($conversation, [(int) $panel->getKey() => $bubbles])]);

        return ['bubbles' => $bubbles, 'image' => $catalog->image($panel), 'issues' => $issues];
    }

    public function render(): View
    {
        return view('livewire.admin.ccc.conversations', [
            'preview' => $this->preview(app(ManageConversationsBd::class), app(ConversationsBdCatalog::class)),
            'missing' => $this->section === 'conversations' ? $this->conversations->mapWithKeys(static fn (BdConversation $c): array => [(int) $c->getKey() => self::missingImages($c)])->all() : [],
            'types' => BubbleType::cases(),
            'targets' => BubbleTailTarget::cases(),
        ]);
    }

    /**
     * Bulles du formulaire au format de la grammaire (§6), relues ligne à ligne (la saisie vient du navigateur).
     *
     * @return list<array<string, mixed>>
     */
    private function formBubbles(): array
    {
        $out = [];

        foreach (array_values($this->bubbles) as $row) {
            if (! is_array($row)) {
                continue;
            }

            $type = self::field($row, 'type');
            $target = self::field($row, 'vers');
            $link = self::field($row, 'associee');
            $out[] = [
                'type' => $type,
                'personnage' => self::field($row, 'personnage'),
                'texte' => self::field($row, 'texte'),
                'emphase' => array_values(array_filter(array_map('trim', explode(',', self::field($row, 'emphase'))))),
                'position' => ['x' => (float) str_replace(',', '.', self::field($row, 'x')), 'y' => (float) str_replace(',', '.', self::field($row, 'y'))],
                'queue' => $type === BubbleType::Caption->value || $target === '' ? null : [
                    'vers' => $target,
                    'x' => is_numeric(str_replace(',', '.', self::field($row, 'tx'))) ? (float) str_replace(',', '.', self::field($row, 'tx')) : null,
                    'y' => is_numeric(str_replace(',', '.', self::field($row, 'ty'))) ? (float) str_replace(',', '.', self::field($row, 'ty')) : null,
                ],
                'associee_a' => ctype_digit($link) && (int) $link >= 1 ? (int) $link - 1 : null,
                'superpose' => (bool) ($row['superpose'] ?? false),
                'masque' => (bool) ($row['masque'] ?? false),
            ];
        }

        return $out;
    }

    /** @return array<string, list<mixed>> */
    private function bubbleRules(): array
    {
        $text = ['string', 'not_regex:/<\/?[a-z!][^>]*>/i'];
        $number = ['nullable', 'numeric', 'between:-10,110'];

        return [
            'sceneDescription' => ['nullable', ...$text, 'max:'.ManageConversationsBd::TEXT_MAX],
            'bubbles' => ['array', 'max:'.self::BUBBLES_MAX],
            'bubbles.*.type' => ['required', 'in:'.implode(',', BubbleType::values())],
            'bubbles.*.personnage' => ['nullable', 'string', 'max:40'],
            'bubbles.*.texte' => ['nullable', ...$text, 'max:300'],
            'bubbles.*.emphase' => ['nullable', ...$text, 'max:120'],
            'bubbles.*.x' => ['required', 'numeric', 'between:-10,110'],
            'bubbles.*.y' => ['required', 'numeric', 'between:-10,110'],
            'bubbles.*.vers' => ['nullable', 'in:'.implode(',', BubbleTailTarget::values())],
            'bubbles.*.tx' => $number,
            'bubbles.*.ty' => $number,
            'bubbles.*.associee' => ['nullable', 'integer', 'min:1', 'max:'.self::BUBBLES_MAX],
            'bubbles.*.superpose' => ['boolean'],
            'bubbles.*.masque' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return [
            'title' => (string) __('admin_conversations_bd.fields.title'),
            'summary' => (string) __('admin_conversations_bd.fields.summary'),
            'theme' => (string) __('admin_conversations_bd.fields.theme'),
            'category' => (string) __('admin_conversations_bd.fields.category'),
            'audience' => (string) __('admin_conversations_bd.fields.audience'),
            'keyMessage' => (string) __('admin_conversations_bd.fields.key_message'),
            'contentWarning' => (string) __('admin_conversations_bd.fields.content_warning'),
            'questions' => (string) __('admin_conversations_bd.fields.questions'),
            'sceneDescription' => (string) __('admin_conversations_bd.fields.scene_description'),
            'bubbles.*.texte' => (string) __('admin_conversations_bd.fields.text'),
            'bubbles.*.x' => (string) __('admin_conversations_bd.fields.x'),
            'bubbles.*.y' => (string) __('admin_conversations_bd.fields.y'),
        ];
    }

    /** @param  array<mixed>  $row */
    private static function field(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return is_string($value) || is_int($value) || is_float($value) ? trim((string) $value) : '';
    }

    private function conversation(int $id): BdConversation
    {
        $this->authorizeManage();

        return BdConversation::query()->where('origin', BdConversationOrigin::Template->value)->findOrFail($id);
    }

    private function scene(int $id): BdScene
    {
        $this->authorizeManage();

        return BdScene::query()->findOrFail($id);
    }

    /** La permission est revérifiée à chaque action : un rôle retiré garde l'écran ouvert, pas le droit d'écrire. */
    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('conversations_bd.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
