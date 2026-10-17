<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Resource;
use App\Models\User;

/**
 * Ressources et actualités : lecture publique quand le contenu est publié (RG-01), édition réservée à `resources.manage`.
 */
final class ResourcePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Resource $resource): bool
    {
        if ($user?->can('resources.manage')) {
            return true;
        }

        return $this->isPublished($resource);
    }

    public function create(User $user): bool
    {
        return $user->can('resources.manage');
    }

    public function update(User $user, Resource $resource): bool
    {
        return $user->can('resources.manage');
    }

    public function delete(User $user, Resource $resource): bool
    {
        return $user->can('resources.manage');
    }

    public function publish(User $user, Resource $resource): bool
    {
        return $user->can('resources.manage');
    }

    private function isPublished(Resource $resource): bool
    {
        return Resource::query()->published()->whereKey($resource->getKey())->exists();
    }
}
