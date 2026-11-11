<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Setting;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Setting>
 */
class SettingFactory extends Factory
{
    protected $model = Setting::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'demo_'.fake()->unique()->lexify('????????'),
            'group' => Setting::GROUP_GENERAL,
            'type' => Setting::TYPE_STRING,
            'value' => fake('fr_FR')->words(3, true),
            'is_public' => false,
            'updated_by' => null,
        ];
    }

    public function key(string $key): static
    {
        return $this->state(fn (array $attributes): array => ['key' => $key]);
    }

    public function inGroup(string $group): static
    {
        return $this->state(fn (array $attributes): array => ['group' => $group]);
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes): array => ['is_public' => true]);
    }

    public function bool(bool $value = true): static
    {
        return $this->state(fn (array $attributes): array => ['type' => Setting::TYPE_BOOL, 'value' => $value]);
    }

    public function int(int $value = 120): static
    {
        return $this->state(fn (array $attributes): array => ['type' => Setting::TYPE_INT, 'value' => $value]);
    }

    /**
     * @param  array<string, mixed>|null  $value
     */
    public function json(?array $value = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => Setting::TYPE_JSON,
            'value' => $value ?? ['on' => true, 'method' => 'sms'],
        ]);
    }

    /**
     * Clé chiffrée (mot de passe SMTP…), jamais publique.
     */
    public function secret(?string $value = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'group' => Setting::GROUP_SMTP,
            'type' => Setting::TYPE_SECRET,
            'value' => $value ?? fake()->password(12),
            'is_public' => false,
        ]);
    }
}
