<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Models\Law;
use App\Models\User;
use App\Support\Audit;
use App\Support\FrenchTypography;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lois et codes de l'administration (P12-04, onglet « Lois » de `/admin/ccc`, permission `laws.manage` en plus de
 * `ccc_tools.manage`) : création, modification, suppression, ordre (monter / descendre) et publié / masqué des textes lus
 * par `/lois` (ListLaws).
 *
 * Champs : titre, sous-titre, catégorie, résumé juridique, version simple, lien officiel (http ou https), publication.
 * Ce sont des textes simples (la page publique les échappe) : les balises sont retirées et refusées, la typographie
 * française est appliquée comme au semis (apostrophe courbe, espace insécable). La clé de l'adresse (`slug`, ancre
 * `/lois#texte-{slug}`) est dérivée du titre à la création et ne change plus. L'ordre est une liste renumérotée de 1 à n
 * (`position`, un octet : 255 textes au plus). Journal : titre seulement, jamais le résumé ni la version simple.
 */
final class ManageLaws
{
    public const TITLE_MAX = 200;

    public const SUBTITLE_MAX = 200;

    public const CATEGORY_MAX = 80;

    public const SUMMARY_MAX = 2000;

    public const URL_MAX = 500;

    /** `laws.position` est un entier non signé sur un octet. */
    public const LAWS_MAX = 255;

    private const SLUG_MAX = 80;

    public function __construct(private readonly PublicContentCache $cache) {}

    public function setPublished(Law $law, bool $published, User $actor): Law
    {
        $law->forceFill(['is_published' => $published])->save();
        Audit::log($published ? 'ccc.law_published' : 'ccc.law_unpublished', $law, $law->title, $actor);
        $this->cache->flush();

        return $law;
    }

    /**
     * Crée (`$law` nul : dernière position, clé dérivée du titre) ou modifie un texte.
     *
     * @param  array{title: string, subtitle: string, category: string, summary: string, simple_summary: string, official_url: string, is_published: bool}  $data
     *
     * @throws ContentRefused
     */
    public function save(?Law $law, array $data, User $actor): Law
    {
        $values = [
            'title' => $this->plain($data['title'], 'title', self::TITLE_MAX, true),
            'subtitle' => $this->plain($data['subtitle'], 'subtitle', self::SUBTITLE_MAX, false),
            'category' => $this->plain($data['category'], 'category', self::CATEGORY_MAX, true, false),
            'summary' => $this->plain($data['summary'], 'summary', self::SUMMARY_MAX, true),
            'simple_summary' => $this->plain($data['simple_summary'], 'simple_summary', self::SUMMARY_MAX, false),
            'official_url' => $this->officialUrl($data['official_url']),
            'is_published' => $data['is_published'],
        ];
        $values = array_map(static fn (mixed $value): mixed => $value === '' ? null : $value, $values);

        if ($law !== null) {
            $law->forceFill($values)->save();
            Audit::log('ccc.law_updated', $law, $law->title, $actor);
            $this->cache->flush();

            return $law;
        }

        $created = $this->insert($values);
        Audit::log('ccc.law_created', $created, $created->title, $actor);
        $this->cache->flush();

        return $created;
    }

    /**
     * Échange le texte avec son voisin dans l'ordre de la liste (−1 : monter, +1 : descendre) puis renumérote de 1 à n :
     * deux textes de même rang ne bloquent jamais l'échange. Renvoie le nouveau rang (0 pour le premier).
     *
     * @throws ContentRefused
     */
    public function move(Law $law, int $direction, User $actor): int
    {
        $target = null;

        DB::transaction(function () use ($law, $direction, &$target): void {
            $ids = Law::query()->lockForUpdate()->ordered()->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $index = array_search((int) $law->getKey(), $ids, true);
            $neighbour = $index === false ? null : $index + ($direction < 0 ? -1 : 1);

            if ($index === false || $neighbour === null || ! isset($ids[$neighbour])) {
                throw new ContentRefused('law_move');
            }

            [$ids[$index], $ids[$neighbour]] = [$ids[$neighbour], $ids[$index]];

            foreach ($ids as $position => $id) {
                Law::query()->whereKey($id)->update(['position' => $position + 1]);
            }

            $target = $neighbour;
        });

        Audit::log('ccc.law_moved', $law, $law->title, $actor);
        $this->cache->flush();

        return (int) $target;
    }

    public function delete(Law $law, User $actor): void
    {
        DB::transaction(function () use ($law): void {
            $law->delete();

            // Les rangs restent consécutifs : le prochain texte créé prend la dernière place sans trou.
            foreach (Law::query()->ordered()->pluck('id') as $position => $id) {
                Law::query()->whereKey($id)->update(['position' => $position + 1]);
            }
        });

        Audit::log('ccc.law_deleted', $law, $law->title, $actor);
        $this->cache->flush();
    }

    /**
     * @param  array<string, mixed>  $values
     *
     * @throws ContentRefused
     */
    private function insert(array $values): Law
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($values): Law {
                    if (Law::query()->count() >= self::LAWS_MAX) {
                        throw new ContentRefused('laws_max', ['max' => self::LAWS_MAX]);
                    }

                    return Law::query()->create([
                        ...$values,
                        'slug' => $this->uniqueSlug((string) $values['title']),
                        'position' => min(self::LAWS_MAX, (int) Law::query()->max('position') + 1),
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

    private function uniqueSlug(string $title): string
    {
        $base = trim(mb_substr(Str::slug($title), 0, self::SLUG_MAX - 4), '-');
        $base = $base === '' ? 'texte' : $base;
        $slug = $base;

        for ($i = 2; Law::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    /**
     * Texte simple : balises retirées (et refusées si elles ne laissent rien), espaces normalisés, typographie française.
     *
     * @throws ContentRefused
     */
    private function plain(string $value, string $field, int $max, bool $required, bool $typography = true): string
    {
        $clean = trim((string) preg_replace('/[\t ]+/u', ' ', str_replace(["\r\n", "\r"], "\n", strip_tags($value))));
        $clean = $typography ? FrenchTypography::apply($clean) : $clean;

        if (($required && $clean === '') || mb_strlen($clean) > $max) {
            throw new ContentRefused('law_field', ['field' => (string) __('admin_ccc.law.fields.'.$field), 'max' => $max]);
        }

        return $clean;
    }

    /** @throws ContentRefused */
    private function officialUrl(string $url): string
    {
        $url = trim($url);

        if ($url === '') {
            return '';
        }

        if (mb_strlen($url) > self::URL_MAX || preg_match('#^https?://[^\s]+$#i', $url) !== 1 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new ContentRefused('law_url');
        }

        return $url;
    }
}
