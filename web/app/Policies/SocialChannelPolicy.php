<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\SocialChannel;
use App\Models\User;

/**
 * Passerelles réseaux sociaux (D4-bis) : `gateways.connect` relie ou détache les comptes de l'organisation,
 * `gateways.publish` lit les canaux pour alimenter la file de publication.
 */
final class SocialChannelPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gateways.connect') || $user->can('gateways.publish');
    }

    public function view(User $user, SocialChannel $channel): bool
    {
        return $this->viewAny($user);
    }

    public function connect(User $user, SocialChannel $channel): bool
    {
        return $user->can('gateways.connect');
    }

    public function update(User $user, SocialChannel $channel): bool
    {
        return $user->can('gateways.connect');
    }

    public function disconnect(User $user, SocialChannel $channel): bool
    {
        return $user->can('gateways.connect');
    }
}
