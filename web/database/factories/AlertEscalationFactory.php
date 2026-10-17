<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\DirectoryContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AlertEscalation>
 */
class AlertEscalationFactory extends Factory
{
    protected $model = AlertEscalation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alert_id' => Alert::factory()->sos(),
            'from_contact_id' => DirectoryContact::factory()->focalZone(),
            'to_contact_id' => DirectoryContact::factory()->focalNational(),
            'reason' => AlertEscalation::REASON_NO_ACK,
        ];
    }

    public function manual(): static
    {
        return $this->state(fn (array $attributes): array => [
            'reason' => AlertEscalation::REASON_MANUAL,
        ]);
    }

    /**
     * Alerte qui n'avait encore aucun contact routé.
     */
    public function fromNobody(): static
    {
        return $this->state(fn (array $attributes): array => [
            'from_contact_id' => null,
        ]);
    }
}
