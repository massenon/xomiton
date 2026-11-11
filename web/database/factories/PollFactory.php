<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PostTag;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\Post;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    /**
     * Libellés d'options : les 4 premiers sont ceux du sondage de démonstration du prototype
     * (posts.json, « Quel thème pour la prochaine causerie ? ») ; les 2 derniers complètent jusqu'à
     * Poll::MAX_OPTIONS pour les tests.
     *
     * @var list<string>
     */
    public const DEMO_OPTIONS = [
        'Santé sexuelle et reproductive',
        'Droits et recours juridiques',
        'Bien-être et santé mentale',
        'Sécurité numérique',
        'Autonomie économique',
        'Vie affective et consentement',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'post_id' => Post::factory()->tagged(PostTag::Poll),
            'question' => 'Quel thème pour la prochaine causerie ?',
            'multiple' => false,
            'results_before_vote' => false,
            'ends_at' => now()->addDays(7),
            'votes_count' => 0,
            'voters_count' => 0,
            'closed_at' => null,
        ];
    }

    /**
     * Crée de 2 à 6 options (RG-09) à partir des libellés du prototype.
     */
    public function withOptions(int $count = 4): static
    {
        $count = max(Poll::MIN_OPTIONS, min(Poll::MAX_OPTIONS, $count));

        return $this->afterCreating(function (Poll $poll) use ($count): void {
            foreach (array_slice(self::DEMO_OPTIONS, 0, $count) as $position => $label) {
                PollOption::factory()->create([
                    'poll_id' => $poll->id,
                    'label' => $label,
                    'position' => $position + 1,
                ]);
            }
        });
    }

    public function multiple(): static
    {
        return $this->state(fn (array $attributes) => [
            'multiple' => true,
        ]);
    }

    public function resultsBeforeVote(): static
    {
        return $this->state(fn (array $attributes) => [
            'results_before_vote' => true,
        ]);
    }

    /**
     * Échéance dépassée.
     */
    public function ended(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subDay(),
        ]);
    }

    /**
     * Clos manuellement par l'auteur·rice.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'closed_at' => now()->subHour(),
        ]);
    }
}
