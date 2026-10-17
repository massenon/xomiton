<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ContentStatus;
use App\Enums\DirectoryRole;
use App\Models\Category;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Thematique>
 */
class ThematiqueFactory extends Factory
{
    protected $model = Thematique::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(6), '.'));

        return [
            'category_id' => Category::factory()->ofKind(Category::KIND_THEMATIQUE),
            'slug' => Str::slug($title),
            'title' => $title,
            'summary' => fake('fr_FR')->sentence(12),
            'body' => '<p>'.implode('</p><p>', fake('fr_FR')->paragraphs(3)).'</p>',
            'sections' => [
                ['title' => 'À retenir', 'body' => fake('fr_FR')->paragraph()],
                ['title' => 'Que faire ?', 'body' => fake('fr_FR')->paragraph()],
            ],
            'content_warning' => null,
            'image_path' => sprintf('assets/images/xomiton/hero-%02d.jpg', fake()->numberBetween(1, 16)),
            'cover_path' => null,
            'reading_minutes' => fake()->numberBetween(3, 8),
            'related_contact_roles' => [DirectoryRole::PointFocal->value],
            'related_ids' => null,
            'status' => ContentStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'views_count' => fake()->numberBetween(0, 500),
            'meta_title' => Str::limit($title, 67, '…'),
            'meta_description' => null,
            'created_by' => null,
        ];
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

    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ContentStatus::Published,
            'published_at' => now()->addWeek(),
        ]);
    }

    public function byAuthor(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['created_by' => $user->getKey()]);
    }
}
