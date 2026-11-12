<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Application\Member\MemberShell;
use App\Models\Profile;
use App\Models\User;

/**
 * Mode discret sur les pages publiques des violences numériques (P19-04). Un·e visiteur·se n'a pas de réglage : page
 * normale, indexable. Une personne connectée garde ses réglages de l'espace membre :
 *   - titre d'onglet neutre (`user_settings.neutral_tab_title`, MemberShell::neutralTitle) → « Notes », comme `app.blade` ;
 *   - mode discret (`profiles.discreet_mode`) → planches de BD repliées tant qu'elle ne choisit pas de les afficher.
 */
final class Discretion
{
    public function __construct(private readonly MemberShell $shell) {}

    public function neutralTitle(?User $user): bool
    {
        return $user !== null && $this->shell->neutralTitle($user);
    }

    public function hidesImages(?User $user): bool
    {
        $profile = $user?->getRelationValue('profile');

        return $profile instanceof Profile && (bool) $profile->discreet_mode;
    }

    /**
     * Props `<x-seo>` avec le titre neutre si la personne l'a choisi (jamais indexé : page vue connecté·e).
     *
     * @param  array<string, mixed>  $seo
     * @return array<string, mixed>
     */
    public function seo(array $seo, ?User $user): array
    {
        return $this->neutralTitle($user) ? ['title' => (string) __('seo.noindex.neutral'), 'index' => false] + $seo : $seo;
    }
}
