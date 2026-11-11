<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CaseForm>
 */
class CaseFormFactory extends Factory
{
    protected $model = CaseForm::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $fr = fake('fr_FR');
        // Deux services demandés parmi le catalogue, sous la forme canonique {clé: bool} (CaseForm::SERVICES).
        $services = CaseForm::normalizeServices(array_fill_keys($fr->randomElements(CaseForm::SERVICES, 2), true));

        return [
            'case_id' => CaseRecord::factory(),
            'reference' => fn (): string => CaseForm::nextReference(),
            'created_by' => User::factory(),
            'identification' => [
                'pseudo' => $fr->firstName('female'),
                'sexe' => 'femme',
                'age' => $fr->randomElement(['15-17', '18-24', '25-34', '35-49']),
                'population' => 'Femmes lesbiennes',
                'zone' => 'Littoral',
                'commune' => 'Cotonou',
            ],
            'facts_date' => $fr->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'facts_place' => $fr->city(),
            'facts_type' => $fr->randomElement(['physique', 'psychologique', 'sexuelle', 'economique', 'numerique']),
            'facts_author' => $fr->optional(0.6)->randomElement(['Conjoint', 'Voisin', 'Membre de la famille', 'Inconnu']),
            'facts_description' => $fr->paragraph(3),
            'services' => $services,
            'services_note' => $fr->optional(0.4)->sentence(10),
            'referral_contact_id' => null,
            'referral_label' => $fr->randomElement(['Point focal', 'Juriste', 'Psychologue', 'Gynécologue']),
            'referral_date' => $fr->dateTimeBetween('-1 month', '+1 week')->format('Y-m-d'),
            'referral_reason' => $fr->sentence(8),
            'referral_urgency' => $fr->randomElement(['normale', 'haute', 'critique']),
            'consent_given' => true,
            'consent_mode' => $fr->randomElement(['ecrit', 'oral']),
            'consent_sharing' => false,
            'consent_signatory' => null,
        ];
    }

    /** Consentement écrit au partage avec un partenaire. */
    public function shareable(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consent_given' => true,
            'consent_mode' => 'ecrit',
            'consent_sharing' => true,
            'consent_signatory' => fake('fr_FR')->name(),
        ]);
    }

    public function withoutConsent(): static
    {
        return $this->state(fn (array $attributes): array => [
            'consent_given' => false,
            'consent_sharing' => false,
            'consent_signatory' => null,
        ]);
    }
}
