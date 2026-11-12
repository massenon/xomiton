<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Application\Content\ConversationsBd\ConversationGrammar;
use App\Application\Content\ConversationsBd\ImportConversationsBd;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Models\BdBubble;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Models\BdScene;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;

/**
 * Administration des conversations en BD (P20, PRD P0-9 ; onglet « Conversations en BD » de `/admin/ccc`, permission
 * `conversations_bd.manage` vérifiée par l'écran à chaque action) : publier / dépublier, ordonner, modifier les textes
 * de la conversation, la description et les bulles d'une case (texte, type, personnage, position, queue), bibliothèque
 * de scènes (description, active / inactive).
 *
 * Chaque écriture est journalisée (`conversations_bd.*`, titre et rang seulement : jamais le texte des bulles) et pose
 * la marque lue par l'import (ImportConversationsBd) : `content_edited_at` (textes, bulles, scène) ou `status_changed_at`
 * (publication) — un import suivant ne réaligne plus ce que l'équipe a retouché. Les bulles passent la grammaire
 * (ConversationGrammar) : une erreur refuse l'enregistrement ; publier exige une conversation sans erreur.
 */
final class ManageConversationsBd
{
    public const TITLE_MAX = 160;

    public const TEXT_MAX = 600;

    public const SHORT_MAX = 200;

    public const QUESTIONS_MAX = 8;

    public function __construct(
        private readonly ConversationGrammar $grammar,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}>
     */
    public function issues(BdConversation $conversation): array
    {
        return $this->grammar->check(['panels' => $this->grammarPanels($conversation)]);
    }

    /**
     * @throws ContentRefused
     */
    public function setPublished(BdConversation $conversation, bool $publish, User $actor): BdConversation
    {
        if ($publish) {
            $errors = ConversationGrammar::errors($this->issues($conversation));
            if ($errors !== []) {
                throw new ContentRefused('bd_conversation_invalid', ['message' => $errors[0]['message']]);
            }

            $conversation->forceFill([
                'status' => BdConversationStatus::Published,
                'published_at' => $conversation->published_at ?? now(),
                'reviewed_at' => now(),
                'reviewed_by' => $actor->getKey(),
                'withdrawn_at' => null,
            ]);
        } else {
            // Un modèle redevient un brouillon ; une création de membre (lot B) est retirée.
            $member = BdConversationOrigin::tryFrom((string) $conversation->getRawOriginal('origin')) === BdConversationOrigin::Member;
            $conversation->forceFill([
                'status' => $member ? BdConversationStatus::Withdrawn : BdConversationStatus::Draft,
                'withdrawn_at' => $member ? now() : null,
            ]);
        }

        $conversation->forceFill(['status_changed_at' => now()])->save();
        Audit::log($publish ? 'conversations_bd.published' : 'conversations_bd.unpublished', $conversation, $conversation->title, $actor);
        $this->cache->flush();

        return $conversation;
    }

