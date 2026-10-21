<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Activation, désactivation et suppression d'une fiche de l'annuaire (P11-04, `directory.manage`). Seules les fiches
 * **actives** sont ciblées par le routage des alertes (RG-04) ; une fiche supprimée l'est logiquement (SoftDeletes) : les
 * alertes déjà routées vers elle gardent leur historique. Journal d'audit « directory.activated », « directory.deactivated »,
 * « directory.deleted » (nom et rôle, jamais les coordonnées).
 */
final class ChangeDirectoryContact
{
    public function toggle(User $by, DirectoryContact $contact): DirectoryContact
    {
        Gate::forUser($by)->authorize('update', $contact);

        $contact->is_active = ! $contact->is_active;
        $contact->save();

        Audit::log($contact->is_active ? 'directory.activated' : 'directory.deactivated', $contact, sprintf('nom=%s ; rôle=%s', $contact->name, $contact->role->value), $by);

        return $contact;
    }

    public function delete(User $by, DirectoryContact $contact): void
    {
        Gate::forUser($by)->authorize('delete', $contact);

        $detail = sprintf('nom=%s ; rôle=%s', $contact->name, $contact->role->value);
        $contact->delete();

        Audit::log('directory.deleted', null, $detail, $by);
    }

    /** Vrai quand aucune fiche active ne porte le drapeau « point focal national » (l'escalade RG-05 passe par ses replis). */
    public static function nationalFocalMissing(): bool
    {
        return DirectoryContact::query()->active()->nationalFocal()->doesntExist();
    }
}
