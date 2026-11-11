<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Enums\DirectoryRole;
use App\Http\Resources\Alerts\EmergencyContactResource;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `emergency_contacts` : contacts d'urgence servis hors connexion (même portée que `GET urgences`,
 * ListEmergencyContacts : fiches actives et publiques d'un rôle d'urgence ou point focal national), pour tous les
 * comptes. Contenu EmergencyContactResource.
 */
final class EmergencyContactsCollection extends EloquentSyncCollection
{
    public const NAME = 'emergency_contacts';

    public function name(): string
    {
        return self::NAME;
    }

    public function availableTo(User $user): bool
    {
        return true;
    }

    protected function model(): string
    {
        return DirectoryContact::class;
    }

    /** Fiches publiques seulement (suppressions logiques comprises) : une fiche réservée au réseau n'est jamais servie. */
    protected function candidates(User $user): Builder
    {
        return DirectoryContact::query()->withTrashed()->public();
    }

    protected function visible(User $user): Builder
    {
        return DirectoryContact::query()->active()->public()->where(fn (Builder $query) => $query
            ->whereIn('role', array_map(static fn (DirectoryRole $role): string => $role->value, ListEmergencyContacts::EMERGENCY_ROLES))
            ->orWhere('is_national_focal', true));
    }

    protected function with(): array
    {
        return ['zone', 'commune'];
    }

    public function payload(Model $row, Request $request): array
    {
        return EmergencyContactResource::make($row)->resolve($request);
    }
}
