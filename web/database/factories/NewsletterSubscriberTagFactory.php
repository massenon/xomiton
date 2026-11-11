<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NewsletterSubscriber;
use App\Models\NewsletterSubscriberTag;
use App\Models\NewsletterTag;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NewsletterSubscriberTag>
 */
class NewsletterSubscriberTagFactory extends Factory
{
    protected $model = NewsletterSubscriberTag::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'subscriber_id' => NewsletterSubscriber::factory(),
            'tag_id' => NewsletterTag::factory(),
        ];
    }
}
