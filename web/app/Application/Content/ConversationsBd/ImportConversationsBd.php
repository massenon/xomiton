<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Application\Admin\Content\PublicContentCache;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Models\BdBubble;
use App\Models\BdCharacter;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Models\BdScene;
use App\Support\MediaUrl;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Import idempotent des 12 conversations modèles (P20-05, PRD P0-9 / P0-10 ; mêmes principes que l'import P19
 * ImportViolencesNumeriques). Rejouable sans doublon : chaque ligne est retrouvée par sa clé naturelle.
 *
 *   - personnages (`bd_characters.key`) : textes réalignés à chaque exécution, actif à la création seulement ;
 *   - scènes (`bd_scenes.key`) : image, description, personnages et zone réalignés tant que l'administration ne les a
 *     pas retouchées (`content_edited_at`) ; active à la création seulement ;
 *   - conversations (`bd_conversations.model_key` m01…m12) : textes, cases et bulles réalignés tant que l'administration
 *     n'a retouché ni les textes ni les bulles (`content_edited_at`), sinon laissées telles quelles ;
 *   - **statut** : à la création, **publiée** seulement si les images de toutes ses cases existent
 *     (`public/assets/images/conversations-bd/<slug>/case-N.webp`), sinon **brouillon** ; un brouillon créé par l'import
 *     est publié plus tard, quand ses images arrivent, tant que l'administration n'a jamais changé son statut
 *     (`status_changed_at`) ; jamais dépubliée, jamais republiée après un retrait.
 *
 * Chaque modèle passe d'abord la grammaire des bulles (ConversationGrammar) : une erreur arrête l'import avant toute
 * écriture (RuntimeException), comme un fichier mal formé.
 */
