<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Resource;
use App\Models\Thematique;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Resource>
 */
class ResourceFactory extends Factory
{
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
            'published_at' => fake()->dateTimeBetween('-6 months', 'now'),
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
}
