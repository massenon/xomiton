<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    protected $model = Zone::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $name = Str::ucfirst(fake('fr_FR')->unique()->departmentName());

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'position' => fake()->numberBetween(1, 99),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }
}
