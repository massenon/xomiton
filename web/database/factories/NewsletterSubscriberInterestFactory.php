<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterInterest;
use App\Models\NewsletterSubscriber;
use App\Models\NewsletterSubscriberInterest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriberInterest>
 */
class NewsletterSubscriberInterestFactory extends Factory
{
    protected $model = NewsletterSubscriberInterest::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscriber_id' => NewsletterSubscriber::factory(),
            'interest_id' => NewsletterInterest::factory(),
        ];
    }
}
