<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Cases\PeerSpace;
use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Enums\AlertStatus;
use App\Http\Resources\Alerts\RoutedAlertResource;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `alerts` : alertes routées vers une fiche d'annuaire liée au compte (`alerts.view_routed`, portée « routées vers
 * moi » même pour l'administration), non closes ; clé = ULID (ADR-27). Résumé RoutedAlertResource seulement : jamais le
 * récit, le contact, la position ni l'autrice ou l'auteur (le détail reste en ligne, AlertPolicy). Close ou routée
 * ailleurs (pierre tombale) : retirée de l'appareil.
 */
final class RoutedAlertsCollection extends EloquentSyncCollection
{
    public const NAME = 'alerts';

    public function name(): string
    {
        return self::NAME;
    }

    public function availableTo(User $user): bool
    {
        return PeerSpace::allows($user) && $user->can('alerts.view_routed');
    }

    protected function model(): string
    {
        return Alert::class;
    }

    protected function keyColumn(): string
    {
        return 'ulid';
    }

    protected function candidates(User $user): Builder
    {
        return Alert::query()->whereIn('routed_to', DirectoryContact::query()->select('id')->linkedTo($user));
    }

    protected function visible(User $user): Builder
    {
        return $this->candidates($user)->where('status', '!=', AlertStatus::Closed->value);
    }

    protected function with(): array
    {
        return ['zone', 'commune'];
    }

    public function payload(Model $row, Request $request): array
    {
        return RoutedAlertResource::make($row)->resolve($request);
    }
}
