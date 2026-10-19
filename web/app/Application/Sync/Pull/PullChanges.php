<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use App\Application\Sync\Pull\Collections\CaseFollowupsCollection;
use App\Application\Sync\Pull\Collections\CasesCollection;
use App\Application\Sync\Pull\Collections\DirectoryContactsCollection;
use App\Application\Sync\Pull\Collections\EmergencyContactsCollection;
use App\Application\Sync\Pull\Collections\RoutedAlertsCollection;
use App\Application\Sync\Pull\Collections\ThematiquesCollection;
use App\Application\Sync\Pull\Collections\ValidationRulesCollection;
use App\Models\SyncCursor;
use App\Models\SyncDevice;
use App\Models\SyncTombstone;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `GET sync/pull?since=` (P9-06, SOFTWARE_ARCHITECTURE.md §5) : par collection, créations et modifications (`upserts`)
 * et suppressions (`deletes` : ligne supprimée, dépubliée, sortie de la portée du compte, pierre tombale
 * `sync_tombstones`) depuis la position du jeton `since` ; nouveau jeton dans `meta.cursor`, `has_more` tant qu'une
 * page reste. Collections de tous les comptes : contacts d'urgence, annuaire public, thématiques ; du pair
 * (`cases.create`, `alerts.view_routed`) : règles de validation, ses dossiers et leurs suivis, alertes routées vers lui.
 *
 *  - lignes modifiées depuis moins de `xomiton.sync.pull_lag_seconds` gardées pour le tirage suivant (une transaction
 *    validée en retard ne glisse jamais derrière le curseur) ;
 *  - `reset` (tirage complet, l'appareil remplace la collection, première page seulement) : premier tirage, ou
 *    dernier tirage plus vieux que la rétention des pierres tombales (`xomiton.sync.tombstone_days`, `sync:prune`) ;
 *  - une suppression n'est jamais servie pour une ligne visible maintenant (réattribuée puis rendue) ;
 *  - `sync_cursors` garde la position servie et l'heure du tirage par appareil et collection (écran d'administration).
 */
final class PullChanges
{
    /** @var list<class-string<SyncCollection>> */
    public const COLLECTIONS = [
        EmergencyContactsCollection::class,
        DirectoryContactsCollection::class,
        ThematiquesCollection::class,
        ValidationRulesCollection::class,
        CasesCollection::class,
        CaseFollowupsCollection::class,
        RoutedAlertsCollection::class,
    ];

    /**
     * Noms de toutes les collections (validation de `collections[]`).
     *
     * @return list<string>
     */
    public static function names(): array
    {
        return array_map(static fn (string $class): string => app($class)->name(), self::COLLECTIONS);
    }

    /**
     * @param  list<string>|null  $only  collections demandées (null : toutes celles du compte)
     */
    public function __invoke(User $user, SyncDevice $device, PullCursor $cursor, ?array $only, Request $request): PullResult
    {
        $until = CarbonImmutable::now()->subSeconds(max(0, (int) config('xomiton.sync.pull_lag_seconds', 2)));
        $horizon = CarbonImmutable::now()->subDays(max(1, (int) config('xomiton.sync.tombstone_days', 90)))->getTimestamp();
        $collections = [];
        $more = false;

        foreach (self::COLLECTIONS as $class) {
            /** @var SyncCollection $collection */
            $collection = app($class);

            if (! $collection->availableTo($user) || ($only !== null && ! in_array($collection->name(), $only, true))) {
                continue;
            }

            $since = $cursor->position($collection->name());
            // Seule la position des pierres tombales vieillit : une ligne ancienne (fiche inchangée depuis deux ans) ne force
            // jamais un tirage complet, une pierre tombale purgée oui.
            $reset = $since->isStart() || $since->tombAt < $horizon;
            [$page, $position] = $this->page($collection, $user, $reset ? new PullPosition : $since, $reset, $until, $request);

            $collections[$collection->name()] = $page;
            $cursor = $cursor->with($collection->name(), $position);
            $more = $more || $page['has_more'];

            SyncCursor::obtain($device, $collection->name())->advanceTo($position->at);
        }

        return new PullResult($collections, $cursor, $more, $until);
    }

