<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Thematique;
use App\Models\User;

/**
 * Fiches thématiques : lecture publique quand le contenu est publié (RG-01), édition réservée à `thematiques.manage`.
 */
final class ThematiquePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Thematique $thematique): bool
    {
        if ($user?->can('thematiques.manage')) {
            return true;
        }

        return $this->isPublished($thematique);
    }

    public function create(User $user): bool
    {
        return $user->can('thematiques.manage');
    }

    public function update(User $user, Thematique $thematique): bool
    {
        return $user->can('thematiques.manage');
    }

    public function delete(User $user, Thematique $thematique): bool
    {
        return $user->can('thematiques.manage');
    }

    public function publish(User $user, Thematique $thematique): bool
    {
        return $user->can('thematiques.manage');
    }

    private function isPublished(Thematique $thematique): bool
    {
        return Thematique::query()->published()->whereKey($thematique->getKey())->exists();
    }
}
