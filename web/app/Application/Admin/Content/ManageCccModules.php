<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Models\CccTool;
use App\Models\OutboundPost;
use App\Models\User;
use App\Support\Audit;
use App\Support\FrenchTypography;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Modules du manuel du pair animateur (onglet « Manuel » de `/admin/ccc`, permission `ccc_tools.manage`) : création,
 * titre, description, durée, chapitres, ordre, publié / masqué et suppression des `ccc_tools` de kind `manuel`.
 *
 * Un module est un titre, une description facultative, une durée facultative (1 à 600 minutes) et une liste ordonnée de
 * chapitres (`chapters` = textes simples de 200 caractères au plus, 1 à 30 ; l'ordre du JSON est l'ordre de lecture).
 * Ce sont des textes simples : les balises sont retirées (et refusées si elles ne laissent rien), la typographie française
 * est appliquée comme pour les lois. La clé publique `m{n}` est générée et ne change plus ; l'ordre est une liste
 * renumérotée de 1 à n au sein de la famille (255 modules au plus, `position` sur un octet). Un module sans chapitre ne se
 * publie pas et un module publié garde au moins un chapitre. La suppression est refusée tant qu'une publication sortante
 * planifiée reprend sa clé. Journal `ccc.module_*` : le titre seulement, jamais la description ni le texte des chapitres.
 */
final class ManageCccModules
{
    public const TITLE_MAX = 200;

    public const DESCRIPTION_MAX = 500;

    public const CHAPTER_MAX = 200;

    public const CHAPTERS_MAX = 30;

    public const DURATION_MIN = 1;

    public const DURATION_MAX = 600;

    /** `ccc_tools.position` est un entier non signé sur un octet. */
    public const MODULES_MAX = 255;

    public function __construct(private readonly PublicContentCache $cache) {}

    /** Vrai pour un module du manuel (les autres familles ne passent jamais par ce service). */
    public static function isModule(CccTool $tool): bool
    {
        return CccKind::tryFrom((string) $tool->getRawOriginal('kind')) === CccKind::Manual;
    }

