<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    /**
     * Actions du journal du prototype (`journal.json` : `action`).
     *
     * @var list<string>
     */
    private const ACTIONS = [
        'Paramètres', 'Inscription', 'Assistant', 'Synchronisation', 'Mise à jour', 'Sauvegarde', 'Maintenance',
        'Optimisation', 'Journal', 'Modération', 'Alerte', 'Collecte', 'Template e-mail', 'Newsletter', 'Utilisateur',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'actor' => fake()->safeEmail(),
            'action' => fake()->randomElement(self::ACTIONS),
            'subject_type' => null,
            'subject_id' => null,
            'detail' => fake('fr_FR')->sentence(8),
            'ip' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'created_at' => fake()->dateTimeBetween('-60 days'),
        ];
    }

    public function configure(): static
    {
        // Sans auteur·rice connu·e, le libellé conservé est « anonyme », comme le journal du prototype.
        return $this->afterMaking(function (AuditLog $log): void {
            if ($log->user_id === null && $log->actor === null) {
                $log->actor = AuditLog::ACTOR_ANONYMOUS;
            }
        });
    }

    public function ofAction(string $action): static
    {
        return $this->state(fn (array $attributes): array => ['action' => $action]);
    }

    public function anonymous(): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => null, 'actor' => null]);
    }

    public function system(): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => null,
            'actor' => AuditLog::ACTOR_SYSTEM,
            'ip' => null,
            'user_agent' => null,
        ]);
    }

    public function on(Model $subject): static
    {
        return $this->state(fn (array $attributes): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    /**
     * Entrée plus ancienne que la rétention (`xomiton.retention.audit_log_days`), donc à purger.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => now()->subDays((int) config('xomiton.retention.audit_log_days', 90) + 10),
        ]);
    }
}
