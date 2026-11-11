<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Application\Shared\MediaUploader;
use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
    /** PDF minimal valide (une page vide) : fichiers de démonstration et de test (P4-03). */
    public const MINIMAL_PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 595 842]>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    /** Débuts de fichiers MP4 (boîte `ftyp`) et MP3 (en-tête ID3 + trames MPEG) reconnus par libmagic : tests de lecture. */
    public const MINIMAL_MP4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom\x00\x00\x00\x08free";

    public const MINIMAL_MP3 = "ID3\x04\x00\x00\x00\x00\x00\x00\xFF\xFB\x90\x64";

    protected $model = Resource::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(5), '.'));

        return [
            'category_id' => Category::factory()->ofKind(Category::KIND_RESOURCE),
            'thematique_id' => null,
            'slug' => Str::slug($title),
            'title' => $title,
            'kind' => ResourceKind::Sheet,
            'summary' => fake('fr_FR')->sentence(12),
            'body' => '<p>'.implode('</p><p>', fake('fr_FR')->paragraphs(2)).'</p>',
            'file_path' => null,
            'file_size' => null,
            'external_url' => null,
            'image_path' => null,
            'duration_minutes' => null,
            'status' => ContentStatus::Published,
            // Relatif à now() (Carbon::setTestNow) : dateTimeBetween(…, 'now') lit l'horloge réelle.
            'published_at' => now()->subDays(fake()->numberBetween(1, 180)),
            'downloads_count' => 0,
            'views_count' => fake()->numberBetween(0, 200),
            'is_offline_available' => false,
            'meta_title' => null,
            'meta_description' => null,
            'created_by' => null,
        ];
    }

    public function ofKind(ResourceKind $kind): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => $kind]);
    }

    public function article(): static
    {
        return $this->ofKind(ResourceKind::Article);
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ResourceKind::Pdf,
            'body' => null,
            'file_path' => 'resources/'.fake()->uuid().'.pdf',
            'file_size' => fake()->numberBetween(50_000, 5_000_000),
            'is_offline_available' => true,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ResourceKind::Video,
            'body' => null,
            'external_url' => 'https://www.youtube.com/watch?v='.Str::random(11),
            'duration_minutes' => fake()->numberBetween(2, 40),
        ]);
    }

    public function link(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ResourceKind::Link,
            'body' => null,
            'external_url' => fake()->url(),
        ]);
    }

    public function forThematique(Thematique $thematique): static
    {
        return $this->state(fn (array $attributes): array => ['thematique_id' => $thematique->getKey()]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => ['status' => ContentStatus::Hidden]);
    }

    /** Publiée à une date future : invisible jusqu'à son heure. */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Published,
            'published_at' => now()->addDays(3),
        ]);
    }

    /**
     * Écrit réellement le fichier (PDF minimal) sur le disque privé : à combiner avec `Storage::fake('local')` dans les
     * tests de téléchargement. Un `file_size` explicite garde la priorité (poids affiché).
     */
    public function withFile(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_path' => $attributes['file_path'] ?? 'resources/'.fake()->uuid().'.pdf',
            'file_size' => strlen(self::MINIMAL_PDF),
        ])->afterCreating(function (Resource $resource): void {
            Storage::disk(MediaUploader::privateDisk())->put((string) $resource->file_path, self::MINIMAL_PDF);
        });
    }

    /**
     * Média hébergé lisible dans la page : écrit un vrai début de MP4 (vidéo, par défaut) ou de MP3 (audio) sur le disque
     * privé, complété pour dépasser 1 Kio (plages `Range` testables). À combiner avec `Storage::fake('local')`.
     */
    public function withMedia(ResourceKind $kind = ResourceKind::Video): static
    {
        [$extension, $header] = $kind === ResourceKind::Audio ? ['mp3', self::MINIMAL_MP3] : ['mp4', self::MINIMAL_MP4];
        $bytes = $header.str_repeat("\x00", 2048 - strlen($header));

        return $this->state(fn (array $attributes): array => [
            'kind' => $kind,
            'body' => $attributes['body'] ?? null,
            'external_url' => null,
            'file_path' => 'resources/'.fake()->uuid().'.'.$extension,
            'file_size' => strlen($bytes),
        ])->afterCreating(function (Resource $resource) use ($bytes): void {
            Storage::disk(MediaUploader::privateDisk())->put((string) $resource->file_path, $bytes);
        });
    }
}