final class ImportConversationsBd
{
    public function __construct(
        private readonly ConversationsBdSource $source,
        private readonly ConversationGrammar $grammar,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @return array<string, int> personnages, scènes, conversations, publiées, brouillons, créations, mises à jour, laissées
     */
    public function __invoke(): array
    {
        $characters = $this->source->characters();
        $scenes = $this->source->scenes();
        $models = $this->source->models();
        $sceneKeys = array_column($scenes, 'key');

        foreach ($models as $model) {
            $this->assertValid($model, $sceneKeys);
        }

        $report = ['characters' => 0, 'scenes' => 0, 'conversations' => 0, 'published' => 0, 'drafts' => 0, 'created' => 0, 'updated' => 0, 'kept' => 0];

        DB::transaction(function () use ($characters, $scenes, $models, &$report): void {
            foreach ($characters as $position => $row) {
                $this->upsertCharacter($row, $position + 1, $report);
            }

            $sceneIds = [];
            foreach ($scenes as $position => $row) {
                $scene = $this->upsertScene($row, $position + 1, $report);
                $sceneIds[$row['key']] = $scene;
            }

            foreach ($models as $rank => $model) {
                $this->upsertConversation($model, $rank + 1, $sceneIds, $report);
            }
        });

        $this->cache->flush();

        return $report;
    }

    /**
     * Bulle au format de la grammaire (§6) à partir d'une ligne de fichier ou d'un formulaire.
     *
     * @param  array<string, mixed>  $row
     * @return array{type: string, personnage: string|null, texte: string, emphase: list<string>, position: array{x: float, y: float}, queue: array{vers: string, x: float|null, y: float|null}|null, associee_a: int|null, superpose: bool, masque: bool}
     */
    public static function bubble(array $row): array
    {
        $position = is_array($row['position'] ?? null) ? $row['position'] : [];
        $tail = is_array($row['queue'] ?? null) ? $row['queue'] : null;
        $character = $row['personnage'] ?? null;

        return [
            'type' => (string) ($row['type'] ?? ''),
            'personnage' => is_string($character) && $character !== '' ? $character : null,
            'texte' => trim((string) ($row['texte'] ?? '')),
            'emphase' => array_values(array_filter((array) ($row['emphase'] ?? []), static fn (mixed $e): bool => is_string($e) && trim($e) !== '')),
            'position' => ['x' => (float) ($position['x'] ?? 0), 'y' => (float) ($position['y'] ?? 0)],
            'queue' => $tail === null ? null : [
                'vers' => (string) ($tail['vers'] ?? ''),
                'x' => is_numeric($tail['x'] ?? null) ? (float) $tail['x'] : null,
                'y' => is_numeric($tail['y'] ?? null) ? (float) $tail['y'] : null,
            ],
            'associee_a' => is_int($row['associee_a'] ?? null) ? $row['associee_a'] : null,
            'superpose' => (bool) ($row['superpose'] ?? false),
            'masque' => (bool) ($row['masque'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  list<string>  $sceneKeys
     */
    private function assertValid(array $model, array $sceneKeys): void
    {
        $panels = [];

        foreach ((array) $model['cases'] as $n => $case) {
            if (! is_array($case) || ! in_array($case['scene'] ?? null, $sceneKeys, true)) {
                throw new RuntimeException(sprintf('%s, case %d : scène absente de _scenes.json.', (string) $model['key'], (int) $n + 1));
            }

            $panels[] = ['bubbles' => array_map(self::bubble(...), array_values(array_filter((array) ($case['bulles'] ?? []), 'is_array')))];
        }

        $errors = ConversationGrammar::errors($this->grammar->check(['panels' => $panels]));

        if ($errors !== []) {
            throw new RuntimeException((string) $model['key'].' : '.$errors[0]['message']);
        }
    }

    /**
     * @param  array{key: string, name: string, pronoun: string|null, role: string|null, description: string|null, secondary: bool}  $row
     * @param  array<string, int>  $report
     */
    private function upsertCharacter(array $row, int $position, array &$report): void
    {
        $character = BdCharacter::query()->firstOrNew(['key' => $row['key']]);
        $creating = ! $character->exists;
        $character->fill([
            'name' => $row['name'],
            'pronoun' => $row['pronoun'],
            'role' => $row['role'],
            'description' => $row['description'],
            'is_secondary' => $row['secondary'],
            'position' => $position,
        ]);

        if ($creating) {
            $character->is_active = true;
        }

        $character->save();
        $report['characters']++;
    }

    /**
     * @param  array{key: string, title: string|null, image: string|null, description: string, characters: list<string>, zone: string|null}  $row
     * @param  array<string, int>  $report
     */
    private function upsertScene(array $row, int $position, array &$report): BdScene
    {
        $scene = BdScene::query()->firstOrNew(['key' => $row['key']]);
        $creating = ! $scene->exists;
        $report['scenes']++;

        if (! $creating && $scene->content_edited_at !== null) {
            return $scene;
        }

        $scene->fill([
            'title' => $row['title'],
            'image_path' => $row['image'],
            'description' => $row['description'],
            'characters' => $row['characters'],
            'bubble_zone' => $row['zone'],
            'position' => $position,
        ]);

        if ($creating) {
            $scene->forceFill(['origin' => 'modele', 'is_active' => true]);
        }

        $scene->save();

        return $scene;
    }

    /**
     * @param  array<string, mixed>  $model
     * @param  array<string, BdScene>  $scenes
     * @param  array<string, int>  $report
     */
    private function upsertConversation(array $model, int $rank, array $scenes, array &$report): void
    {
        $key = (string) $model['key'];
        $conversation = BdConversation::query()->firstOrNew(['model_key' => $key]);
        $creating = ! $conversation->exists;
        $report['conversations']++;
        $cases = array_values(array_filter((array) $model['cases'], 'is_array'));
        $complete = $this->imagesExist($cases, $scenes);

        if ($creating || $conversation->content_edited_at === null) {
            $conversation->fill([
                'slug' => (string) $model['slug'],
                'title' => trim((string) $model['title']),
                'summary' => $this->text($model['summary'] ?? null),
                'theme' => $this->text($model['theme'] ?? null),
                'category' => $this->text($model['category'] ?? null),
                'audience' => $this->text($model['audience'] ?? null),
                'key_message' => $this->text($model['key_message'] ?? null),
                'content_warning' => $this->text($model['content_warning'] ?? null),
                'discussion_questions' => array_values(array_filter(array_map(static fn (mixed $q): string => is_string($q) ? trim($q) : '', (array) ($model['questions'] ?? [])))),
            ]);
        }

        if ($creating) {
            // `ulid` posé ici : l'import tourne aussi sans événements de modèle (DatabaseSeeder, WithoutModelEvents).
            $conversation->forceFill([
                'ulid' => (string) Str::ulid(),
                'origin' => BdConversationOrigin::Template,
                'is_anonymous' => true,
                'position' => $rank,
                'status' => $complete ? BdConversationStatus::Published : BdConversationStatus::Draft,
                'published_at' => $complete ? now() : null,
            ]);
        } elseif ($complete && $conversation->statusValue() === BdConversationStatus::Draft && $conversation->status_changed_at === null) {
            // Images arrivées depuis l'import précédent : le brouillon de l'import est publié.
            $conversation->forceFill(['status' => BdConversationStatus::Published, 'published_at' => now()]);
        }

        $edited = ! $creating && $conversation->content_edited_at !== null;
        $conversation->save();

        if (! $edited) {
            $this->syncPanels($conversation, $cases, $scenes);
        }

        $report[$creating ? 'created' : ($edited ? 'kept' : 'updated')]++;
        $report[$conversation->isPublished() ? 'published' : 'drafts']++;
    }

    /**
     * Cases réalignées par rang (mises à jour sur place, en trop supprimées) ; bulles réécrites.
     *
     * @param  list<array<string, mixed>>  $cases
     * @param  array<string, BdScene>  $scenes
     */
    private function syncPanels(BdConversation $conversation, array $cases, array $scenes): void
    {
        $existing = BdPanel::query()->where('conversation_id', $conversation->getKey())->orderBy('position')->orderBy('id')->get()->values();

        foreach ($cases as $index => $case) {
            $scene = $scenes[(string) $case['scene']];
            $panel = $existing[$index] ?? new BdPanel(['conversation_id' => $conversation->getKey()]);
            $panel->fill([
                'scene_id' => $scene->getKey(),
                'position' => $index + 1,
                'scene_description' => $this->text($case['description'] ?? null) ?? $scene->description,
            ])->save();

            BdBubble::query()->where('panel_id', $panel->getKey())->delete();

            foreach (array_values(array_filter((array) ($case['bulles'] ?? []), 'is_array')) as $order => $row) {
                $bubble = self::bubble($row);
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
        }

        foreach ($existing->slice(count($cases)) as $extra) {
            $extra->delete();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $cases
     * @param  array<string, BdScene>  $scenes
     */
    private function imagesExist(array $cases, array $scenes): bool
    {
        foreach ($cases as $case) {
            $scene = $scenes[(string) ($case['scene'] ?? '')] ?? null;

            if ($scene === null || ! MediaUrl::exists($scene->image_path)) {
                return false;
            }
        }

        return $cases !== [];
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
