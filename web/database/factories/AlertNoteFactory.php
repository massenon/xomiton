<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alert;
use App\Models\AlertNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertNote>
 */
class AlertNoteFactory extends Factory
{
    protected $model = AlertNote::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'user_id' => User::factory(),
            'body' => fake('fr_FR')->sentence(10),
        ];
    }

    /**
     * Note dont l'auteur·rice a supprimé son compte.
     */
    public function orphan(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
        ]);
    }
}
