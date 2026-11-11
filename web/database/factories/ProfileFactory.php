<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AgeRange;
use App\Enums\AllyRole;
use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'pronouns' => fake()->randomElement(['elle', 'il', 'iel', null]),
            'age_range' => fake()->randomElement(['15-17', '18-24', '25-34', '35-49', '50+']),
            // Âge saisi à l'inscription, cohérent avec la tranche (App\Enums\AgeRange::fromAge).
            'age' => static fn (array $attributes): ?int => self::ageIn($attributes['age_range']),
            'country_code' => 'BJ',
            'city' => fake()->randomElement(['Cotonou', 'Porto-Novo', 'Parakou', 'Abomey-Calavi', null]),
            'member_type' => MemberType::Member,
            'gender_identity' => null,
            'orientation' => null,
            'bio' => fake()->optional(0.7)->sentence(12),
            'completion' => fake()->numberBetween(20, 100),
            'avatar_path' => null,
            'cover_path' => null,
            'discreet_mode' => false,
            'trusted_contact_phone' => null,
            'trusted_contact_name' => null,
            'website' => null,
            'visibility' => ProfileVisibility::Community,
            'show_zone' => true,
            'searchable' => true,
        ];
    }

    /**
     * Un âge au hasard dans la tranche donnée ; null si la tranche est inconnue.
     */
    private static function ageIn(mixed $range): ?int
    {
        $range = $range instanceof AgeRange ? $range : AgeRange::tryFrom((string) $range);

        if ($range === null) {
            return null;
        }

        return fake()->numberBetween($range->lowerBound(), $range->upperBound() ?? 70);
    }

    public function peer(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_type' => MemberType::Peer,
        ]);
    }

    public function ally(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_type' => MemberType::Ally,
        ]);
    }

    /**
     * Allié·e inscrit·e avec un statut (inscription en 3 étapes) ; précision seulement pour « autre ».
     */
    public function allyWithRole(AllyRole $role = AllyRole::Lawyer, ?string $other = null): static
    {
        return $this->state(fn (array $attributes) => [
            'member_type' => MemberType::Ally,
            'ally_role' => $role->value,
            'ally_role_other' => $role === AllyRole::Other ? ($other ?? 'Formatrice') : null,
        ]);
    }

    public function resourcePerson(): static
    {
        return $this->state(fn (array $attributes) => [
            'member_type' => MemberType::Resource,
        ]);
    }

    /**
     * Profil visible sans connexion.
     */
    public function publiclyVisible(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => ProfileVisibility::Public,
        ]);
    }

    /**
     * Mode discret : profil privé, zone masquée, absent des suggestions.
     */
    public function discreet(): static
    {
        return $this->state(fn (array $attributes) => [
            'discreet_mode' => true,
            'visibility' => ProfileVisibility::Private,
            'show_zone' => false,
            'searchable' => false,
        ]);
    }

    /**
     * Profil avec données sensibles renseignées (consentement sensitive_data supposé donné).
     */
    public function withSensitiveData(): static
    {
        return $this->state(fn (array $attributes) => [
            'gender_identity' => fake()->randomElement(['femme', 'femme trans', 'non-binaire']),
            'orientation' => fake()->randomElement(['lesbienne', 'bisexuelle', 'queer', 'hétérosexuelle']),
            'trusted_contact_name' => fake()->firstName('female'),
            'trusted_contact_phone' => '+229 01 '.fake()->numerify('## ## ## ##'),
        ]);
    }
}
