<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PollVote>
 */
class PollVoteFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'poll_option_id' => PollOption::factory(),
            // poll_id est dénormalisé : toujours celui de l'option votée.
            'poll_id' => fn (array $attributes) => PollOption::query()->whereKey($attributes['poll_option_id'])->value('poll_id'),
            'user_id' => User::factory(),
        ];
    }

    public function forOption(PollOption $option): static
    {
        return $this->state(fn (array $attributes) => [
            'poll_option_id' => $option->id,
            'poll_id' => $option->poll_id,
        ]);
    }
}
