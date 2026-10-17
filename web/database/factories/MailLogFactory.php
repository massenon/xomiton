<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MailTransport;
use App\Models\MailLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailLog>
 */
class MailLogFactory extends Factory
{
    protected $model = MailLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'to' => fake()->safeEmail(),
            'subject' => fake()->sentence(5),
            'template_slug' => fake()->randomElement(['code-verification', 'bienvenue', 'alerte-routee', null]),
            'context' => fake()->randomElement(['auth', 'newsletter', 'alerte', 'test', null]),
            'transport' => MailTransport::Smtp,
            'ok' => true,
            'error' => null,
            'message_id' => '<'.fake()->uuid().'@xomiton.bj>',
            'created_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ];
    }

    public function failed(?string $error = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'ok' => false,
            'error' => $error ?? 'SMTP connect() failed',
            'message_id' => null,
        ]);
    }

    /** Déposé dans `mail_outbox` faute de SMTP. */
    public function viaOutbox(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transport' => MailTransport::Outbox,
            'message_id' => null,
        ]);
    }

    /** Adresse invalide : aucun transport (prototype `-`). */
    public function invalidAddress(): static
    {
        return $this->state(fn (array $attributes): array => [
            'transport' => MailTransport::None,
            'ok' => false,
            'error' => 'Adresse e-mail invalide',
            'message_id' => null,
        ]);
    }

    /** Au-delà de la rétention de 180 jours : retenu par `expired()`. */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => now()->subDays(MailLog::RETENTION_DAYS + fake()->numberBetween(1, 60)),
        ]);
    }
}
