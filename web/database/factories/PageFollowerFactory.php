<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Page;
use App\Models\PageFollower;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PageFollower>
 */
class PageFollowerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'user_id' => User::factory(),
        ];
    }
}
