<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\MaintenanceWindow;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MaintenanceWindow>
 */
class MaintenanceWindowFactory extends Factory
{
    protected $model = MaintenanceWindow::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('+1 day', '+2 weeks');

        return [
            'starts_at' => $start,
            'ends_at' => (clone $start)->modify('+2 hours'),
            'message' => 'Maintenance planifiée : la plateforme sera indisponible pendant environ deux heures.',
            'is_active' => true,
            'created_by' => User::factory(),
        ];
    }

    /**
     * Fenêtre en cours à l'instant présent.
     */
    public function ongoing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);
    }

    public function past(): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => now()->subDays(3),
            'ends_at' => now()->subDays(3)->addHours(2),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => ['is_active' => false]);
    }

    public function withoutMessage(): static
    {
        return $this->state(fn (array $attributes): array => ['message' => null]);
    }
}
