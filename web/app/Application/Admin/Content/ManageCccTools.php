<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Models\CccTool;
use App\Models\Law;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;

/**
 * Outils CCC de l'administration (P12-04, permission `ccc_tools.manage` ; textes de loi : `laws.manage`) : publié / masqué
 * (`is_published`, lu par les écrans membre et pair, P8-12, et par `/lois` pour les textes), remplacement de l'image par
 * MediaUploader (disque public, ancienne image retirée sauf celles du dépôt `assets/…`), texte alternatif de chaque planche
 * d'une bande dessinée (obligatoire, 200 caractères au plus). Journalisé, sans contenu.
 */
final class ManageCccTools
{
    public const ALT_MAX = 200;

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
     * Textes alternatifs des planches, dans l'ordre des pages de l'outil.
     *
     * @param  list<string>  $alts
     *
     * @throws ContentRefused
     */
    public function saveAlts(CccTool $tool, array $alts, User $actor): CccTool
    {
        $pages = array_values(array_filter((array) $tool->pages, 'is_array'));

        foreach ($pages as $index => $page) {
            $alt = trim((string) ($alts[$index] ?? ''));

            if ($alt === '' || mb_strlen($alt) > self::ALT_MAX) {
                throw new ContentRefused('alt_invalid', ['page' => $index + 1, 'max' => self::ALT_MAX]);
            }

            $pages[$index]['alt'] = $alt;
        }

        $tool->forceFill(['pages' => $pages])->save();
        Audit::log('ccc.tool_alt_saved', $tool, $tool->title, $actor);
        $this->cache->flush();

        return $tool;
    }
}
