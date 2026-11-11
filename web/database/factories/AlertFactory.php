<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\ContactChannel;
use App\Enums\RoutingFallback;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Alert>
 */
class AlertFactory extends Factory
{
    protected $model = Alert::class;

    /**
     * Slugs de `violence_types` et de `needs` (DATABASE_DICTIONARY.md §1), tels que le prototype les emploie.
     *
     * @var list<string>
     */
    private const CATEGORIES = ['physique', 'psychologique', 'sexuelle', 'economique', 'numerique'];

    /** @var list<string> */
    private const NEEDS = [
        'soutien_psychologique',
        'soutien_juridique',
        'soutien_medical',
        'hebergement',
        'accompagnement',
        'securite',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([AlertType::Report, AlertType::Need]),
            'user_id' => null,
            'is_anonymous' => true,
            'pseudo' => null,
            'category' => fake()->randomElement(self::CATEGORIES),
            'description' => fake('fr_FR')->sentence(12),
            'needs' => fake()->randomElements(self::NEEDS, fake()->numberBetween(1, 3)),
            'zone_id' => Zone::factory(),
            'commune_id' => null,
            'lat' => null,
            'lng' => null,
            'location_accuracy' => null,
            'contact' => null,
            'status' => AlertStatus::New,
            'priority' => AlertPriority::Normal,
            'routed_to' => null,
            'routing_fallback' => null,
            'acknowledged_at' => null,
            'acknowledged_by' => null,
            'escalated_at' => null,
            'closed_at' => null,
            'cancelled_at' => null,
            'safe_at' => null,
            'source' => AlertSource::Web,
            'idempotency_key' => null,
            'device_id' => null,
        ];
    }

    /**
     * SOS géolocalisé : toujours critique, position chiffrée (coordonnées plausibles au Bénin).
     */
    public function sos(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => AlertType::Sos,
            'priority' => AlertPriority::Critical,
            'needs' => ['securite'],
        ])->located();
    }

    public function ofType(AlertType $type): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => $type,
        ]);
    }

    /**
     * Position GPS chiffrée (stockée en texte : `6.534550`).
     */
    public function located(?float $lat = null, ?float $lng = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'lat' => number_format($lat ?? fake()->randomFloat(6, 6.2, 12.4), 6, '.', ''),
            'lng' => number_format($lng ?? fake()->randomFloat(6, 0.8, 3.8), 6, '.', ''),
            'location_accuracy' => fake()->numberBetween(5, 120),
        ]);
    }

    /**
     * Alerte émise par un·e membre identifié·e (pseudo affiché).
     */
    public function fromMember(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user): array {
            $user ??= User::factory()->create();

            return [
                'user_id' => $user->id,
                'is_anonymous' => false,
                'pseudo' => $user->pseudo,
            ];
        });
    }

    /**
     * Coordonnée de rappel chiffrée (numéro ou e-mail).
     */
    public function withContact(?string $contact = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'contact' => $contact ?? '+229 01 '.fake()->numerify('## ## ## ##'),
        ]);
    }

    public function routedTo(?DirectoryContact $contact = null, RoutingFallback|string $fallback = RoutingFallback::RoleZone): static
    {
        return $this->state(fn (array $attributes): array => [
            'routed_to' => $contact?->id ?? DirectoryContact::factory(),
            'routing_fallback' => $fallback,
        ]);
    }

    public function withStatus(AlertStatus $status): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => $status,
            'closed_at' => $status === AlertStatus::Closed ? now() : null,
        ]);
    }

    public function priority(AlertPriority $priority): static
    {
        return $this->state(fn (array $attributes): array => [
            'priority' => $priority,
        ]);
    }

    public function acknowledged(?User $by = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AlertStatus::Acknowledged,
            'acknowledged_at' => now(),
            'acknowledged_by' => $by?->id ?? User::factory(),
        ]);
    }

    public function escalated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'escalated_at' => now(),
        ]);
    }

    /**
     * SOS annulé par la personne avant la fin du compte à rebours.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cancelled_at' => now(),
            'status' => AlertStatus::Closed,
            'closed_at' => now(),
        ]);
    }

    /**
     * Créée hors connexion sur un appareil puis synchronisée (clé d'idempotence).
     */
    public function fromDevice(?SyncDevice $device = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => AlertSource::SyncOffline,
            'idempotency_key' => (string) Str::uuid(),
            'device_id' => $device?->id ?? SyncDevice::factory(),
        ]);
    }

    /**
     * Date des faits déclarée (P5-04).
     */
    public function withOccurredOn(?CarbonInterface $date = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurred_on' => ($date ?? now()->subDays(3))->toDateString(),
        ]);
    }

    public function preferredChannel(ContactChannel $channel = ContactChannel::Call): static
    {
        return $this->state(fn (array $attributes): array => [
            'preferred_channel' => $channel,
        ]);
    }

    /**
     * Rappel RG-05 déjà envoyé au contact routé (Q27).
     */
    public function reminded(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reminded_at' => now(),
        ]);
    }

    /**
     * SOS dont la personne a indiqué « Je suis en sécurité » (statut traité).
     */
    public function safe(): static
    {
        return $this->state(fn (array $attributes): array => [
            'safe_at' => now(),
            'status' => AlertStatus::Handled,
        ]);
    }

    public function withTrackingCode(string $code): static
    {
        return $this->state(fn (array $attributes): array => [
            'tracking_code' => $code,
        ]);
    }

    /**
     * Clé d'idempotence ADR-32 (rejeu web ou mobile).
     */
    public function withIdempotencyKey(?string $key = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'idempotency_key' => $key ?? (string) Str::uuid(),
        ]);
    }

    public function source(AlertSource $source): static
    {
        return $this->state(fn (array $attributes): array => [
            'source' => $source,
        ]);
    }

    /**
     * Ancienneté arbitraire (utile pour tester l'escalade RG-05).
     */
    public function createdMinutesAgo(int $minutes): static
    {
        return $this->state(fn (array $attributes): array => [
            'created_at' => now()->subMinutes($minutes),
            'updated_at' => now()->subMinutes($minutes),
        ]);
    }
}
