<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\BdConversationOrigin;
use App\Enums\BdConversationStatus;
use App\Models\BdBubble;
use App\Models\BdConversation;
use App\Models\BdPanel;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Conversation en BD de test (P20-03) : modèle publié, 3 cases d'une bulle de parole chacune (`withPanels`).
 *
 * @extends Factory<BdConversation>
 */
class BdConversationFactory extends Factory
{
    protected $model = BdConversation::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $title = Str::ucfirst(rtrim(fake('fr_FR')->unique()->sentence(3), '.'));

        return [
            'ulid' => (string) Str::ulid(),
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(10, 99999),
            'origin' => BdConversationOrigin::Template,
            'is_anonymous' => true,
            'title' => $title,
            'summary' => 'Une conversation courte pour comprendre une situation et savoir comment réagir avec bienveillance.',
            'theme' => 'consentement',
            'category' => 'relations et consentement',
            'audience' => 'tout public',
            'key_message' => 'Un non n’a pas besoin de justification.',
            'discussion_questions' => ['Qu’a dit le personnage ?', 'Que pensait-il vraiment ?'],
            'status' => BdConversationStatus::Published,
            'position' => fake()->numberBetween(1, 50),
            'published_at' => now()->subDay(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => BdConversationStatus::Draft, 'published_at' => null]);
    }

    public function withPanels(int $count = 3): static
    {
        return $this->afterCreating(function (BdConversation $conversation) use ($count): void {
            for ($n = 1; $n <= $count; $n++) {
                $panel = BdPanel::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'position' => $n,
                    'scene_description' => 'Scène '.$n.' : deux amies discutent dans une cour, le soir.',
                ]);
                BdBubble::query()->create([
                    'panel_id' => $panel->getKey(),
                    'position' => 1,
                    'type' => 'parole',
                    'character_key' => 'afi',
                    'text' => 'Bonjour, case '.$n.'.',
                    'x' => 4,
                    'y' => 4,
                    'tail_target' => 'personnage',
                    'tail_x' => 25,
                    'tail_y' => 60,
                ]);
            }
        });
    }
}
