<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Enums\OutboundKind;
use App\Models\CccTool;
use App\Models\Law;
use App\Models\OutboundPost;
use App\Models\User;
use App\Support\Audit;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Outils CCC de l'administration (P12-04, permission `ccc_tools.manage` ; textes de loi : `laws.manage`) : publié / masqué
 * (`is_published`, lu par les écrans membre et pair, P8-12, et par `/lois` pour les textes), remplacement de l'image par
 * MediaUploader (disque public, ancienne image retirée sauf celles du dépôt `assets/…`), texte alternatif de chaque planche
 * d'une bande dessinée (obligatoire, 200 caractères au plus). Journalisé, sans contenu.
 *
 * Bandes dessinées SSR (kind `bd`) : création (clé publique `bd{n}` générée, jamais modifiée), titre, planches
 * (`pages` = liste ordonnée de `{image, caption, alt}` : l'ordre du JSON est l'ordre de lecture ; ajout, retrait, légende,
 * texte alternatif, déplacement) et suppression, refusée tant qu'une publication sortante planifiée la référence.
 * Le journal ne contient jamais le texte des planches (légende, texte alternatif) : le titre et le rang de la planche seulement.
 */
final class ManageCccTools
{
    public const ALT_MAX = 200;

    public const TITLE_MAX = 200;

    public const CAPTION_MAX = 300;

    public const PAGES_MAX = 60;

    /** `ccc_tools.position` est un entier non signé sur un octet. */
    private const POSITION_MAX = 255;

    public function __construct(
        private readonly ContentUploads $uploads,
        private readonly PublicContentCache $cache,
    ) {}

