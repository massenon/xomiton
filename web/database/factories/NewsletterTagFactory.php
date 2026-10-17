<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterTag>
 */
class NewsletterTagFactory extends Factory
{
    protected $model = NewsletterTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::ucfirst(fake()->unique()->word());

        return [
            'slug' => Str::slug($label),
            'label' => $label,
        ];
    }
}
