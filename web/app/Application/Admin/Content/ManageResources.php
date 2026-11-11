<?php

declare(strict_types=1);

namespace App\Application\Admin\Content;

use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Resource;
use App\Models\SlugRedirect;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Ressources de l'administration (P12-03, permission `resources.manage`, policy ResourcePolicy) : création, modification,
 * publication / brouillon, suppression douce. Règles de publication (ACCESSIBILITE.md §5) : un PDF, un audio ou une
 * vidéo exige son fichier (ou, pour une vidéo ou un audio, son adresse externe) ; un lien exige son adresse `https` ; une vidéo ou un audio exige **sous-titres WebVTT et
 * transcription**, sinon la publication est refusée (`accessibility_missing`) et le brouillon reste enregistrable.
 * Changer le slug garde l'ancien (301, SlugHistory). Chaque écriture journalise (sans le texte) et invalide le plan du
 * site (PublicContentCache).
 */
final class ManageResources
{
    public function __construct(
        private readonly ContentUploads $uploads,
        private readonly SlugHistory $history,
        private readonly PublicContentCache $cache,
    ) {}

    /**
     * @param  array<string, mixed>  $data  données validées par ContentRules::resource()
     *
     * @throws ContentRefused
     */
    public function save(?Resource $resource, array $data, ResourceUploads $files, User $actor): Resource
    {
        $kind = ResourceKind::from((string) $data['kind']);
        $publish = $data['status'] === ContentStatus::Published->value;
        $resource ??= new Resource;
        $creating = ! $resource->exists;
        $oldSlug = (string) $resource->slug;
        $hasFile = ($kind->hasFile() && $files->file !== null) || ($kind->hasFile() && $resource->kind === $kind && (string) $resource->file_path !== '');
        $hasSubtitles = $files->subtitles !== null || (string) $resource->subtitles_path !== '';

        if ($publish) {
            self::assertPublishable($kind, $hasFile, (string) ($data['external_url'] ?? ''), $hasSubtitles, (string) ($data['transcript'] ?? ''));
        }

        $stored = [];

        try {
            $attributes = [
                'category_id' => $data['category_id'] ?? null,
                'thematique_id' => $data['thematique_id'] ?? null,
                'title' => trim((string) $data['title']),
                'kind' => $kind,
                'summary' => trim((string) $data['summary']),
                'body' => self::nullable($data['body'] ?? null),
                'external_url' => self::nullable($data['external_url'] ?? null),
                'duration_minutes' => $data['duration_minutes'] ?? null,
                'is_offline_available' => (bool) ($data['is_offline_available'] ?? false),
                'transcript' => self::nullable($data['transcript'] ?? null),
                'meta_title' => self::nullable($data['meta_title'] ?? null),
                'meta_description' => self::nullable($data['meta_description'] ?? null),
                'slug' => $this->slug($resource, (string) ($data['slug'] ?? ''), (string) $data['title']),
            ];

            if ($files->image !== null) {
                $stored['image_path'] = $this->uploads->image($files->image);
            }

            if ($files->og !== null) {
                $stored['og_image_path'] = $this->uploads->ogImage($files->og);
            }

            if ($files->file !== null && $kind->hasFile()) {
                [$stored['file_path'], $stored['file_size']] = $this->uploads->resourceFile($files->file, $kind);
            }

            if ($files->subtitles !== null) {
                $stored['subtitles_path'] = $this->uploads->subtitles($files->subtitles);
            }

            $old = array_intersect_key($resource->getAttributes(), $stored);
            $resource->fill([...$attributes, ...$stored]);

            if (! $kind->hasFile()) {
                $resource->fill(['file_path' => null, 'file_size' => null]);
            }

            $resource->status = $publish ? ContentStatus::Published : ($resource->status === ContentStatus::Hidden ? ContentStatus::Hidden : ContentStatus::Draft);

            if ($publish && $resource->published_at === null) {
                $resource->published_at = now();
            }

            if ($creating) {
                $resource->created_by = $actor->getKey();
            }

            DB::transaction(function () use ($resource, $oldSlug, $creating): void {
                $resource->save();

                if (! $creating) {
                    $this->history->renamed(SlugRedirect::TYPE_RESOURCE, (int) $resource->getKey(), $oldSlug, (string) $resource->slug);
                }
            });
        } catch (Throwable $error) {
            foreach ($stored as $column => $path) {
                if (is_string($path) && in_array($column, ['image_path', 'og_image_path'], true)) {
                    $this->uploads->discard($path, true);
                } elseif (is_string($path) && in_array($column, ['file_path', 'subtitles_path'], true)) {
                    $this->uploads->discard($path, false);
                }
            }

            throw $error;
        }

        foreach ($old as $column => $path) {
            $this->uploads->discard(is_string($path) ? $path : null, in_array($column, ['image_path', 'og_image_path'], true));
        }

        Audit::log($creating ? 'content.resource_created' : 'content.resource_updated', $resource, $resource->title, $actor);
        $this->cache->flush();

        return $resource;
    }

    /**
     * Publier ou repasser en brouillon sans rouvrir le formulaire.
     *
     * @throws ContentRefused
     */
    public function setPublished(Resource $resource, bool $published, User $actor): Resource
    {
        if ($published) {
            self::assertPublishable(
                $resource->kind,
                $resource->kind->hasFile() && (string) $resource->file_path !== '',
                (string) $resource->external_url,
                (string) $resource->subtitles_path !== '',
                (string) $resource->transcript,
            );
        }

        $resource->forceFill([
            'status' => $published ? ContentStatus::Published : ContentStatus::Draft,
            'published_at' => $published ? ($resource->published_at ?? now()) : $resource->published_at,
        ])->save();

        Audit::log($published ? 'content.resource_published' : 'content.resource_unpublished', $resource, $resource->title, $actor);
        $this->cache->flush();

        return $resource;
    }

    public function delete(Resource $resource, User $actor): void
    {
        $resource->delete();
        Audit::log('content.resource_deleted', $resource, $resource->title, $actor);
        $this->cache->flush();
    }

    /**
     * @throws ContentRefused
     */
    public static function assertPublishable(ResourceKind $kind, bool $hasFile, string $externalUrl, bool $hasSubtitles, string $transcript): void
    {
        if ($kind->hasFile() && ! $hasFile && trim($externalUrl) === '') {
            throw new ContentRefused('file_missing');
        }

        if ($kind === ResourceKind::Link && trim($externalUrl) === '') {
            throw new ContentRefused('link_missing');
        }

        if (in_array($kind, [ResourceKind::Video, ResourceKind::Audio], true) && (! $hasSubtitles || trim($transcript) === '')) {
            throw new ContentRefused('accessibility_missing');
        }
    }

    private function slug(Resource $resource, string $wanted, string $title): string
    {
        $wanted = trim($wanted);

        if ($wanted !== '') {
            return $wanted;
        }

        if ((string) $resource->slug !== '') {
            return (string) $resource->slug;
        }

        $base = Str::limit(Str::slug($title), 110, '') ?: 'ressource';
        $slug = $base;

        for ($i = 2; Resource::withTrashed()->where('slug', $slug)->exists() || SlugHistory::usedByOther(SlugRedirect::TYPE_RESOURCE, $slug, null); $i++) {
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
