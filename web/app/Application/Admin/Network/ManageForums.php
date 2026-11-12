<?php

declare(strict_types=1);

namespace App\Application\Admin\Network;

use App\Application\Community\UniqueSlug;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Models\Forum;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Gestion des forums depuis l'administration (P18-10, onglet « Gestion des forums » de /admin/reseau ; avant : seulement
 * ForumsSeeder) : créer, renommer (nom, description, icône Bootstrap Icons), réordonner (monter / descendre, positions
 * renumérotées de 0 à n−1), archiver / réactiver. Un forum archivé (`archived_at`) reste listé et lisible mais n'accepte
 * plus de nouveau sujet ; la clé courte (`f6`…) est attribuée à la création et ne change jamais (filtre `?forum=` de
 * l'API). Chaque écriture est journalisée sans texte libre (`forums.created|updated|moved|archived|restored`). La
 * permission `forums.manage` est revérifiée par le composant à chaque action.
 */
final class ManageForums
{
    public const NAME_MIN = 3;

    public const NAME_MAX = 60;

    public const DESCRIPTION_MAX = 200;

    /** Icônes proposées (Bootstrap Icons, sans « bi- »). */
    public const ICONS = [
        'chat-heart', 'chat-square-text', 'bank', 'heart-pulse', 'people', 'graph-up-arrow', 'shield-check', 'book',
        'mortarboard', 'briefcase', 'house-heart', 'flower1', 'stars', 'megaphone', 'globe2', 'lightbulb',
    ];

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.self::NAME_MIN, 'max:'.self::NAME_MAX],
            'description' => ['required', 'string', 'max:'.self::DESCRIPTION_MAX],
            'icon' => ['required', 'string', Rule::in(self::ICONS)],
        ];
    }

    /**
     * @param  array{name: string, description: string, icon: string}  $data
     */
    public function create(array $data, User $actor): Forum
    {
        $forum = DB::transaction(function () use ($data): Forum {
            $position = (int) Forum::query()->max('position') + 1;

            return Forum::query()->create([
                'key' => self::nextKey(),
                'name' => trim($data['name']),
                'slug' => UniqueSlug::for(Forum::class, $data['name'], 140),
                'description' => trim($data['description']),
                'icon' => 'bi-'.$data['icon'],
                'position' => min(255, $position),
                'topics_count' => 0,
                'posts_count' => 0,
                'is_active' => true,
            ]);
        });

        Audit::log('forums.created', $forum, 'key='.$forum->key, $actor);

        return $forum;
    }

    /**
     * @param  array{name: string, description: string, icon: string}  $data
     */
    public function update(Forum $forum, array $data, User $actor): Forum
    {
        $forum->fill([
            'name' => trim($data['name']),
            'description' => trim($data['description']),
            'icon' => 'bi-'.$data['icon'],
        ]);

        if ($forum->isDirty('name')) {
            $forum->slug = UniqueSlug::for(Forum::class, $data['name'], 140);
        }

        if ($forum->isDirty()) {
            $forum->save();
            Audit::log('forums.updated', $forum, 'key='.$forum->key, $actor);
        }

        return $forum;
    }

    /**
     * Échange le forum avec son voisin dans l'ordre d'affichage (−1 : monter, +1 : descendre) puis renumérote. Renvoie le
     * nouveau rang (0 pour le premier).
     *
     * @throws ContentRefused
     */
    public function move(Forum $forum, int $direction, User $actor): int
    {
        $target = DB::transaction(function () use ($forum, $direction): int {
            $ids = Forum::query()->lockForUpdate()->ordered()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $index = array_search((int) $forum->getKey(), $ids, true);
            $neighbour = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

            if ($index === false || $neighbour === null || ! isset($ids[$neighbour])) {
                throw new ContentRefused('forum_edge');
            }

            [$ids[$index], $ids[$neighbour]] = [$ids[$neighbour], $ids[$index]];

            foreach ($ids as $position => $id) {
                Forum::query()->whereKey($id)->update(['position' => min(255, $position)]);
            }

            return $neighbour;
        });

        Audit::log('forums.moved', $forum, 'position='.($target + 1), $actor);

        return $target;
    }

    /** Archive (vrai) ou réactive (faux) ; sans effet si l'état ne change pas. */
    public function archive(Forum $forum, bool $archived, User $actor): bool
    {
        if ($forum->isArchived() === $archived) {
            return false;
        }

        $forum->forceFill(['archived_at' => $archived ? now() : null])->save();
        Audit::log($archived ? 'forums.archived' : 'forums.restored', $forum, 'key='.$forum->key, $actor);

        return true;
    }

    /** Prochaine clé courte libre (`f6`, `f7`…), dans la continuité du prototype. */
    private static function nextKey(): string
    {
        $max = 0;

        foreach (Forum::query()->pluck('key') as $key) {
            if (is_string($key) && preg_match('/^f(\d+)$/', $key, $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }

        do {
            $key = 'f'.(++$max);
        } while (Forum::query()->where('key', $key)->exists());

        return $key;
    }
}
