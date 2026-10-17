<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterInterest;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<NewsletterInterest>
 */
class NewsletterInterestFactory extends Factory
{
    protected $model = NewsletterInterest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $label = Str::ucfirst(fake()->unique()->words(2, true));

        return [
            'slug' => Str::slug($label),
            'label' => $label,
        ];
    }
}
