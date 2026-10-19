<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Collection tirée par `GET sync/pull` (P9-06) : lignes modifiées depuis une position (jeu de clés horodatage +
 * identifiant, suppressions logiques comprises), portée du compte, clé rendue à l'appareil et contenu servi.
 */
interface SyncCollection
{
    public function name(): string;

    public function availableTo(User $user): bool;

    /** Lignes par page (une de plus est lue pour savoir s'il en reste). */
    public function pageSize(): int;

    /**
     * Lignes **susceptibles** d'avoir été vues par ce compte, modifiées après `$since`, jusqu'à `$until` inclus,
     * dans l'ordre (horodatage, identifiant) ; au plus `$limit` lignes. Chaque ligne porte `sync_changed_at`.
     *
     * @return Collection<int, Model>
     */
    public function changedSince(User $user, PullPosition $since, CarbonImmutable $until, int $limit): Collection;

    /**
     * Clés (au sens de key()) visibles maintenant par ce compte parmi celles données.
     *
     * @param  list<string|int>  $keys
     * @return list<string|int>
     */
    public function visibleKeys(User $user, array $keys): array;

    public function key(Model $row): string|int;

    /**
     * @return array<string, mixed>
     */
    public function payload(Model $row, Request $request): array;
}
