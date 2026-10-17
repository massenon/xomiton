<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BackupKind;
use App\Enums\JobStatus;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Backup>
 */
class BackupFactory extends Factory
{
    protected $model = Backup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $at = Carbon::instance(fake()->unique()->dateTimeBetween('-6 months'));
        $kind = BackupKind::Manual;
        $name = Backup::nameFor($kind, $at);
        $tables = [
            ['name' => 'users', 'rows' => fake()->numberBetween(50, 500)],
            ['name' => 'posts', 'rows' => fake()->numberBetween(100, 5000)],
            ['name' => 'alerts', 'rows' => fake()->numberBetween(10, 300)],
            ['name' => 'cases', 'rows' => fake()->numberBetween(10, 200)],
        ];

        return [
            'name' => $name,
            'kind' => $kind,
            'size_bytes' => fake()->numberBetween(50_000, 20_000_000),
            'tables' => $tables,
            'storage_disk' => Backup::DEFAULT_DISK,
            'storage_path' => 'backups/'.$name.'.sql.gz',
            'checksum' => hash('sha256', $name),
            'status' => JobStatus::Done,
            'created_by' => User::factory(),
            'restored_at' => null,
            'restored_by' => null,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    public function ofKind(BackupKind $kind): static
    {
        return $this->state(function (array $attributes) use ($kind): array {
            $name = Backup::nameFor($kind, Carbon::parse($attributes['created_at']));

            return [
                'kind' => $kind,
                'name' => $name,
                'storage_path' => 'backups/'.$name.'.sql.gz',
                'created_by' => $kind === BackupKind::Auto ? null : $attributes['created_by'],
            ];
        });
    }

    public function status(JobStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'size_bytes' => $status === JobStatus::Done ? $attributes['size_bytes'] : 0,
            'tables' => $status === JobStatus::Done ? $attributes['tables'] : [],
            'checksum' => $status === JobStatus::Done ? $attributes['checksum'] : null,
        ]);
    }

    public function restored(): static
    {
        return $this->state(fn (array $attributes): array => [
            'restored_at' => now(),
            'restored_by' => User::factory(),
        ]);
    }
}