    public function setPublished(CccTool $tool, bool $published, User $actor): CccTool
    {
        $tool->forceFill(['is_published' => $published])->save();
        Audit::log($published ? 'ccc.tool_published' : 'ccc.tool_unpublished', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    public function setLawPublished(Law $law, bool $published, User $actor): Law
    {
        $law->forceFill(['is_published' => $published])->save();
        Audit::log($published ? 'ccc.law_published' : 'ccc.law_unpublished', $law, $law->title, $actor);
        $this->cache->flush();

        return $law;
    }

    /**
     * Remplace l'image d'un outil. Une boîte à images garde son image unique dans sa planche : la planche suit.
     *
     * @throws ContentRefused
     */
    public function replaceImage(CccTool $tool, UploadedFile $file, User $actor): CccTool
    {
        $path = $this->uploads->image($file);
        $old = $tool->image_path;
        $pages = $tool->pages;

        if ($tool->kind === CccKind::ImageBox && is_array($pages) && isset($pages[0]) && is_array($pages[0])) {
            $pages[0]['image'] = $path;
        }

        $tool->forceFill(['image_path' => $path, 'pages' => $pages])->save();
        $this->uploads->discard($old, true);
        Audit::log('ccc.tool_image_replaced', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    /**
     * Crée une bande dessinée : clé `bd{n}` suivante, dernière position, couverture et planches initiales facultatives.
     * Les fichiers sont téléversés d'abord et retirés si la création échoue.
     *
     * @param  list<array{file: UploadedFile, caption: string, alt: string}>  $pages
     *
     * @throws ContentRefused
     */
    public function createComic(string $title, ?UploadedFile $cover, array $pages, bool $published, User $actor): CccTool
    {
        $title = $this->validTitle($title);

        if (count($pages) > self::PAGES_MAX) {
            throw new ContentRefused('pages_max', ['max' => self::PAGES_MAX]);
        }

        if ($published && $pages === []) {
            throw new ContentRefused('comic_empty');
        }

        /** @var list<string> $stored */
        $stored = [];

        try {
            $coverPath = null;

            if ($cover !== null) {
                $coverPath = $this->uploads->image($cover);
                $stored[] = $coverPath;
            }

            $entries = [];

            foreach ($pages as $page) {
                $image = $this->uploads->image($page['file']);
                $stored[] = $image;
                $entries[] = [
                    'image' => $image,
                    'caption' => $this->validCaption($page['caption']),
                    'alt' => $this->validAlt($page['alt'], count($entries) + 1),
                ];
            }

            $tool = $this->insertComic($title, $coverPath, $entries, $published);
        } catch (Throwable $failure) {
            foreach ($stored as $path) {
                $this->uploads->discard($path, true);
            }

            throw $failure;
        }

        Audit::log('ccc.comic_created', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    /** @throws ContentRefused */
    public function renameComic(CccTool $tool, string $title, User $actor): CccTool
    {
        $this->assertComic($tool);
        $tool->forceFill(['title' => $this->validTitle($title)])->save();
        Audit::log('ccc.comic_renamed', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }

    /**
     * Publie ou masque une bande dessinée ; une BD sans planche ne se publie pas (les lecteurs n'auraient rien à afficher).
     *
     * @throws ContentRefused
     */
    public function setComicPublished(CccTool $tool, bool $published, User $actor): CccTool
    {
        $this->assertComic($tool);

        if ($published && $this->pagesOf($tool) === []) {
            throw new ContentRefused('comic_empty');
        }

        return $this->setPublished($tool, $published, $actor);
    }

    /**
     * Ajoute une planche à la fin : image téléversée, légende facultative, texte alternatif obligatoire.
     *
     * @throws ContentRefused
     */
    public function addPage(CccTool $tool, UploadedFile $file, string $caption, string $alt, User $actor): CccTool
    {
        $this->assertComic($tool);
        $caption = $this->validCaption($caption);
        $alt = $this->validAlt($alt, null);
        $path = $this->uploads->image($file);

        try {
            $locked = $this->mutatePages($tool, function (array $pages) use ($path, $caption, $alt): array {
                if (count($pages) >= self::PAGES_MAX) {
                    throw new ContentRefused('pages_max', ['max' => self::PAGES_MAX]);
                }

                $pages[] = ['image' => $path, 'caption' => $caption, 'alt' => $alt];

                return $pages;
            });
        } catch (Throwable $failure) {
            $this->uploads->discard($path, true);

            throw $failure;
        }

        Audit::log('ccc.comic_page_added', $locked, $this->pageDetail($locked, count($this->pagesOf($locked))), $actor);
        $this->cache->flush();

        return $locked;
    }

    /**
     * Retire la planche de rang `$index` (0 pour la première) et supprime son média s'il n'est plus utilisé.
     * Une BD publiée garde au moins une planche.
     *
     * @throws ContentRefused
     */
    public function removePage(CccTool $tool, int $index, User $actor): CccTool
    {
        $this->assertComic($tool);
        $removed = null;

        $locked = $this->mutatePages($tool, function (array $pages) use ($tool, $index, &$removed): array {
            if (! isset($pages[$index])) {
                throw new ContentRefused('page_missing');
            }

            if (count($pages) === 1 && $tool->fresh()?->is_published === true) {
                throw new ContentRefused('last_page_published');
            }

            $removed = $pages[$index]['image'];
            array_splice($pages, $index, 1);

            return $pages;
        });

        if (is_string($removed) && $removed !== $locked->image_path && ! $this->usesImage($locked, $removed)) {
            $this->uploads->discard($removed, true);
        }

        Audit::log('ccc.comic_page_removed', $locked, $this->pageDetail($locked, $index + 1), $actor);
        $this->cache->flush();

        return $locked;
    }

    /**
     * Légende et texte alternatif de chaque planche, dans l'ordre des planches (même nombre que la BD).
     *
     * @param  list<array{caption: string, alt: string}>  $edits
     *
     * @throws ContentRefused
     */
    public function savePages(CccTool $tool, array $edits, User $actor): CccTool
    {
        $this->assertComic($tool);

        $locked = $this->mutatePages($tool, function (array $pages) use ($edits): array {
            if (count($pages) !== count($edits)) {
                throw new ContentRefused('pages_changed');
            }

            foreach ($pages as $index => $page) {
                $pages[$index]['caption'] = $this->validCaption($edits[$index]['caption']);
                $pages[$index]['alt'] = $this->validAlt($edits[$index]['alt'], $index + 1);
            }

            return $pages;
        });

        Audit::log('ccc.comic_pages_saved', $locked, $locked->title, $actor);
        $this->cache->flush();

        return $locked;
    }

    /**
     * Échange la planche de rang `$index` avec sa voisine (−1 : monter, +1 : descendre). Renvoie son nouveau rang.
     *
     * @throws ContentRefused
     */
    public function movePage(CccTool $tool, int $index, int $direction, User $actor): int
    {
        $this->assertComic($tool);
        $target = $index + ($direction < 0 ? -1 : 1);

        $locked = $this->mutatePages($tool, function (array $pages) use ($index, $target): array {
            if (! isset($pages[$index], $pages[$target])) {
                throw new ContentRefused('page_missing');
            }

            [$pages[$index], $pages[$target]] = [$pages[$target], $pages[$index]];

            return $pages;
        });

        Audit::log('ccc.comic_page_moved', $locked, $this->pageDetail($locked, $target + 1), $actor);
        $this->cache->flush();

        return $target;
    }

    /**
     * Supprime la bande dessinée, sa couverture et les médias de ses planches. Refusée tant qu'une publication sortante
     * planifiée (`outbound_posts.kind = bd`, `reference` = clé de la BD) n'est pas envoyée ou annulée.
     *
     * @throws ContentRefused
     */
    public function deleteComic(CccTool $tool, User $actor): void
    {
        $this->assertComic($tool);
        $files = [];

        DB::transaction(function () use ($tool, &$files): void {
            $locked = $this->lock($tool);
            $scheduled = $this->scheduledPostsCount($locked);

            if ($scheduled > 0) {
                throw new ContentRefused('comic_scheduled', ['count' => $scheduled]);
            }

            $files = array_map(static fn (array $page): string => $page['image'], $this->pagesOf($locked));
            $files[] = (string) $locked->image_path;
            $locked->delete();
        });

        foreach (array_unique($files) as $path) {
            $this->uploads->discard($path, true);
        }

        Audit::log('ccc.comic_deleted', $tool, $tool->title, $actor);
        $this->cache->flush();
    }

    /** Publications sortantes planifiées qui reprennent cette BD. */
    public function scheduledPostsCount(CccTool $tool): int
    {
        return OutboundPost::query()->scheduled()->ofKind(OutboundKind::Bd)->where('reference', $tool->key)->count();
    }

    /**
     * Planches normalisées d'une BD (image, légende, texte alternatif), dans l'ordre de lecture.
     *
     * @return list<array{image: string, caption: string, alt: string}>
     */
    public function pagesOf(CccTool $tool): array
    {
        $pages = [];

        foreach ((array) $tool->pages as $page) {
            if (is_array($page)) {
                $pages[] = [
                    'image' => (string) ($page['image'] ?? ''),
                    'caption' => (string) ($page['caption'] ?? ''),
                    'alt' => (string) ($page['alt'] ?? ''),
                ];
            }
        }

        return $pages;
    }

    /**
     * @param  list<array{image: string, caption: string, alt: string}>  $entries
     */
    private function insertComic(string $title, ?string $coverPath, array $entries, bool $published): CccTool
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(fn (): CccTool => CccTool::query()->create([
                    'kind' => CccKind::Comic,
                    'key' => $this->nextComicKey(),
                    'title' => $title,
                    'image_path' => $coverPath,
                    'pages' => $entries,
                    'position' => $this->nextComicPosition(),
                    'is_published' => $published,
                ]));
            } catch (UniqueConstraintViolationException $collision) {
                // Deux créations simultanées ont choisi la même clé : on recalcule.
                if ($attempt >= 3) {
                    throw $collision;
                }
            }
        }
    }

    private function nextComicKey(): string
    {
        $prefix = CccKind::Comic->keyPrefix();
        $max = 0;

        foreach (CccTool::query()->where('key', 'like', $prefix.'%')->pluck('key') as $key) {
            if (preg_match('/^'.$prefix.'(\d+)$/', (string) $key, $match) === 1) {
                $max = max($max, (int) $match[1]);
            }
        }

        return $prefix.($max + 1);
    }

    private function nextComicPosition(): int
    {
        return min(self::POSITION_MAX, (int) CccTool::query()->ofKind(CccKind::Comic)->max('position') + 1);
    }

    /**
     * Relit la BD verrouillée, applique la modification des planches et enregistre, dans une transaction.
     *
     * @param  Closure(array<int, array{image: string, caption: string, alt: string}>): array<int, array{image: string, caption: string, alt: string}>  $change
     */
    private function mutatePages(CccTool $tool, Closure $change): CccTool
    {
        return DB::transaction(function () use ($tool, $change): CccTool {
            $locked = $this->lock($tool);
            $locked->forceFill(['pages' => array_values($change($this->pagesOf($locked)))])->save();

            return $locked;
        });
    }

    private function lock(CccTool $tool): CccTool
    {
        return CccTool::query()->lockForUpdate()->findOrFail($tool->getKey());
    }

    private function usesImage(CccTool $tool, string $path): bool
    {
        foreach ($this->pagesOf($tool) as $page) {
            if ($page['image'] === $path) {
                return true;
            }
        }

        return false;
    }

    /** Détail de journal : titre et rang de la planche, jamais sa légende ni son texte alternatif. */
    private function pageDetail(CccTool $tool, int $rank): string
    {
        return $tool->title.' · '.$rank;
    }

    /** @throws ContentRefused */
    private function assertComic(CccTool $tool): void
    {
        if ($tool->kind !== CccKind::Comic) {
            throw new ContentRefused('not_comic');
        }
    }

    /** @throws ContentRefused */
    private function validTitle(string $title): string
    {
        $title = trim($title);

        if ($title === '' || mb_strlen($title) > self::TITLE_MAX) {
            throw new ContentRefused('title_invalid', ['max' => self::TITLE_MAX]);
        }

        return $title;
    }

    /** @throws ContentRefused */
    private function validCaption(string $caption): string
    {
        $caption = trim($caption);

        if (mb_strlen($caption) > self::CAPTION_MAX) {
            throw new ContentRefused('caption_long', ['max' => self::CAPTION_MAX]);
        }

        return $caption;
    }

    /** @throws ContentRefused */
    private function validAlt(string $alt, ?int $page): string
    {
        $alt = trim($alt);

        if ($alt === '' || mb_strlen($alt) > self::ALT_MAX) {
            throw new ContentRefused($page === null ? 'alt_required' : 'alt_invalid', ['page' => $page, 'max' => self::ALT_MAX]);
        }

        return $alt;
    }
}
