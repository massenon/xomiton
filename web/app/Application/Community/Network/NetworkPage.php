<?php

declare(strict_types=1);

namespace App\Application\Community\Network;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Page par curseur (keyset) des suggestions et des invitations (P8-06, docs/API.md §1.1 et §17) : la suite reprend
 * **après le dernier élément affiché** (ses valeurs de tri, encodées dans `next_cursor`, opaque), jamais à un rang. Une
 * personne invitée ou suivie, une invitation acceptée, refusée ou annulée qui sort de la liste entre deux pages ne
 * décale donc pas la suivante (« Charger plus » sans trou). Curseur absent, illisible ou « vers l'arrière » → première
 * page.
 *
 * @template TItem of Model
 */
final class NetworkPage
{
    /**
     * @param  Collection<int, TItem>  $items
     */
    public function __construct(
        public readonly Collection $items,
        public readonly ?string $nextCursor,
        public readonly int $perPage,
    ) {}

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  requête ordonnée, déjà bornée par le curseur reçu
     * @param  Closure(TModel): array<string, int|string>  $key  valeurs de tri d'un élément
     * @return self<TModel>
     */
    public static function fetch(Builder $query, int $perPage, Closure $key): self
    {
        $perPage = max(1, $perPage);
        $rows = $query->limit($perPage + 1)->get();
        /** @var Collection<int, TModel> $items */
        $items = $rows->take($perPage)->values();
        $last = $items->last();

        return new self($items, $rows->count() > $perPage && $last !== null ? (new Cursor($key($last)))->encode() : null, $perPage);
    }

    /**
     * Valeurs de tri du curseur reçu ; null pour la première page (absent ou illisible).
     *
     * @param  list<string>  $keys
     * @return array<string, int|string>|null
     */
    public static function after(?string $encoded, array $keys): ?array
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }

        try {
            $cursor = Cursor::fromEncoded($encoded);

            if (! $cursor instanceof Cursor || ! $cursor->pointsToNextItems()) {
                return null;
            }

            $values = [];

            foreach ($keys as $key) {
                $value = $cursor->parameter($key);

                if (! is_int($value) && ! is_string($value)) {
                    return null;
                }

                $values[$key] = $value;
            }

            return $values;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array{next_cursor: string|null, per_page: int}
     */
    public function meta(): array
    {
        return ['next_cursor' => $this->nextCursor, 'per_page' => $this->perPage];
    }
}
