<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alert;
use App\Models\AlertAttachment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AlertAttachment>
 */
class AlertAttachmentFactory extends Factory
{
    protected $model = AlertAttachment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory(),
            'kind' => 'images',
            'disk' => 'local',
            'path' => 'images/'.now()->format('Y/m').'/'.strtolower((string) Str::ulid()).'.webp',
            'mime' => 'image/webp',
            'size' => fake()->numberBetween(20_000, 900_000),
        ];
    }

    public function pdf(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => 'documents',
            'path' => 'documents/'.now()->format('Y/m').'/'.strtolower((string) Str::ulid()).'.pdf',
            'mime' => 'application/pdf',
        ]);
    }
}
