<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertStatusChange>
 */
class AlertStatusChangeFactory extends Factory
{
    protected $model = AlertStatusChange::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'user_id' => User::factory(),
            'from_status' => AlertStatus::New,
            'to_status' => AlertStatus::Acknowledged,
        ];
    }

    /**
     * Première entrée : création de l'alerte par le système.
     */
    public function initial(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'from_status' => null,
            'to_status' => AlertStatus::New,
        ]);
    }

    public function transition(AlertStatus $from, AlertStatus $to): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_status' => $from,
            'to_status' => $to,
        ]);
    }
}
