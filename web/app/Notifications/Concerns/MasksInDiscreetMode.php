<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Models\User;

/**
 * Le **mode discret** du profil vaut « aperçus masqués » pour les notifications de l'équipe (pair·es, admins : espaces
 * Pair et Admin de l'application mobile, mode discret par défaut dès le premier accès, D-MOB-8) : sur une notification
 * sensible, titre et corps neutres (« Xomiton », « Nouvelle notification »…), jamais « SOS », « VBG » ni « admin »
 * (NOTIFICATIONS §4.1). S'ajoute au réglage `mask_sensitive_notifications`. Une classe peut restreindre l'effet en
 * redéfinissant `discreetModeMasks()`.
 */
trait MasksInDiscreetMode
{
    protected function discreetModeMasks(): bool
    {
        return true;
    }

    public function shouldMask(object $notifiable): bool
    {
        if (! $this->isSensitive()) {
            return false;
        }

        if (parent::shouldMask($notifiable)) {
            return true;
        }

        return $this->discreetModeMasks() && $notifiable instanceof User && (bool) $notifiable->loadMissing('profile')->profile?->discreet_mode;
    }
}
