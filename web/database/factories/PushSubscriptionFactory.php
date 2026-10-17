<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PushSubscription>
 */
class PushSubscriptionFactory extends Factory
{
    protected $model = PushSubscription::class;

    /**
     * Par défaut : abonnement Web Push (navigateur).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $endpoint = 'https://fcm.googleapis.com/fcm/send/'.Str::random(120);

        return [
            'user_id' => User::factory(),
            'kind' => PushSubscription::KIND_WEBPUSH,
            'endpoint' => $endpoint,
            'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
            'public_key' => Str::random(87),
            'auth_token' => Str::random(22),
            'device_label' => fake()->randomElement(['Chrome · Android', 'Firefox · Windows', 'Safari · iPhone']),
            'last_used_at' => null,
        ];
    }

    /** Jeton FCM de l'application Flutter : pas de clés VAPID. */
    public function fcm(): static
    {
        return $this->state(function (array $attributes): array {
            $token = Str::random(152);

            return [
                'kind' => PushSubscription::KIND_FCM,
                'endpoint' => $token,
                'endpoint_hash' => PushSubscription::hashEndpoint($token),
                'public_key' => null,
                'auth_token' => null,
                'device_label' => 'Android 13 · Redmi 10',
            ];
        });
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->getKey()]);
    }
}
