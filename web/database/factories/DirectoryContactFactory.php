<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DirectoryRole;
use App\Models\Commune;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Contacts d'annuaire fictifs. Les coordonnées réelles du prototype (`annuaire.json`) ne sont
 * volontairement pas reprises ; seules les organisations et disponibilités servent de vocabulaire.
 *
 * @extends Factory<DirectoryContact>
 */
class DirectoryContactFactory extends Factory
{
    protected $model = DirectoryContact::class;

    /** @var list<string> */
    private const ORGS = [
        'Xomiton',
        'Cabinet partenaire',
        'Barreau – bénévole',
        'Centre de promotion sociale',
        'Centre de santé partenaire',
        'Clinique partenaire',
        'Réseau des pairs – Sud',
        'Réseau Xomiton – Borgou',
        'Réseau Xomiton – Ouémé',
    ];

    /** @var list<string> */
    private const AVAILABILITIES = [
        '24/7, 9h-17h',
        'Lun-Ven 8h-17h',
        'Lun-Ven 9h-16h',
        'Lun-Sam 8h-18h',
        'Sur rendez-vous',
    ];

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $phone = '+229 01 '.fake()->numerify('## ## ## ##');

        return [
            'name' => Str::upper(fake('fr_FR')->lastName()).' '.fake('fr_FR')->firstName(),
            'role' => fake()->randomElement(DirectoryRole::cases()),
            'org' => fake()->randomElement(self::ORGS),
            'phone' => $phone,
            'email' => fake()->unique()->safeEmail(),
            'whatsapp' => $phone,
            'zone_id' => Zone::factory(),
            'commune_id' => null,
            'availability' => fake()->randomElement(self::AVAILABILITIES),
            'is_active' => true,
            'is_public' => true,
            'is_national_focal' => false,
            'user_id' => null,
            'notes' => null,
        ];
    }

    /**
     * Point focal national (RG-04 dernier recours avant « premier actif », destinataire de l'escalade RG-05).
     */
    public function focalNational(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => DirectoryRole::PointFocal,
            'org' => 'Xomiton',
            'zone_id' => null,
            'commune_id' => null,
            'availability' => '24/7',
            'is_national_focal' => true,
            'is_active' => true,
        ]);
    }

    /**
     * Point focal d'un département.
     */
    public function focalZone(?Zone $zone = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => DirectoryRole::PointFocal,
            'zone_id' => $zone?->id ?? Zone::factory(),
            'is_national_focal' => false,
        ]);
    }

    public function role(DirectoryRole $role): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => $role,
        ]);
    }

    public function inZone(Zone $zone, ?Commune $commune = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'zone_id' => $zone->id,
            'commune_id' => $commune?->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Contact de routage interne, absent de l'annuaire public.
     */
    public function hidden(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_public' => false,
        ]);
    }

    /**
     * Contact rattaché à un compte membre (alertes « routées vers moi »).
     */
    public function linkedTo(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function withNotes(?string $notes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'notes' => $notes ?? fake('fr_FR')->sentence(8),
        ]);
    }
}
