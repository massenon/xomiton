<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Collection adossée à une table Eloquent : position = `COALESCE(updated_at, created_at)` (une suppression logique
 * met aussi `updated_at` à jour), départage par `id`. `candidates()` = lignes que l'appareil a pu recevoir (suppressions
 * logiques comprises, portée large) ; `visible()` = ce que le compte peut garder maintenant : une ligne candidate
 * modifiée mais plus visible devient une suppression pour l'appareil.
 */
abstract class EloquentSyncCollection implements SyncCollection
{
    /**
     * @return class-string<Model>
     */
    abstract protected function model(): string;

    /**
     * @return Builder<Model>
     */
    abstract protected function visible(User $user): Builder;

    /**
     * @return Builder<Model>
     */
    protected function candidates(User $user): Builder
    {
        $class = $this->model();
        $query = $class::query();

        // @phpstan-ignore method.notFound (macro du SoftDeletingScope)
        return in_array(SoftDeletes::class, class_uses_recursive($class), true) ? $query->withTrashed() : $query;
    }

    protected function keyColumn(): string
    {
        return 'id';
    }

    /** Horodatage de modification servant de position (SQL, colonnes qualifiées). */
    protected function changeExpression(string $table): string
    {
        return sprintf('COALESCE(%1$s.updated_at, %1$s.created_at)', $table);
    }

    /**
     * @return list<string>
     */
    protected function with(): array
    {
        return [];
    }

    public function pageSize(): int
    {
        return max(1, (int) config('xomiton.sync.pull_page_size', 200));
    }

    public function changedSince(User $user, PullPosition $since, CarbonImmutable $until, int $limit): Collection
    {
        $query = $this->candidates($user);
        $table = $query->getModel()->getTable();
        $expression = $this->changeExpression($table);
        $at = self::sqlTime($since->at);

        return $query->select($table.'.*')
            ->selectRaw($expression.' as sync_changed_at')
            ->whereRaw($expression.' <= ?', [$until->setTimezone(self::timezone())->format('Y-m-d H:i:s')])
            ->where(fn (Builder $after) => $after->whereRaw($expression.' > ?', [$at])
                ->orWhere(fn (Builder $same) => $same->whereRaw($expression.' = ?', [$at])->where($table.'.id', '>', $since->id)))
            ->orderByRaw($expression)
            ->orderBy($table.'.id')
            ->with($this->with())
            ->limit($limit)
            ->get();
    }

    public function visibleKeys(User $user, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $query = $this->visible($user);
        $column = $query->getModel()->getTable().'.'.$this->keyColumn();

        return array_values($query->whereIn($column, $keys)->pluck($this->keyColumn())
            ->map(fn (mixed $key): string|int => $this->keyColumn() === 'id' ? (int) $key : (string) $key)
            ->all());
    }

    public function key(Model $row): string|int
    {
        $key = $row->getAttribute($this->keyColumn());

        return $this->keyColumn() === 'id' ? (int) $key : (string) $key;
    }

    /** Position (secondes Unix) d'une ligne lue par changedSince(). */
    public static function changedAt(Model $row): int
    {
        $value = $row->getAttribute('sync_changed_at');

        return $value === null ? 0 : CarbonImmutable::parse((string) $value, self::timezone())->getTimestamp();
    }

    public static function sqlTime(int $timestamp): string
    {
        return CarbonImmutable::createFromTimestamp($timestamp, self::timezone())->format('Y-m-d H:i:s');
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }
}