    /**
     * Crée un module masqué et sans chapitre : clé `m{n}` suivante, dernière position de la famille.
     *
     * @throws ContentRefused
     */
    public function create(string $title, string $description, ?int $duration, User $actor): CccTool
    {
        $tool = $this->insert($this->values($title, $description, $duration));

        Audit::log('ccc.module_created', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    /** @throws ContentRefused */
    public function update(CccTool $tool, string $title, string $description, ?int $duration, User $actor): CccTool
    {
        $this->assertModule($tool);
        $tool->forceFill($this->values($title, $description, $duration))->save();
        Audit::log('ccc.module_updated', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    /**
     * Publie ou masque un module ; sans chapitre, il ne se publie pas (les lecteurs n'auraient rien à afficher).
     *
     * @throws ContentRefused
     */
    public function setPublished(CccTool $tool, bool $published, User $actor): CccTool
    {
        $this->assertModule($tool);

        $locked = DB::transaction(function () use ($tool, $published): CccTool {
            $locked = $this->lock($tool);

            if ($published && $this->chaptersOf($locked) === []) {
                throw new ContentRefused('module_empty');
            }

            $locked->forceFill(['is_published' => $published])->save();

            return $locked;
        });

        Audit::log($published ? 'ccc.module_published' : 'ccc.module_unpublished', $locked, $locked->title, $actor);
        $this->cache->flush();

        return $locked;
    }

    /**
     * Ajoute un chapitre à la fin.
     *
     * @throws ContentRefused
     */
    public function addChapter(CccTool $tool, string $text, User $actor): CccTool
    {
        $this->assertModule($tool);
        $text = $this->plainChapter($text);

        return $this->changeChapters($tool, $actor, function (array $chapters) use ($text): array {
            if (count($chapters) >= self::CHAPTERS_MAX) {
                throw new ContentRefused('chapters_max', ['max' => self::CHAPTERS_MAX]);
            }

            $chapters[] = $text;

            return $chapters;
        });
    }

    /**
     * Retire le chapitre de rang `$index` (0 pour le premier). Un module publié garde au moins un chapitre.
     *
     * @throws ContentRefused
     */
    public function removeChapter(CccTool $tool, int $index, User $actor): CccTool
    {
        $this->assertModule($tool);

        return $this->changeChapters($tool, $actor, function (array $chapters, CccTool $locked) use ($index): array {
            if (! isset($chapters[$index])) {
                throw new ContentRefused('chapter_missing');
            }

            if (count($chapters) === 1 && $locked->is_published) {
                throw new ContentRefused('last_chapter_published');
            }

            array_splice($chapters, $index, 1);

            return $chapters;
        });
    }

    /**
     * Remplace le texte de chaque chapitre, dans l'ordre des chapitres (même nombre que dans le module).
     *
     * @param  list<string>  $texts
     *
     * @throws ContentRefused
     */
    public function saveChapters(CccTool $tool, array $texts, User $actor): CccTool
    {
        $this->assertModule($tool);
        $clean = array_map(fn (string $text): string => $this->plainChapter($text), $texts);

        return $this->changeChapters($tool, $actor, function (array $chapters) use ($clean): array {
            if (count($chapters) !== count($clean)) {
                throw new ContentRefused('chapters_changed');
            }

            return $clean;
        });
    }

    /**
     * Échange le chapitre de rang `$index` avec son voisin (−1 : monter, +1 : descendre). Renvoie son nouveau rang.
     *
     * @throws ContentRefused
     */
    public function moveChapter(CccTool $tool, int $index, int $direction, User $actor): int
    {
        $this->assertModule($tool);
        $target = $index + ($direction < 0 ? -1 : 1);

        $this->changeChapters($tool, $actor, function (array $chapters) use ($index, $target): array {
            if (! isset($chapters[$index], $chapters[$target])) {
                throw new ContentRefused('chapter_missing');
            }

            [$chapters[$index], $chapters[$target]] = [$chapters[$target], $chapters[$index]];

            return $chapters;
        });

        return $target;
    }

    /**
     * Échange le module avec son voisin dans l'ordre du manuel (−1 : monter, +1 : descendre) puis renumérote de 1 à n.
     * Renvoie le nouveau rang (0 pour le premier).
     *
     * @throws ContentRefused
     */
    public function move(CccTool $tool, int $direction, User $actor): int
    {
        $this->assertModule($tool);
        $target = null;

        DB::transaction(function () use ($tool, $direction, &$target): void {
            $ids = CccTool::query()->lockForUpdate()->ofKind(CccKind::Manual)->ordered()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $index = array_search((int) $tool->getKey(), $ids, true);
            $neighbour = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

            if ($index === false || $neighbour === null || ! isset($ids[$neighbour])) {
                throw new ContentRefused('module_move');
            }

            [$ids[$index], $ids[$neighbour]] = [$ids[$neighbour], $ids[$index]];
            $this->renumber($ids);
            $target = $neighbour;
        });

        Audit::log('ccc.module_moved', $tool, $tool->title, $actor);
        $this->cache->flush();

        return (int) $target;
    }

    /**
     * Supprime le module. Refusée tant qu'une publication sortante planifiée reprend sa clé (`outbound_posts.reference`).
     *
     * @throws ContentRefused
     */
    public function delete(CccTool $tool, User $actor): void
    {
        $this->assertModule($tool);

        DB::transaction(function () use ($tool): void {
            $locked = $this->lock($tool);
            $scheduled = $this->scheduledPostsCount($locked);

            if ($scheduled > 0) {
                throw new ContentRefused('module_scheduled', ['count' => $scheduled]);
            }

            $locked->delete();

            // Les rangs restent consécutifs : le prochain module créé prend la dernière place sans trou.
            $this->renumber(CccTool::query()->ofKind(CccKind::Manual)->ordered()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
        });

        Audit::log('ccc.module_deleted', $tool, $tool->title, $actor);
        $this->cache->flush();
    }

    /** Publications sortantes planifiées qui reprennent ce module par sa clé. */
    public function scheduledPostsCount(CccTool $tool): int
    {
        return OutboundPost::query()->scheduled()->where('reference', $tool->key)->count();
    }

    /**
     * Chapitres normalisés d'un module (textes), dans l'ordre de lecture.
     *
     * @return list<string>
     */
    public function chaptersOf(CccTool $tool): array
    {
        $chapters = [];

        foreach ((array) $tool->chapters as $chapter) {
            if (is_string($chapter)) {
                $chapters[] = $chapter;
            }
        }

        return $chapters;
    }

    /**
     * Relit le module verrouillé, applique la modification des chapitres, enregistre et journalise (titre seulement).
     *
     * @param  Closure(array<int, string>, CccTool): array<int, string>  $change
     */
    private function changeChapters(CccTool $tool, User $actor, Closure $change): CccTool
    {
        $locked = DB::transaction(function () use ($tool, $change): CccTool {
            $locked = $this->lock($tool);
            $locked->forceFill(['chapters' => array_values($change($this->chaptersOf($locked), $locked))])->save();

            return $locked;
        });

        Audit::log('ccc.module_chapters_updated', $locked, $locked->title, $actor);
        $this->cache->flush();

        return $locked;
    }

    /**
     * @param  array<int, int>  $ids
     */
    private function renumber(array $ids): void
    {
        foreach ($ids as $position => $id) {
            CccTool::query()->whereKey($id)->update(['position' => $position + 1]);
        }
    }

    /**
     * @param  array{title: string, description: ?string, duration_minutes: ?int}  $values
     *
     * @throws ContentRefused
     */
    private function insert(array $values): CccTool
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($values): CccTool {
                    if (CccTool::query()->ofKind(CccKind::Manual)->count() >= self::MODULES_MAX) {
                        throw new ContentRefused('modules_max', ['max' => self::MODULES_MAX]);
                    }

                    return CccTool::query()->create([
                        ...$values,
                        'kind' => CccKind::Manual,
                        'key' => $this->nextKey(),
                        'chapters' => [],
                        'position' => min(self::MODULES_MAX, (int) CccTool::query()->ofKind(CccKind::Manual)->max('position') + 1),
                        'is_published' => false,
                    ]);
                });
            } catch (UniqueConstraintViolationException $collision) {
                // Deux créations simultanées ont choisi la même clé : on recalcule.
                if ($attempt >= 3) {
                    throw $collision;
                }
            }
        }
    }

    private function nextKey(): string
    {
        $max = 0;

        foreach (CccTool::query()->where('key', 'like', 'm%')->pluck('key') as $key) {
            if (preg_match('/^m(\d+)$/', (string) $key, $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }

        return 'm'.($max + 1);
    }

    private function lock(CccTool $tool): CccTool
    {
        return CccTool::query()->lockForUpdate()->findOrFail($tool->getKey());
    }

    /** @throws ContentRefused */
    private function assertModule(CccTool $tool): void
    {
        if (! self::isModule($tool)) {
            throw new ContentRefused('not_module');
        }
    }

    /**
     * @return array{title: string, description: ?string, duration_minutes: ?int}
     *
     * @throws ContentRefused
     */
    private function values(string $title, string $description, ?int $duration): array
    {
        $title = $this->plain($title);
        $description = $this->plain($description);

        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            throw new ContentRefused('title_invalid', ['max' => self::TITLE_MAX]);
        }

        if (mb_strlen($description) > self::DESCRIPTION_MAX) {
            throw new ContentRefused('module_description', ['max' => self::DESCRIPTION_MAX]);
        }

        if ($duration !== null && ($duration < self::DURATION_MIN || $duration > self::DURATION_MAX)) {
            throw new ContentRefused('module_duration', ['min' => self::DURATION_MIN, 'max' => self::DURATION_MAX]);
        }

        return ['title' => $title, 'description' => $description === '' ? null : $description, 'duration_minutes' => $duration];
    }

    /** @throws ContentRefused */
    private function plainChapter(string $text): string
    {
        $text = $this->plain($text);

        if ($text === '' || mb_strlen($text) > self::CHAPTER_MAX) {
            throw new ContentRefused('chapter_invalid', ['max' => self::CHAPTER_MAX]);
        }

        return $text;
    }

    /** Texte simple : balises retirées, espaces et retours à la ligne normalisés, typographie française. */
    private function plain(string $value): string
    {
        return FrenchTypography::apply(trim((string) preg_replace('/[ \t\r\n]+/', ' ', strip_tags($value))));
    }
}