    /**
     * @return array{0: array{reset: bool, upserts: list<array<string, mixed>>, deletes: list<string|int>, has_more: bool}, 1: PullPosition}
     */
    private function page(SyncCollection $collection, User $user, PullPosition $since, bool $reset, CarbonImmutable $until, Request $request): array
    {
        $limit = $collection->pageSize();
        $untilAt = $until->getTimestamp();

        $rows = $collection->changedSince($user, $since, $until, $limit + 1);
        $rowsMore = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        $visible = array_flip($collection->visibleKeys($user, $rows->map(fn (Model $row): string|int => $collection->key($row))->all()));
        $upserts = [];
        $deletes = [];

        foreach ($rows as $row) {
            $key = $collection->key($row);

            if (isset($visible[$key])) {
                $upserts[$key] = $collection->payload($row, $request);
            } elseif (! $reset) {
                $deletes[] = $key;
            }
        }

        $last = $rows->last();
        [$at, $id] = $last === null ? [$since->at, $since->id] : [EloquentSyncCollection::changedAt($last), (int) $last->getKey()];

        if (! $rowsMore && $at < $untilAt) {
            [$at, $id] = [$untilAt, 0];
        }

        [$tombDeletes, $tombAt, $tombId, $tombMore] = $reset
            ? [[], $untilAt, 0, false]
            : $this->tombstones($collection, $user, $since, $until, $limit);

        $deletes = array_values(array_unique(array_filter(
            [...$deletes, ...$tombDeletes],
            static fn (string|int $key): bool => ! array_key_exists($key, $upserts),
        )));

        return [[
            'reset' => $reset,
            'upserts' => array_values($upserts),
            'deletes' => $deletes,
            'has_more' => $rowsMore || $tombMore,
        ], new PullPosition($at, $id, $tombAt, $tombId)];
    }

    /**
     * Pierres tombales servies après la position, sauf pour une ligne visible maintenant.
     *
     * @return array{0: list<string|int>, 1: int, 2: int, 3: bool}
     */
    private function tombstones(SyncCollection $collection, User $user, PullPosition $since, CarbonImmutable $until, int $limit): array
    {
        $at = EloquentSyncCollection::sqlTime($since->tombAt);

        $rows = SyncTombstone::query()->for($collection->name(), (int) $user->getKey())
            ->where('created_at', '<=', EloquentSyncCollection::sqlTime($until->getTimestamp()))
            ->where(fn (Builder $after) => $after->where('created_at', '>', $at)
                ->orWhere(fn (Builder $same) => $same->where('created_at', $at)->where('id', '>', $since->tombId)))
            ->orderBy('created_at')->orderBy('id')
            ->limit($limit + 1)
            ->get();

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();
        $numeric = $rows->every(static fn (SyncTombstone $row): bool => ctype_digit((string) $row->subject_key));
        $keys = $rows->map(static fn (SyncTombstone $row): string|int => $numeric ? (int) $row->subject_key : (string) $row->subject_key)->unique()->values()->all();
        $visible = array_flip($collection->visibleKeys($user, $keys));
        $deletes = array_values(array_filter($keys, static fn (string|int $key): bool => ! isset($visible[$key])));

        /** @var SyncTombstone|null $last */
        $last = $rows->last();
        [$tombAt, $tombId] = $last === null
            ? [$since->tombAt, $since->tombId]
            : [(int) $last->created_at?->getTimestamp(), (int) $last->getKey()];

        if (! $more && $tombAt < $until->getTimestamp()) {
            [$tombAt, $tombId] = [$until->getTimestamp(), 0];
        }

        return [$deletes, $tombAt, $tombId, $more];
    }
}
