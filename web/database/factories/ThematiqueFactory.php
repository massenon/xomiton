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
            // Visuels présents dans public/assets/images/xomiton (ceux du prototype repris par ContentSeeder).
            'image_path' => 'assets/images/xomiton/'.fake()->randomElement(['hero-03.jpg', 'hero-04.jpg', 'hero-05.jpg', 'hero-07.jpg', 'hero-09.jpg', 'hero-10.jpg', 'hero-12.jpg', 'hero-13.jpg']),
            'cover_path' => null,
            'reading_minutes' => fake()->numberBetween(3, 8),
            'related_contact_roles' => [DirectoryRole::PointFocal->value],
            'related_ids' => null,
            'status' => ContentStatus::Published,
            // Relatif à now() (Carbon::setTestNow) : dateTimeBetween(…, 'now') lit l'horloge réelle et pouvait publier « dans le futur ».
            'published_at' => now()->subDays(fake()->numberBetween(1, 365)),
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
