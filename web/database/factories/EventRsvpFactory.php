<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventRsvp>
 */
class EventRsvpFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'user_id' => User::factory(),
            'status' => RsvpStatus::Going,
            'reminder_opt_in' => true,
        ];
    }

    public function interested(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RsvpStatus::Interested,
        ]);
    }

    public function declined(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RsvpStatus::Declined,
            'reminder_opt_in' => false,
        ]);
    }

    public function withoutReminder(): static
    {
        return $this->state(fn (array $attributes) => [
            'reminder_opt_in' => false,
        ]);
    }
}
