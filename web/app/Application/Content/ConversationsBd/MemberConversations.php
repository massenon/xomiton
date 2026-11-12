<?php

declare(strict_types=1);

namespace App\Application\Content\ConversationsBd;

use App\Application\Admin\Content\PublicContentCache;
use App\Domain\Content\Exceptions\BdConversationRefused;
use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Enums\BubbleTailTarget;
use App\Enums\BubbleType;
use App\Models\BdBubble;
use App\Models\BdCharacter;
use App\Models\BdConversation;
use App\Models\BdPanel;
use App\Models\BdScene;
use App\Models\User;
use App\Support\Audit;
use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Créations des membres (P20-07, P20-08 ; PRD conversation-en-bd P0-6, P0-7) : un seul service pour l'API
 * (`/api/v1/bd-conversations`) et la page Vue de l'éditeur.
 *
 *  - créer : brouillon **privé** (titre, résumé facultatif), origine `membre`, anonyme par défaut (Q99), adresse privée
 *    par ULID, slug provisoire jamais servi (seules les conversations publiées existent pour le public) ;
 *  - enregistrer (enregistrement automatique de l'éditeur) : titre, résumé, 0 à 8 cases choisies dans la **bibliothèque
 *    de scènes actives** (une scène devenue inactive reste permise dans une case qui l'utilisait déjà), bulles au format
 *    de la grammaire (§6) — personnage parmi le casting de la scène, aucun pour un cartouche (narration), jamais de bulle
 *    masquée ni d'image : **aucun import**. Filtres de contenu (ConversationContentFilter) à chaque enregistrement : un
 *    texte refusé n'est pas enregistré (422, message par champ). La grammaire (ConversationGrammar) est rendue avec la
 *    réponse sans bloquer le brouillon (une conversation en cours peut avoir moins de 3 cases) ;
 *  - proposer : filtres et grammaire sans erreur exigés (dont « au moins une bulle »), choix « Afficher mon pseudonyme »
 *    (décoché par défaut → anonyme), statut `proposee` ; jamais public avant la décision de la modération ;
 *  - retirer : à tout moment depuis `proposee` ou `publiee` → `retiree`, dépublication immédiate (plan du site oublié) ;
 *  - supprimer : un brouillon, une création refusée ou retirée.
 * Journal `conversations_bd.member_*` : titre seulement, jamais le texte des bulles.
 */
final class MemberConversations
{
    public const TITLE_MIN = 3;

    public const TITLE_MAX = 120;

    public const SUMMARY_MAX = 300;

    public const TEXT_MAX = 240;

    public const MAX_BUBBLES_HARD = 8;

    /** Créations gardées par compte (brouillons compris). */
    public const MAX_PER_AUTHOR = 50;

    public function __construct(
        private readonly ConversationGrammar $grammar,
        private readonly ConversationContentFilter $filter,
        private readonly PublicContentCache $cache,
    ) {}

    /** @return Collection<int, BdConversation> */
    public function mine(User $author): Collection
    {
        return BdConversation::query()->where('origin', BdConversationOrigin::Member->value)->where('author_id', $author->getKey())
            ->withCount('panels')->orderByDesc('updated_at')->orderByDesc('id')->get();
    }

    /**
     * @param  array<string, mixed>  $input  `title`, `summary`
     *
     * @throws BdConversationRefused
     */
    public function create(User $author, array $input): BdConversation
    {
        $data = $this->validate($input, false);

        if (BdConversation::query()->where('origin', BdConversationOrigin::Member->value)->where('author_id', $author->getKey())->count() >= self::MAX_PER_AUTHOR) {
            throw new BdConversationRefused('bd_too_many', [], [], ['max' => self::MAX_PER_AUTHOR]);
        }

        $this->assertClean(['title' => $data['title'], 'summary' => $data['summary']]);
        $ulid = (string) Str::ulid();

        $conversation = new BdConversation;
        $conversation->forceFill([
            'ulid' => $ulid,
            'slug' => 'brouillon-'.strtolower($ulid),
            'origin' => BdConversationOrigin::Member->value,
            'author_id' => $author->getKey(),
            'is_anonymous' => true,
            'title' => $data['title'],
            'summary' => $data['summary'],
            'status' => BdConversationStatus::Draft->value,
            'position' => 0,
        ])->save();

        Audit::log('conversations_bd.member_created', $conversation, $conversation->title, $author);

        return $conversation;
    }

    /**
     * Enregistre le brouillon (titre, résumé, cases et bulles), remplacées en entier dans l'ordre donné.
     *
     * @param  array<string, mixed>  $input
     * @return list<array{level: string, code: string, panel: int|null, bubble: int|null, message: string}> contrôles de la grammaire
     *
     * @throws BdConversationRefused
     */
    public function save(BdConversation $conversation, array $input, User $author): array
    {
        $this->assertOwnedEditable($conversation, $author);
        $data = $this->validate($input, true);
        $scenes = $this->scenesFor($conversation, $data['panels']);
        $panels = $this->normalizePanels($data['panels'], $scenes);
        $this->assertClean($this->textFields($data['title'], $data['summary'], $panels));

        DB::transaction(function () use ($conversation, $data, $panels, $scenes): void {
            $conversation->forceFill(['title' => $data['title'], 'summary' => $data['summary']])->save();
            BdPanel::query()->where('conversation_id', $conversation->getKey())->delete();

            foreach ($panels as $p => $panel) {
                $row = BdPanel::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'scene_id' => $scenes[$panel['scene']]->getKey(),
                    'position' => $p + 1,
                ]);

                foreach ($panel['bubbles'] as $b => $bubble) {
                    BdBubble::query()->create([
                        'panel_id' => $row->getKey(),
                        'position' => $b + 1,
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
                        'is_masked' => false,
                    ]);
                }
            }

            // Toujours toucher la date : « Enregistré à … » et tri de « Mes conversations ».
            $conversation->touch();
        });

        return $this->grammar->check(['panels' => array_map(static fn (array $panel): array => ['bubbles' => $panel['bubbles']], $panels)]);
    }

    /**
     * @throws BdConversationRefused
     */
    public function submit(BdConversation $conversation, User $author, bool $showPseudonym): BdConversation
    {
        $this->assertOwned($conversation, $author);

        if (! $conversation->isEditable()) {
            throw new BdConversationRefused('bd_not_submittable');
        }

        $panels = $this->grammarPanels($conversation);
        $this->assertClean($this->textFields((string) $conversation->title, $conversation->summary, $panels));
        $errors = ConversationGrammar::errors($this->grammar->check(['panels' => $panels]));

        if ($errors !== []) {
            throw new BdConversationRefused('bd_invalid', [], $errors, ['message' => $errors[0]['message']]);
        }

        $conversation->forceFill([
            'status' => BdConversationStatus::Submitted->value,
            'is_anonymous' => ! $showPseudonym,
            'submitted_at' => now(),
            'refusal_reason' => null,
            'reviewed_at' => null,
            'reviewed_by' => null,
            'withdrawn_at' => null,
            'withdrawn_by' => null,
        ])->save();

        Audit::log('conversations_bd.member_submitted', $conversation, $conversation->title.' ; '.($showPseudonym ? 'pseudonyme' : 'anonyme'), $author);

        return $conversation;
    }

    /**
     * @throws BdConversationRefused
     */
    public function withdraw(BdConversation $conversation, User $author): BdConversation
    {
        $this->assertOwned($conversation, $author);
        $status = $conversation->statusValue();

        if ($status !== BdConversationStatus::Submitted && $status !== BdConversationStatus::Published) {
            throw new BdConversationRefused('bd_not_withdrawable');
        }

        $conversation->forceFill([
            'status' => BdConversationStatus::Withdrawn->value,
            'withdrawn_at' => now(),
            'withdrawn_by' => $author->getKey(),
        ])->save();

        Audit::log('conversations_bd.member_withdrawn', $conversation, $conversation->title, $author);

        if ($status === BdConversationStatus::Published) {
            $this->cache->flush();
        }

        return $conversation;
    }

    /**
     * @throws BdConversationRefused
     */
    public function delete(BdConversation $conversation, User $author): void
    {
        $this->assertOwnedEditable($conversation, $author);
        Audit::log('conversations_bd.member_deleted', $conversation, $conversation->title, $author);
        $conversation->delete();
    }

    /**
     * Création pour l'éditeur et l'API.
     *
     * @return array<string, mixed>
     */
    public function present(BdConversation $conversation, bool $withPanels = true): array
    {
        $status = $conversation->statusValue() ?? BdConversationStatus::Draft;
        $data = [
            'ulid' => (string) $conversation->ulid,
            'title' => (string) $conversation->title,
            'summary' => (string) ($conversation->summary ?? ''),
            'status' => $status->value,
            'status_label' => $status->label(),
            'editable' => $conversation->isEditable(),
            'show_pseudonym' => ! $conversation->is_anonymous,
            'refusal_reason' => $status === BdConversationStatus::Refused ? (string) $conversation->refusal_reason : null,
            'panels_count' => (int) ($conversation->getAttribute('panels_count') ?? $conversation->panels()->count()),
            'updated_at' => $conversation->updated_at?->toIso8601String(),
            'submitted_at' => self::iso($conversation->getAttribute('submitted_at')),
            'published_at' => $status === BdConversationStatus::Published ? self::iso($conversation->getAttribute('published_at')) : null,
            'public_url' => $status === BdConversationStatus::Published ? route('conversations-bd.show', ['slug' => $conversation->slug]) : null,
            'editor_url' => route('mes-conversations-bd.edit', ['ulid' => $conversation->ulid]),
            'pdf_url' => route('mes-conversations-bd.pdf', ['ulid' => $conversation->ulid]),
        ];

        if ($withPanels) {
            $data['panels'] = [];

            foreach ($conversation->panels()->with(['scene', 'bubbles'])->get() as $panel) {
                $data['panels'][] = [
                    'scene' => ($scene = $panel->getRelationValue('scene')) instanceof BdScene ? (string) $scene->key : '',
                    'bubbles' => $panel->bubbles->map(static fn (BdBubble $bubble): array => $bubble->toGrammar())->values()->all(),
                ];
            }

            $data['issues'] = $this->grammar->check(['panels' => $this->grammarPanels($conversation)]);
        }

        return $data;
    }

    /**
     * Bibliothèque de scènes actives (illustrations validées, sans texte) avec leur casting, et limites de l'éditeur.
     *
     * @return array{scenes: list<array<string, mixed>>, characters: array<string, string>, types: list<array{value: string, label: string}>, tails: list<array{value: string, label: string}>, limits: array<string, int>}
     */
    public function library(): array
    {
        $names = BdCharacter::names();

        return [
            'scenes' => BdScene::query()->active()->ordered()->get()->map(static function (BdScene $scene) use ($names): array {
                $characters = array_values(array_filter((array) $scene->characters, static fn (mixed $key): bool => is_string($key) && $key !== ''));

                return [
                    'key' => (string) $scene->key,
                    'title' => (string) ($scene->title ?? $scene->key),
                    'image' => MediaUrl::exists($scene->image_path) ? MediaUrl::resolve($scene->image_path, '') : null,
                    'description' => (string) $scene->description,
                    'zone' => (string) ($scene->bubble_zone ?? ''),
                    'characters' => array_map(static fn (string $key): array => ['key' => $key, 'name' => $names[$key] ?? Str::ucfirst($key)], $characters),
                ];
            })->values()->all(),
            'characters' => $names,
            'types' => array_map(static fn (BubbleType $type): array => ['value' => $type->value, 'label' => $type->label()], BubbleType::cases()),
            'tails' => array_map(static fn (BubbleTailTarget $tail): array => ['value' => $tail->value, 'label' => (string) __('conversations_bd.tail_targets.'.$tail->value)], BubbleTailTarget::cases()),
            'limits' => [
                'title_min' => self::TITLE_MIN,
                'title_max' => self::TITLE_MAX,
                'summary_max' => self::SUMMARY_MAX,
                'text_max' => self::TEXT_MAX,
                'words_max' => ConversationGrammar::MAX_WORDS,
                'bubbles_max' => ConversationGrammar::MAX_BUBBLES,
                'panels_min' => ConversationGrammar::MIN_PANELS,
                'panels_max' => ConversationGrammar::MAX_PANELS,
            ],
        ];
    }

    /**
     * Cases au format de la grammaire, d'après la base.
     *
     * @return list<array{bubbles: list<array<string, mixed>>}>
     */
    public function grammarPanels(BdConversation $conversation): array
    {
        return BdPanel::query()->where('conversation_id', $conversation->getKey())->orderBy('position')->orderBy('id')->with('bubbles')->get()
            ->map(static fn (BdPanel $panel): array => ['bubbles' => $panel->bubbles->map(static fn (BdBubble $bubble): array => $bubble->toGrammar())->values()->all()])
            ->values()->all();
    }

    // --- Intérieur ---------------------------------------------------------------------------------------------------

    /**
     * @throws BdConversationRefused
     */
    private function assertOwned(BdConversation $conversation, User $author): void
    {
        // Création d'une autre personne ou modèle de l'équipe : même réponse qu'une adresse inconnue.
        abort_unless($conversation->isMemberCreation() && $conversation->isAuthoredBy($author), 404);
    }

    /**
     * @throws BdConversationRefused
     */
    private function assertOwnedEditable(BdConversation $conversation, User $author): void
    {
        $this->assertOwned($conversation, $author);

        if (! $conversation->isEditable()) {
            throw new BdConversationRefused('bd_not_editable');
        }
    }

    /**
     * @param  array<string, string|null>  $fields
     *
     * @throws BdConversationRefused
     */
    private function assertClean(array $fields): void
    {
        $errors = $this->filter->check($fields);

        if ($errors !== []) {
            throw new BdConversationRefused('bd_content_rejected', $errors, [], ['message' => (string) (reset($errors)[0] ?? '')]);
        }
    }

    /**
     * @param  list<array{bubbles: list<array<string, mixed>>}>  $panels
     * @return array<string, string|null>
     */
    private function textFields(string $title, ?string $summary, array $panels): array
    {
        $fields = ['title' => $title, 'summary' => $summary];

        foreach ($panels as $p => $panel) {
            foreach ($panel['bubbles'] as $b => $bubble) {
                $fields['panels.'.$p.'.bubbles.'.$b.'.texte'] = (string) ($bubble['texte'] ?? '');

                foreach ((array) ($bubble['emphase'] ?? []) as $e => $phrase) {
                    $fields['panels.'.$p.'.bubbles.'.$b.'.emphase.'.$e] = is_string($phrase) ? $phrase : '';
                }
            }
        }

        return $fields;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{title: string, summary: string|null, panels: list<array<string, mixed>>}
     *
     * @throws BdConversationRefused
     */
    private function validate(array $input, bool $withPanels): array
    {
        $rules = [
            'title' => ['required', 'string', 'min:'.self::TITLE_MIN, 'max:'.self::TITLE_MAX],
            'summary' => ['nullable', 'string', 'max:'.self::SUMMARY_MAX],
        ];

        if ($withPanels) {
            $rules += [
                'panels' => ['present', 'array', 'max:'.ConversationGrammar::MAX_PANELS],
                'panels.*' => ['array'],
                'panels.*.scene' => ['required', 'string', 'max:60'],
                'panels.*.bubbles' => ['present', 'array', 'max:'.self::MAX_BUBBLES_HARD],
                'panels.*.bubbles.*' => ['array'],
                'panels.*.bubbles.*.type' => ['required', 'string', Rule::in(BubbleType::values())],
                'panels.*.bubbles.*.personnage' => ['nullable', 'string', 'max:40'],
                'panels.*.bubbles.*.texte' => ['nullable', 'string', 'max:'.self::TEXT_MAX],
                'panels.*.bubbles.*.emphase' => ['nullable', 'array', 'max:5'],
                'panels.*.bubbles.*.emphase.*' => ['string', 'max:60'],
                'panels.*.bubbles.*.position' => ['required', 'array'],
                'panels.*.bubbles.*.position.x' => ['required', 'numeric', 'between:-10,110'],
                'panels.*.bubbles.*.position.y' => ['required', 'numeric', 'between:-10,110'],
                'panels.*.bubbles.*.queue' => ['nullable', 'array'],
                'panels.*.bubbles.*.queue.vers' => ['required_with:panels.*.bubbles.*.queue', 'string', Rule::in(BubbleTailTarget::values())],
                'panels.*.bubbles.*.queue.x' => ['nullable', 'numeric', 'between:0,100'],
                'panels.*.bubbles.*.queue.y' => ['nullable', 'numeric', 'between:0,100'],
                'panels.*.bubbles.*.associee_a' => ['nullable', 'integer', 'min:0'],
                'panels.*.bubbles.*.superpose' => ['nullable', 'boolean'],
            ];
        }

        $validator = Validator::make($input, $rules, [], [
            'title' => (string) __('conversations_bd.editor.fields.title'),
            'summary' => (string) __('conversations_bd.editor.fields.summary'),
            'panels' => (string) __('conversations_bd.editor.fields.panels'),
        ]);

        if ($validator->fails()) {
            /** @var array<string, list<string>> $errors */
            $errors = $validator->errors()->toArray();

            throw new BdConversationRefused('bd_invalid_input', $errors, [], ['message' => (string) $validator->errors()->first()]);
        }

        /** @var array{title: string, summary?: string|null, panels?: list<array<string, mixed>>} $valid */
        $valid = $validator->validated();
        $summary = trim((string) ($valid['summary'] ?? ''));

        return [
            'title' => trim($valid['title']),
            'summary' => $summary === '' ? null : $summary,
            'panels' => array_values((array) ($valid['panels'] ?? [])),
        ];
    }

    /**
     * Scènes des cases : actives, ou déjà utilisées par cette conversation (scène désactivée depuis).
     *
     * @param  list<array<string, mixed>>  $panels
     * @return array<string, BdScene>
     *
     * @throws BdConversationRefused
     */
    private function scenesFor(BdConversation $conversation, array $panels): array
    {
        $keys = array_values(array_unique(array_map(static fn (array $panel): string => (string) ($panel['scene'] ?? ''), $panels)));
        $used = BdPanel::query()->where('conversation_id', $conversation->getKey())->whereNotNull('scene_id')->pluck('scene_id')->all();
        $scenes = BdScene::query()->whereIn('key', $keys)
            ->where(static fn ($query) => $query->where('is_active', true)->orWhereIn('id', $used))
            ->get()->keyBy('key')->all();
        $errors = [];

        foreach ($panels as $p => $panel) {
            if (! isset($scenes[(string) ($panel['scene'] ?? '')])) {
                $errors['panels.'.$p.'.scene'] = [(string) __('conversations_bd.errors.scene_unknown')];
            }
        }

        if ($errors !== []) {
            throw new BdConversationRefused('bd_invalid_input', $errors, [], ['message' => (string) __('conversations_bd.errors.scene_unknown')]);
        }

        /** @var array<string, BdScene> $scenes */
        return $scenes;
    }

    /**
     * Bulles normalisées (format de la grammaire) ; personnage contrôlé contre le casting de la scène.
     *
     * @param  list<array<string, mixed>>  $panels
     * @param  array<string, BdScene>  $scenes
     * @return list<array{scene: string, bubbles: list<array{type: string, personnage: string|null, texte: string, emphase: list<string>, position: array{x: float, y: float}, queue: array{vers: string, x: float|null, y: float|null}|null, associee_a: int|null, superpose: bool, masque: bool}>}>
     *
     * @throws BdConversationRefused
     */
    private function normalizePanels(array $panels, array $scenes): array
    {
        $out = [];
        $errors = [];

        foreach ($panels as $p => $panel) {
            $scene = $scenes[(string) $panel['scene']];
            $cast = array_values(array_filter((array) $scene->characters, 'is_string'));
            $bubbles = [];

            foreach (array_values((array) ($panel['bubbles'] ?? [])) as $b => $row) {
                $bubble = ImportConversationsBd::bubble((array) $row);
                $bubble['masque'] = false;

                if ($bubble['type'] === BubbleType::Caption->value) {
                    $bubble['personnage'] = null;
                    $bubble['queue'] = null;
                } elseif ($bubble['personnage'] !== null && ! in_array($bubble['personnage'], $cast, true)) {
                    $errors['panels.'.$p.'.bubbles.'.$b.'.personnage'] = [(string) __('conversations_bd.errors.character_unknown')];
                } elseif ($bubble['personnage'] === null && $cast !== []) {
                    $errors['panels.'.$p.'.bubbles.'.$b.'.personnage'] = [(string) __('conversations_bd.errors.character_required')];
                }

                $bubble['position'] = ['x' => round($bubble['position']['x'], 2), 'y' => round($bubble['position']['y'], 2)];

                if ($bubble['queue'] !== null) {
                    $bubble['queue']['x'] = $bubble['queue']['x'] === null ? null : round($bubble['queue']['x'], 2);
                    $bubble['queue']['y'] = $bubble['queue']['y'] === null ? null : round($bubble['queue']['y'], 2);
                }

                $bubbles[] = $bubble;
            }

            $out[] = ['scene' => (string) $panel['scene'], 'bubbles' => $bubbles];
        }

        if ($errors !== []) {
            throw new BdConversationRefused('bd_invalid_input', $errors, [], ['message' => (string) (reset($errors)[0] ?? '')]);
        }

        return $out;
    }

    private static function iso(mixed $value): ?string
    {
        return $value instanceof \DateTimeInterface ? $value->format(\DateTimeInterface::ATOM) : null;
    }
}
