<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Models\SlugRedirect;
use App\Models\Thematique;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Thématiques de l'administration (P12-03, permission `thematiques.manage`, policy ThematiquePolicy) : création,
 * modification, publication / brouillon, suppression douce, couverture et image de partage (Open Graph 1200 × 630).
 * Une fiche ne se publie qu'avec un corps ou des sections (`empty_sheet`). Changer le slug garde l'ancien (301).
 */
final class ManageThematiques
{
    public function __construct(
        private readonly ContentUploads $uploads,
        private readonly SlugHistory $history,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data  données validées par ContentRules::thematique()
     *
     * @throws ContentRefused
     */
    public function save(?Thematique $thematique, array $data, ?UploadedFile $cover, ?UploadedFile $og, User $actor): Thematique
    {
        $publish = $data['status'] === ContentStatus::Published->value;
        $thematique ??= new Thematique;
        $creating = ! $thematique->exists;
        $oldSlug = (string) $thematique->slug;

        if ($publish) {
            self::assertPublishable(trim((string) ($data['body'] ?? '')) !== '' || ! empty($thematique->sections));
        }

        $stored = [];

        try {
            if ($cover !== null) {
                $stored['cover_path'] = $this->uploads->image($cover);
            }

            if ($og !== null) {
                $stored['og_image_path'] = $this->uploads->ogImage($og);
            }

            $old = array_intersect_key($thematique->getAttributes(), $stored);
            $thematique->fill([
                'category_id' => (int) $data['category_id'],
                'title' => trim((string) $data['title']),
                'slug' => $this->slug($thematique, (string) ($data['slug'] ?? ''), (string) $data['title']),
                'summary' => trim((string) $data['summary']),
                'body' => self::nullable($data['body'] ?? null),
                'content_warning' => self::nullable($data['content_warning'] ?? null),
                'reading_minutes' => (int) $data['reading_minutes'],
                'meta_title' => self::nullable($data['meta_title'] ?? null),
                'meta_description' => self::nullable($data['meta_description'] ?? null),
                ...$stored,
            ]);
            $thematique->status = $publish ? ContentStatus::Published : ($thematique->status === ContentStatus::Hidden ? ContentStatus::Hidden : ContentStatus::Draft);

            if ($publish && $thematique->published_at === null) {
                $thematique->published_at = now();
            }

            if ($creating) {
                $thematique->created_by = $actor->getKey();
            }

            DB::transaction(function () use ($thematique, $oldSlug, $creating): void {
                $thematique->save();

                if (! $creating) {
                    $this->history->renamed(SlugRedirect::TYPE_THEMATIQUE, (int) $thematique->getKey(), $oldSlug, (string) $thematique->slug);
                }
            });
        } catch (Throwable $error) {
            foreach ($stored as $path) {
                $this->uploads->discard(is_string($path) ? $path : null, true);
            }

            throw $error;
        }

        foreach ($old as $path) {
            $this->uploads->discard(is_string($path) ? $path : null, true);
        }

        Audit::log($creating ? 'content.thematique_created' : 'content.thematique_updated', $thematique, $thematique->title, $actor);
        $this->cache->flush();

        return $thematique;
    }

    /**
     * @throws ContentRefused
     */
    public function setPublished(Thematique $thematique, bool $published, User $actor): Thematique
    {
        if ($published) {
            self::assertPublishable(trim((string) $thematique->body) !== '' || ! empty($thematique->sections));
        }

        $thematique->forceFill([
            'status' => $published ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $published ? ($thematique->published_at ?? now()) : $thematique->published_at,
        ])->save();

        Audit::log($published ? 'content.thematique_published' : 'content.thematique_unpublished', $thematique, $thematique->title, $actor);
        $this->cache->flush();

        return $thematique;
    }

    public function delete(Thematique $thematique, User $actor): void
    {
        $thematique->delete();
        Audit::log('content.thematique_deleted', $thematique, $thematique->title, $actor);
        $this->cache->flush();
    }

    /**
     * @throws ContentRefused
     */
    public static function assertPublishable(bool $hasContent): void
    {
        if (! $hasContent) {
            throw new ContentRefused('empty_sheet');
        }
    }

    private function slug(Thematique $thematique, string $wanted, string $title): string
    {
        $wanted = trim($wanted);

        if ($wanted !== '') {
            return $wanted;
        }

        if ((string) $thematique->slug !== '') {
            return (string) $thematique->slug;
        }

        $base = Str::limit(Str::slug($title), 110, '') ?: 'thematique';
        $slug = $base;

        for ($i = 2; Thematique::withTrashed()->where('slug', $slug)->exists() || SlugHistory::usedByOther(SlugRedirect::TYPE_THEMATIQUE, $slug, null); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private static function nullable(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : null;

        return $value === '' ? null : $value;
    }
}
