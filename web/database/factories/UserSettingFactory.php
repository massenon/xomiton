<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Models\UserSetting;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<UserSetting>
 */
class UserSettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'theme' => fake()->randomElement(['light', 'dark', 'auto']),
            'language' => 'fr',
            'notification_prefs' => UserSetting::defaultNotificationPrefs(),
            'mask_sensitive_notifications' => false,
            'notification_digest' => 'never',
            'sos_share_gps' => true,
            'pin_enabled' => false,
            'pin_hash' => null,
            'neutral_tab_title' => false,
        ];
    }

    /**
     * Mode discret complet : notifications masquées, titre d'onglet neutre, code PIN, pas de GPS au SOS.
     */
    public function discreet(string $pin = '1234'): static
    {
        return $this->state(fn (array $attributes) => [
            'mask_sensitive_notifications' => true,
            'neutral_tab_title' => true,
            'pin_enabled' => true,
            'pin_hash' => Hash::make($pin),
            'sos_share_gps' => false,
        ]);
    }

    /**
     * Résumé d'activité hebdomadaire (valeur par défaut du prototype : « hebdo »).
     */
    public function weeklyDigest(): static
    {
        return $this->state(fn (array $attributes) => [
            'notification_digest' => 'weekly',
        ]);
    }
}
