<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ModerationKind;
use App\Enums\ModerationStatus;
use App\Models\Comment;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @extends Factory<ModerationReport>
 */
class ModerationReportFactory extends Factory
{
    protected $model = ModerationReport::class;

    /**
     * Motifs du prototype (`moderation_reports.json`).
     *
     * @var list<string>
     */
    private const REASONS = [
        'Homophobie',
        'Insultes',
        'Harcèlement',
        'Données personnelles',
        'Spam',
        'Désinformation santé',
        'Usurpation',
        'Menaces',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'kind' => ModerationKind::Post,
            'subject_type' => (new Post)->getMorphClass(),
            'subject_id' => Post::factory(),
            'excerpt' => Str::limit(fake('fr_FR')->sentence(12), 255, ''),
            'reported_user_id' => null,
            'reporter_user_id' => null,
            'reporter_label' => 'Membre anonyme',
            'reason' => fake()->randomElement(self::REASONS),
            'details' => null,
            'status' => ModerationStatus::Pending,
            'action' => null,
            'handled_by' => null,
            'handled_at' => null,
        ];
    }

    /**
     * La personne signalée est l'auteur·rice de la cible (ou le compte visé), sauf valeur explicite :
     * un signalement et son contenu désignent la même personne.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ModerationReport $report): void {
            if ($report->reported_user_id !== null) {
                return;
            }

            $subject = $report->subject;

            $report->reported_user_id = match (true) {
                $subject instanceof User => $subject->getKey(),
                $subject !== null && $subject->getAttribute('user_id') !== null => (int) $subject->getAttribute('user_id'),
                default => User::factory()->create()->getKey(),
            };
        });
    }

    /** Signalement visant un contenu ou un compte existant ; la nature est déduite de sa classe. */
    public function forSubject(Model $subject, ?ModerationKind $kind = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => $kind ?? ModerationKind::forSubjectClass($subject::class) ?? ModerationKind::Post,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    public function onComment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => ModerationKind::Comment,
            'subject_type' => (new Comment)->getMorphClass(),
            'subject_id' => Comment::factory(),
        ]);
    }

    /** Signalement d'un compte : la cible est l'utilisateur·rice signalé·e. */
    public function onUser(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            $user ??= User::factory()->create();

            return [
                'kind' => ModerationKind::User,
                'subject_type' => $user->getMorphClass(),
                'subject_id' => $user->getKey(),
                'reported_user_id' => $user->getKey(),
                'excerpt' => null,
            ];
        });
    }

    /** Signalé·e par un·e membre identifié·e. */
    public function reportedBy(?User $reporter = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'reporter_user_id' => $reporter?->id ?? User::factory(),
            'reporter_label' => null,
        ]);
    }

    /** Signalement automatique du filtre de mots interdits. */
    public function bySystem(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reporter_user_id' => null,
            'reporter_label' => 'Système (mots interdits)',
            'reason' => 'Spam',
        ]);
    }

    public function withDetails(?string $details = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'details' => $details ?? fake('fr_FR')->sentence(20),
        ]);
    }

    public function handled(string $action = ModerationReport::ACTION_HIDE, ?User $moderator = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ModerationStatus::Handled,
            'action' => $action,
            'handled_by' => $moderator?->id ?? User::factory(),
            'handled_at' => now(),
        ]);
    }

    public function rejected(?User $moderator = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ModerationStatus::Rejected,
            'action' => ModerationReport::ACTION_NONE,
            'handled_by' => $moderator?->id ?? User::factory(),
            'handled_at' => now(),
        ]);
    }
}
