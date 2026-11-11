<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CccKind;
use App\Models\CccTool;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CccTool>
 */
class CccToolFactory extends Factory
{
    protected $model = CccTool::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(4), '.'));

        return [
            'kind' => CccKind::Manual,
            'key' => 'm'.fake()->unique()->numberBetween(10, 999),
            'title' => $title,
            'description' => fake('fr_FR')->sentence(10),
            'image_path' => null,
            'pages' => null,
            'chapters' => fake('fr_FR')->sentences(4),
            'duration_minutes' => fake()->randomElement([45, 50, 60]),
            'position' => fake()->numberBetween(1, 20),
            'is_published' => true,
        ];
    }

    public function imageBox(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => CccKind::ImageBox,
            'key' => 'bi'.fake()->unique()->numberBetween(10, 999),
            'image_path' => 'assets/images/xomiton/hero-09.jpg',
            'pages' => [[
                'image' => 'assets/images/xomiton/hero-09.jpg',
                'caption' => fake('fr_FR')->sentence(12),
                'alt' => $attributes['title'],
            ]],
            'chapters' => null,
            'duration_minutes' => null,
        ]);
    }

    public function comic(): static
    {
        return $this->state(function (array $attributes): array {
            $pages = [];

            foreach (fake('fr_FR')->sentences(4) as $index => $caption) {
                $pages[] = [
                    'image' => 'assets/images/xomiton/hero-10.jpg',
                    'caption' => $caption,
                    'alt' => $attributes['title'].' – planche '.($index + 1),
                ];
            }

            return [
                'kind' => CccKind::Comic,
                'key' => 'bd'.fake()->unique()->numberBetween(10, 999),
                'image_path' => 'assets/images/xomiton/hero-10.jpg',
                'pages' => $pages,
                'chapters' => null,
                'duration_minutes' => null,
            ];
        });
    }

    public function unpublished(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => false]);
    }
}
