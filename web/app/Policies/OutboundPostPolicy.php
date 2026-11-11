<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OutboundPost;
use App\Models\User;

/**
 * File de publication vers les réseaux sociaux (D4-bis) : tout relève de `gateways.publish`.
 */
final class OutboundPostPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('gateways.publish');
    }

    public function view(User $user, OutboundPost $post): bool
    {
        return $user->can('gateways.publish');
    }

    public function create(User $user): bool
    {
        return $user->can('gateways.publish');
    }

    public function update(User $user, OutboundPost $post): bool
    {
        return $user->can('gateways.publish');
    }

    public function delete(User $user, OutboundPost $post): bool
    {
        return $user->can('gateways.publish');
    }

    public function publish(User $user, OutboundPost $post): bool
    {
        return $user->can('gateways.publish');
    }

    public function markPublished(User $user, OutboundPost $post): bool
    {
        return $user->can('gateways.publish');
    }
}
