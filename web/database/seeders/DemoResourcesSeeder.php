<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Seo\MetaText;
use App\Application\Shared\MediaUploader;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Resource;
use App\Models\Thematique;
use App\Support\FrenchTypography;
use Database\Factories\ResourceFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Ressources de démonstration pour `/ressources` (P4-03 ; data/content/resources_demo.php) : six fiches PDF du
 * prototype avec un vrai fichier PDF minimal sur le disque privé (téléchargement signé testable), deux guides, une fiche
 * pratique, une vidéo externe, un lien et un brouillon ; deux fiches de crise marquées disponibles hors connexion.
 * Rattachées aux catégories `thematique` du ContentSeeder (comme le prototype) et à leur thématique (couverture
 * `covers/th-<id>.jpg`). Jamais en production (DatabaseSeeder::DEMO_ENVIRONMENTS) ; idempotent (clé : le slug).
 */
class DemoResourcesSeeder extends Seeder
{
    private const IMAGE_BASE = 'assets/images/xomiton/covers/';

    public function run(): void
    {
        if (! app()->environment(DatabaseSeeder::DEMO_ENVIRONMENTS) || ! Schema::hasTable('resources')) {
            return;
        }

        /** @var array{published_from: string, rows: list<array{0: string, 1: string, 2: string, 3: string, 4: string, 5: array<string, mixed>}>} $data */
        $data = require database_path('seeders/data/content/resources_demo.php');
        /** @var array{categories: array<string, array<string, mixed>>} $content */
        $content = require database_path('seeders/data/content/thematiques_content.php');
        $from = Carbon::parse($data['published_from']);
        $disk = Storage::disk(MediaUploader::privateDisk());

        foreach ($data['rows'] as [$slug, $kind, $cat, $title, $summary, $options]) {
            $kind = ResourceKind::from($kind);
            $status = ContentStatus::from((string) ($options['status'] ?? ContentStatus::Published->value));
            $body = $this->body($options['body'] ?? null, $content['categories'][$cat] ?? []);
            $filePath = null;

            if (($options['file'] ?? false) === true) {
                $filePath = 'resources/demo/'.$slug.'.pdf';

                if (! $disk->exists($filePath)) {
                    $disk->put($filePath, ResourceFactory::MINIMAL_PDF);
                }
            }

            $resource = Resource::query()->withTrashed()->updateOrCreate(['slug' => $slug], [
                'category_id' => Category::query()->ofKind(Category::KIND_THEMATIQUE)->where('slug', $cat)->value('id'),
                'thematique_id' => isset($options['thematique']) ? $this->thematiqueId((int) $options['thematique']) : null,
                'title' => $title,
                'kind' => $kind,
                'summary' => $summary,
                'body' => $body,
                'file_path' => $filePath,
                'file_size' => $filePath !== null ? $disk->size($filePath) : null,
                'external_url' => $options['external_url'] ?? null,
                'image_path' => isset($options['image']) ? self::IMAGE_BASE.'th-'.(int) $options['image'].'.jpg' : null,
                'duration_minutes' => $options['duration'] ?? null,
                'status' => $status,
                'published_at' => $status === ContentStatus::Published ? $from->copy()->subDays((int) ($options['days'] ?? 0)) : null,
                'is_offline_available' => (bool) ($options['offline'] ?? false),
                'meta_title' => MetaText::title($title),
                'meta_description' => MetaText::description($summary, $body, (string) __('pages/ressources.meta_description_filler')),
            ]);

            if ($resource->trashed()) {
                $resource->restore();
            }
        }
    }

    /** Fiche thématique du prototype dont la couverture semée porte cet id (ContentSeeder : covers/th-<id>.jpg). */
    private function thematiqueId(int $protoId): ?int
    {
        $id = Thematique::query()->where('cover_path', 'like', '%/covers/th-'.$protoId.'.jpg')->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Corps : HTML fourni, ou liste `steps` / `points` de la catégorie (thematiques_content.php), en typographie française.
     *
     * @param  array<string, mixed>  $category
     */
    private function body(?string $body, array $category): ?string
    {
        if ($body === null || ! in_array($body, ['steps', 'points'], true)) {
            return $body;
        }

        $items = array_map(
            static fn (mixed $item): string => '<li>'.e(FrenchTypography::apply((string) $item)).'</li>',
            (array) ($category[$body] ?? []),
        );

        if ($items === []) {
            return null;
        }

        $tag = $body === 'steps' ? 'ol' : 'ul';

        return '<'.$tag.'>'.implode('', $items).'</'.$tag.'>';
    }
}