    /**
     * Monte (-1) ou descend (+1) une conversation ; les rangs sont renumérotés 1…n.
     *
     * @throws ContentRefused
     */
    public function move(BdConversation $conversation, int $direction, User $actor): void
    {
        $ordered = BdConversation::query()->where('origin', BdConversationOrigin::Template->value)->ordered()->get(['id', 'position'])->values();
        $index = $ordered->search(fn (BdConversation $row): bool => (int) $row->getKey() === (int) $conversation->getKey());
        $target = is_int($index) ? $index + ($direction < 0 ? -1 : 1) : -1;

        if (! is_int($index) || $target < 0 || $target >= $ordered->count()) {
            throw new ContentRefused('bd_edge');
        }

        $ids = $ordered->pluck('id')->all();
        [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

        DB::transaction(function () use ($ids): void {
            foreach ($ids as $rank => $id) {
                BdConversation::query()->whereKey($id)->update(['position' => $rank + 1]);
            }
        });

        Audit::log('conversations_bd.reordered', $conversation, $conversation->title, $actor);
        $this->cache->flush();
    }

    /**
     * @param  array{title: string, summary: string, theme: string, category: string, audience: string, key_message: string, content_warning: string, questions: list<string>}  $data
     */
    public function updateTexts(BdConversation $conversation, array $data, User $actor): BdConversation
    {
        $conversation->fill([
            'title' => trim($data['title']),
            'summary' => $this->nullable($data['summary']),
            'theme' => $this->nullable($data['theme']),
            'category' => $this->nullable($data['category']),
            'audience' => $this->nullable($data['audience']),
            'key_message' => $this->nullable($data['key_message']),
            'content_warning' => $this->nullable($data['content_warning']),
            'discussion_questions' => array_values(array_filter(array_map('trim', $data['questions']), static fn (string $q): bool => $q !== '')),
        ]);
        $conversation->forceFill(['content_edited_at' => now()])->save();
        Audit::log('conversations_bd.updated', $conversation, $conversation->title, $actor);
        $this->cache->flush();

        return $conversation;
    }

    /**
     * Description et bulles d'une case. Les bulles (format de la grammaire, §6) remplacent celles de la case, dans
     * l'ordre donné ; la conversation entière repasse la grammaire.
     *
     * @param  list<array<string, mixed>>  $bubbles
     *
     * @throws ContentRefused
     */
    public function updatePanel(BdPanel $panel, string $sceneDescription, array $bubbles, User $actor): BdPanel
    {
        $conversation = $panel->conversation()->firstOrFail();
        $normalized = array_map(ImportConversationsBd::bubble(...), $bubbles);
        $panels = $this->grammarPanels($conversation, [(int) $panel->getKey() => $normalized]);
        $errors = ConversationGrammar::errors($this->grammar->check(['panels' => $panels]));

        if ($errors !== []) {
            throw new ContentRefused('bd_conversation_invalid', ['message' => $errors[0]['message']]);
        }

        DB::transaction(function () use ($panel, $sceneDescription, $normalized, $conversation): void {
            $panel->forceFill(['scene_description' => $this->nullable($sceneDescription)])->save();
            BdBubble::query()->where('panel_id', $panel->getKey())->delete();

            foreach ($normalized as $order => $bubble) {
                BdBubble::query()->create([
                    'panel_id' => $panel->getKey(),
                    'position' => $order + 1,
                    'type' => $bubble['type'],
                    'character_key' => $bubble['personnage'],
                    'text' => $bubble['texte'],
                    'emphasis' => $bubble['emphase'] === [] ? null : $bubble['emphase'],
                    'x' => $bubble['position']['x'],
                    'y' => $bubble['position']['y'],
                    'tail_target' => $bubble['queue']['vers'] ?? null,
                    'tail_x' => $bubble['queue']['x'] ?? null,
                    'tail_y' => $bubble['queue']['y'] ?? null,
                    'linked_to' => $bubble['associee_a'],
                    'is_overlapping' => $bubble['superpose'],
                    'is_masked' => $bubble['masque'],
                ]);
            }

            $conversation->forceFill(['content_edited_at' => now()])->save();
        });

        Audit::log('conversations_bd.panel_updated', $conversation, $conversation->title.' – case '.$panel->position, $actor);
        $this->cache->flush();

        return $panel;
    }

    public function updateScene(BdScene $scene, string $description, User $actor): BdScene
    {
        $scene->forceFill(['description' => trim($description), 'content_edited_at' => now()])->save();
        Audit::log('conversations_bd.scene_updated', $scene, $scene->key, $actor);

        return $scene;
    }

    public function setSceneActive(BdScene $scene, bool $active, User $actor): BdScene
    {
        $scene->forceFill(['is_active' => $active])->save();
        Audit::log($active ? 'conversations_bd.scene_activated' : 'conversations_bd.scene_deactivated', $scene, $scene->key, $actor);

        return $scene;
    }

    /**
     * Cases de la conversation au format de la grammaire ; `$replace` substitue les bulles d'une case (identifiant).
     *
     * @param  array<int, list<array<string, mixed>>>  $replace
     * @return list<array{bubbles: list<array<string, mixed>>}>
     */
    public function grammarPanels(BdConversation $conversation, array $replace = []): array
    {
        $panels = [];

        foreach (BdPanel::query()->where('conversation_id', $conversation->getKey())->orderBy('position')->orderBy('id')->with('bubbles')->get() as $panel) {
            $panels[] = ['bubbles' => $replace[(int) $panel->getKey()] ?? $panel->bubbles->map(static fn (BdBubble $bubble): array => $bubble->toGrammar())->values()->all()];
        }

        return $panels;
    }

    private function nullable(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
