<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SocialNetwork;
use App\Models\SocialChannel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use LogicException;

/**
 * @extends Factory<SocialChannel>
 */
class SocialChannelFactory extends Factory
{
    protected $model = SocialChannel::class;

    /**
     * Le réseau est unique : par défaut, le premier des six qui n'existe pas encore en base.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'network' => fn (): SocialNetwork => $this->freeNetwork(),
            'label' => fn (array $attributes): string => $this->resolveNetwork($attributes)->label(),
            'icon' => fn (array $attributes): string => $this->resolveNetwork($attributes)->defaultIcon(),
            'color' => fn (array $attributes): string => $this->resolveNetwork($attributes)->defaultColor(),
            'supports_api' => fn (array $attributes): bool => $this->resolveNetwork($attributes)->supportsApi(),
            'is_connected' => false,
            'account_handle' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'external_id' => null,
            'connected_at' => null,
            'connected_by' => null,
            'last_error' => null,
        ];
    }

    public function network(SocialNetwork $network): static
    {
        return $this->state(fn (array $attributes): array => ['network' => $network]);
    }

    /** Compte relié (jeton chiffré, valide 60 jours) par l'administrateur·rice donné·e ou un·e nouveau·elle. */
    public function connected(?User $user = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_connected' => true,
            'account_handle' => '@xomiton.bj',
            'access_token' => 'tok_'.fake()->sha256(),
            'refresh_token' => 'rtk_'.fake()->sha256(),
            'token_expires_at' => now()->addDays(60),
            'external_id' => (string) fake()->numerify('##############'),
            'connected_at' => now()->subDay(),
            'connected_by' => $user?->getKey() ?? User::factory(),
        ]);
    }

    /** Compte relié dont le jeton est échu : à reconnecter avant publication. */
    public function expired(): static
    {
        return $this->connected()->state(fn (array $attributes): array => [
            'token_expires_at' => now()->subDay(),
            'last_error' => 'Jeton expiré',
        ]);
    }

    /** Premier réseau sans passerelle en base ; au-delà des six, l'appelant doit préciser `network()`. */
    private function freeNetwork(): SocialNetwork
    {
        $used = SocialChannel::query()->pluck('network')
            ->map(static fn (SocialNetwork|string $network): string => $network instanceof SocialNetwork ? $network->value : $network)
            ->all();

        foreach (SocialNetwork::cases() as $network) {
            if (! in_array($network->value, $used, true)) {
                return $network;
            }
        }

        throw new LogicException('Les six réseaux ont déjà une passerelle : précisez network() ou réutilisez une passerelle existante.');
    }

    /** @param  array<string, mixed>  $attributes */
    private function resolveNetwork(array $attributes): SocialNetwork
    {
        return $attributes['network'] instanceof SocialNetwork
            ? $attributes['network']
            : SocialNetwork::from((string) $attributes['network']);
    }
}
