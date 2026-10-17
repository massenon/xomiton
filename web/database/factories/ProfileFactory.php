<?php

declare(strict_types=1);

namespace Database\Factories;

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
