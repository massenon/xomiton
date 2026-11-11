<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Models\DirectoryContact;

/**
 * Invalidation du cache des contacts d'urgence (P5-06 `GET urgences`, P4-06 `/urgences`) : toute écriture sur une fiche
 * de l'annuaire (création, modification, suppression, restauration) rend obsolètes les entrées de ListEmergencyContacts.
 * Enregistré par AppServiceProvider::registerReferentialObservers.
 */
final class DirectoryContactObserver
{
    public function saved(DirectoryContact $contact): void
    {
        ListEmergencyContacts::invalidate();
    }

    public function deleted(DirectoryContact $contact): void
    {
        ListEmergencyContacts::invalidate();
    }

    public function restored(DirectoryContact $contact): void
    {
        ListEmergencyContacts::invalidate();
    }

    public function forceDeleted(DirectoryContact $contact): void
    {
        ListEmergencyContacts::invalidate();
    }
}
