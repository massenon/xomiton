<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MailOutbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailOutbox>
 */
class MailOutboxFactory extends Factory
{
    protected $model = MailOutbox::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $text = fake()->paragraph();

        return [
            'to' => fake()->safeEmail(),
            'from_address' => 'no-reply@xomiton.bj',
            'from_name' => 'Xomiton',
            'reply_to' => 'contact@xomiton.bj',
            'subject' => fake()->sentence(5),
            'html' => '<p>'.$text.'</p>',
            'text' => $text,
            'template_slug' => null,
            'attempts' => 0,
            'sent_at' => null,
            'last_error' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempts' => 1,
            'sent_at' => now()->subMinutes(fake()->numberBetween(1, 600)),
        ]);
    }

    /** Toutes les tentatives consommées : plus retenté automatiquement. */
    public function exhausted(): static
    {
        return $this->state(fn (array $attributes): array => [
            'attempts' => MailOutbox::MAX_ATTEMPTS,
            'last_error' => 'Connection refused',
        ]);
    }
}
