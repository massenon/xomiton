<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OnCallShift;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnCallShift>
 */
class OnCallShiftFactory extends Factory
{
    protected $model = OnCallShift::class;

    /**
     * Plage de garde admin de 8 h en cours (commencée il y a une heure), nationale.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = now()->subHour()->startOfHour();

        return [
            'user_id' => User::factory(),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHours(8),
            'kind' => OnCallShift::KIND_ADMIN,
            'zone_id' => null,
            'notes' => null,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->getKey()]);
    }

    public function between(CarbonInterface $start, CarbonInterface $end): static
    {
        return $this->state(fn (array $attributes): array => [
            'starts_at' => $start,
            'ends_at' => $end,
        ]);
    }

    /** Garde de nuit : de 22 h la veille à 6 h le jour donné (par défaut aujourd'hui). */
    public function night(?CarbonInterface $day = null): static
    {
        $day ??= now();
        $end = $day->copy()->setTime(6, 0);

        return $this->between($end->copy()->subHours(8), $end);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes): array => ['kind' => OnCallShift::KIND_ADMIN]);
    }

    public function focal(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => OnCallShift::KIND_FOCAL,
            'zone_id' => $zone?->getKey(),
        ]);
    }

    public function inZone(Zone $zone): static
    {
        return $this->state(fn (array $attributes): array => ['zone_id' => $zone->getKey()]);
    }

    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => $notes ?? fake('fr_FR')->sentence(6),
        ]);
    }

    /** Plage terminée (hier). */
    public function past(): static
    {
        return $this->between(now()->subDay()->setTime(8, 0), now()->subDay()->setTime(16, 0));
    }

    /** Plage à venir (demain). */
    public function upcoming(): static
    {
        return $this->between(now()->addDay()->setTime(8, 0), now()->addDay()->setTime(16, 0));
    }
}
