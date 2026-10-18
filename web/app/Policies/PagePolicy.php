<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Page;
use App\Models\User;

/**
 * Pages d'organisations partenaires (P8-03, FR-SOC-05) : `social.access` lit, crée et suit ; une page n'est visible
 * des autres membres qu'une fois **approuvée** par la modération et active (P12-05) — sa propriétaire la voit toujours
 * (« En attente de vérification ») ; on ne suit qu'une page visible et approuvée ; la propriétaire modifie ;
 * `moderation.manage` voit tout, approuve et met en avant (écran /admin/reseau, P12-05).
 */
final class PagePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('social.access');
    }

    public function view(User $user, Page $page): bool
    {
        if ($user->can('moderation.manage')) {
            return true;
        }

        if (! $user->can('social.access')) {
            return false;
        }

        return $page->isPubliclyVisible() || $page->isOwnedBy($user);
    }

    public function create(User $user): bool
    {
        return $user->can('social.access');
    }

    public function follow(User $user, Page $page): bool
    {
        return $user->can('social.access') && $page->isPubliclyVisible();
    }

    public function update(User $user, Page $page): bool
    {
        return $user->can('moderation.manage') || ($page->isOwnedBy($user) && $user->can('social.access'));
    }

    public function delete(User $user, Page $page): bool
    {
        return $this->update($user, $page);
    }

    /** Approuver ou retirer l'approbation (P12-05). */
    public function approve(User $user, Page $page): bool
    {
        return $user->can('moderation.manage');
    }

    /** Mettre en avant (P12-05). */
    public function feature(User $user, Page $page): bool
    {
        return $user->can('moderation.manage');
    }
}
