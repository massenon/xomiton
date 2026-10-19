<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Http\Resources\Directory\DirectoryContactResource;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `directory_contacts` : annuaire public (fiches actives `is_public`) pour tous les comptes, hors connexion ; les fiches
 * réservées au réseau restent en ligne (`GET directory`) et ne sont jamais candidates (leur identifiant n'arrive pas
 * dans `deletes`) : une fiche qui quitte l'annuaire public passe par une pierre tombale (TombstoneRecorder). Contenu
 * Directory\DirectoryContactResource (jamais les notes ni le compte lié).
 */
final class DirectoryContactsCollection extends EloquentSyncCollection
{
    public const NAME = 'directory_contacts';

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
        return DirectoryContact::query()->active()->public();
    }

    protected function with(): array
    {
        return ['zone', 'commune'];
    }

    public function payload(Model $row, Request $request): array
    {
        return DirectoryContactResource::make($row)->resolve($request);
    }
}
