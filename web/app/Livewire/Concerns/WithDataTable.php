<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Application\Admin\CsvExportLink;
use App\Livewire\Attributes\SafeUrl;
use App\Models\User;
use App\Support\Audit;
use App\Support\CsvExport;
use App\Support\LikePattern;
use App\Support\ListingQuery;
use App\Support\LocaleFormatter;
use App\Support\TypedValue;
use Generator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\WithPagination;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tableau d'administration Livewire (P10-02, UI_UX_DESIGN_SYSTEM.md §4.2 et §4.4, Q23, ADR-33) : recherche `?q=`
 * limitée aux colonnes **en clair** (une colonne `encrypted` déclarée cherchable ou triable lève une LogicException :
 * un mot d'un récit chiffré ne filtre jamais la liste), tri par liste blanche `?tri=&sens=`, pagination serveur
 * 10 / 25 / 50 (25 par défaut, `?par=`, `?page=`), filtres propres à l'écran dans l'URL (`#[SafeUrl(as: 'st')]`…),
 * sélection pour les actions groupées et export CSV des filtres actifs.
 *
 * L'écran déclare `sortableColumns()`, `searchableColumns()`, au besoin `defaultSort()` et `filterProperties()`, puis
 * passe sa requête à `paginateQuery()` ; la vue utilise x-admin.filters, x-admin.table et x-admin.pagination. Les
 * propriétés d'URL portent `#[SafeUrl]` (jamais `#[Url]`) : une valeur mal formée est ignorée, sans erreur 500.
 *
 * Export CSV : l'écran déclare `csvScreen()`, `csvQuery()`, `csvColumns()`, `csvRow()` et `authorizeCsvExport()`, puis
 * son action appelle `requestCsvExport($full)` : contrôles (policy de l'écran, plus `cases.export` pour un export
 * complet, ADR-33), plafond `xomiton.exports.max_rows`, puis lien signé de courte durée vers CsvExportController, qui
 * restaure l'état, rejoue les contrôles, journalise (`export.csv`, ou `export.csv_full` pour un export complet) et
 * diffuse le fichier par paquets (Livewire mettrait toute la réponse en mémoire, puis en base64).
 */
trait WithDataTable
{
    use WithPagination;

    /** Q23 : pagination serveur 10 / 25 / 50, 25 par défaut. */
    public const PER_PAGE_OPTIONS = [10, 25, 50];

    public const PER_PAGE_DEFAULT = 25;

    #[SafeUrl(as: 'q', except: '')]
    public string $search = '';

    #[SafeUrl(as: 'tri', except: '')]
    public string $sort = '';

    #[SafeUrl(as: 'sens', except: '')]
    public string $direction = '';

    #[SafeUrl(as: 'par', except: 25)]
    public int $perPage = 25;

    /** @var list<string> identifiants sélectionnés (actions groupées) */
    public array $selected = [];

    public string $bulkAction = '';

    /**
     * Tri autorisé : clé d'URL => colonne SQL de la table de l'écran (jamais une saisie).
     *
     * @return array<string, string>
     */
    abstract protected function sortableColumns(): array;

    /**
     * Colonnes en clair où porte la recherche `q` (ADR-33 : code, n°, pseudo, commune, catégorie…).
     *
     * @return list<string>
     */
    abstract protected function searchableColumns(): array;

    /**
     * Tri par défaut : [clé de sortableColumns(), 'asc'|'desc'].
     *
     * @return array{0: string, 1: string}
     */
    protected function defaultSort(): array
    {
        return [(string) array_key_first($this->sortableColumns()), 'desc'];
    }

    /**
     * Propriétés de filtre de l'écran (remises à zéro par « Réinitialiser », comptées comme filtres actifs).
     *
     * @return list<string>
     */
    protected function filterProperties(): array
    {
        return [];
    }

    // --- Actions de la vue -------------------------------------------------------------------------------------------

    public function sortBy(string $key): void
    {
        if (! array_key_exists($key, $this->sortableColumns())) {
            return;
        }

        [$current, $direction] = $this->currentSort();
        [$defaultKey, $defaultDirection] = $this->defaultSort();
        $this->sort = $key;
        // Même colonne : l'ordre s'inverse ; autre colonne : croissant (la colonne par défaut garde son ordre propre).
        $this->direction = $current === $key ? ($direction === 'asc' ? 'desc' : 'asc') : ($key === $defaultKey ? $defaultDirection : 'asc');
        $this->resetPage();
    }

    public function applyFilters(): void
    {
        $this->search = ListingQuery::search($this->search);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->reset(['search', ...$this->filterProperties()]);
        $this->clearSelection();
        $this->resetPage();
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->bulkAction = '';
    }

    /**
     * « Tout sélectionner » sur la page : ajoute ou retire les identifiants affichés.
     *
     * @param  list<int|string>  $ids
     */
    public function togglePageSelection(array $ids): void
    {
        $ids = array_values(array_map('strval', array_filter($ids, static fn (mixed $id): bool => is_int($id) || is_string($id))));
        $missing = array_diff($ids, $this->selected);

        $this->selected = $missing === []
            ? array_values(array_diff($this->selected, $ids))
            : array_values(array_unique([...$this->selected, ...$ids]));
    }

    /** Toute modification d'une recherche, d'un filtre ou de la taille de page revient en page 1 (hook Livewire). */
    public function updatedWithDataTable(string $property): void
    {
        $root = explode('.', $property)[0];

        if ($root === 'search') {
            $this->search = ListingQuery::search($this->search);
        }

        if ($root === 'perPage' && ! in_array($this->perPage, self::PER_PAGE_OPTIONS, true)) {
            $this->perPage = self::PER_PAGE_DEFAULT;
        }

        if (in_array($root, ['search', 'perPage', ...$this->filterProperties()], true)) {
            $this->clearSelection();
            $this->resetPage();
        }
    }

    // --- Lecture de l'état (valeurs d'URL assainies) -----------------------------------------------------------------

    /**
     * Tri effectif : celui de l'URL s'il est autorisé, sinon le tri par défaut.
     *
     * @return array{0: string, 1: string}
     */
    public function currentSort(): array
    {
        [$defaultKey, $defaultDirection] = $this->defaultSort();
        $key = array_key_exists($this->sort, $this->sortableColumns()) ? $this->sort : $defaultKey;
        $direction = in_array($this->direction, ['asc', 'desc'], true) ? $this->direction : ($key === $defaultKey ? $defaultDirection : 'asc');

        return [$key, $direction];
    }

    public function perPageValue(): int
    {
        return in_array($this->perPage, self::PER_PAGE_OPTIONS, true) ? $this->perPage : self::PER_PAGE_DEFAULT;
    }

    public function searchTerm(): string
    {
        return ListingQuery::search($this->search);
    }

    public function activeFilterCount(): int
    {
        $count = $this->searchTerm() !== '' ? 1 : 0;

        foreach ($this->filterProperties() as $property) {
            $value = $this->{$property} ?? null;
            $count += $value !== null && $value !== '' && $value !== [] ? 1 : 0;
        }

        return $count;
    }

    /** Cibles `wire:target` des squelettes du tableau (recherche, tri, filtres, pagination). */
    public function dataTableTargets(): string
    {
        return implode(',', ['search', 'sortBy', 'perPage', 'gotoPage', 'previousPage', 'nextPage', 'setPage', 'resetFilters', 'applyFilters', ...$this->filterProperties()]);
    }

    // --- Requête -----------------------------------------------------------------------------------------------------

    /**
     * Applique recherche et tri, puis pagine (tri stable : clé primaire en second critère).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateQuery(Builder $query): LengthAwarePaginator
    {
        return $this->applySort($this->applySearch($query))->paginate($this->perPageValue());
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySearch(Builder $query): Builder
    {
        $columns = $this->searchableColumns();
        self::assertClearColumns($query->getModel(), $columns, 'recherche');
        $term = $this->searchTerm();

        if ($term === '' || $columns === []) {
            return $query;
        }

        return $query->where(static function (Builder $where) use ($columns, $term): void {
            foreach ($columns as $column) {
                $where->orWhereRaw(LikePattern::clause($where->qualifyColumn($column)), [LikePattern::contains($term)]);
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    protected function applySort(Builder $query): Builder
    {
        $columns = $this->sortableColumns();
        self::assertClearColumns($query->getModel(), array_values($columns), 'tri');
        [$key, $direction] = $this->currentSort();

        if (isset($columns[$key])) {
            $query->orderBy($query->qualifyColumn($columns[$key]), $direction);
        }

        return $query->orderBy($query->qualifyColumn($query->getModel()->getKeyName()), $direction);
    }

    // --- Export CSV (ADR-33) ----------------------------------------------------------------------------------------

    /** Clé de l'écran : nom du fichier (`xomiton-<clé>-AAAAMMJJ.csv`) et ligne d'audit. */
    protected function csvScreen(): string
    {
        throw new LogicException(sprintf('%s : csvScreen() non déclarée (export CSV).', static::class));
    }

    /**
     * Requête filtrée de l'écran, sans recherche ni tri (ajoutés par le trait).
     *
     * @return Builder<Model>
     */
    protected function csvQuery(): Builder
    {
        throw new LogicException(sprintf('%s : csvQuery() non déclarée (export CSV).', static::class));
    }

    /**
     * Colonnes exportées : clé => en-tête. Les colonnes sensibles (CsvExport) ne sont admises qu'avec `$full`.
     *
     * @return array<string, string>
     */
    protected function csvColumns(bool $full): array
    {
        throw new LogicException(sprintf('%s : csvColumns() non déclarée (export CSV).', static::class));
    }

    /**
     * Ligne CSV d'un modèle, indexée par clé de colonne.
     *
     * @return array<string, scalar|\Stringable|null>
     */
    protected function csvRow(Model $model, bool $full): array
    {
        throw new LogicException(sprintf('%s : csvRow() non déclarée (export CSV).', static::class));
    }

    /** Contrôle propre à l'écran (policy) ; le trait y ajoute `cases.export` pour un export complet. */
    protected function authorizeCsvExport(bool $full): void {}

    /**
     * Action Livewire de l'écran : contrôles, plafond, puis redirection vers le lien signé (le navigateur télécharge
     * le fichier sans quitter la page). Au-delà du plafond, toast d'erreur et aucun lien.
     */
    protected function requestCsvExport(bool $full = false): void
    {
        $this->authorizeCsvExportWithDataTable($full);
        CsvExport::guard(array_keys($this->csvColumns($full)), $full);
        $count = $this->csvCount();

        if ($count > self::csvMaxRows()) {
            $this->dispatch('toast', type: 'error', message: self::tooManyRowsMessage($count));

            return;
        }

        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $this->redirect(CsvExportLink::make(static::class, $user, $this->csvState(), $full));
    }

    /**
     * Diffusion du fichier, appelée par CsvExportController sur une instance neuve (hors cycle Livewire) : état restauré
     * depuis le lien, mêmes contrôles et plafond, ligne d'audit, lecture par paquets.
     *
     * @param  array<string, mixed>  $state
     */
    protected function respondCsvExport(array $state, bool $full): Response
    {
        $this->restoreCsvState($state);
        $this->authorizeCsvExportWithDataTable($full);
        $columns = $this->csvColumns($full);
        CsvExport::guard(array_keys($columns), $full);
        $count = $this->csvCount();

        if ($count > self::csvMaxRows()) {
            return redirect()->back()->with(['toast' => self::tooManyRowsMessage($count), 'toast_type' => 'error']);
        }

        Audit::log($full ? 'export.csv_full' : 'export.csv', null, trans_choice($full ? 'admin.export.audit_full' : 'admin.export.audit', $count, [
            'screen' => $this->csvScreen(),
            'count' => $count,
        ]));

        $query = $this->applySort($this->applySearch($this->csvQuery()));
        $chunk = max(1, (int) config('xomiton.exports.chunk_rows', 500));
        $rows = (function () use ($query, $full, $chunk): Generator {
            foreach ($query->lazy($chunk) as $model) {
                yield $this->csvRow($model, $full);
            }
        })();

        return CsvExport::download(CsvExport::filename($this->csvScreen()), $columns, $rows, $full);
    }

    /**
     * État transmis au lien d'export : recherche, tri effectif et filtres de l'écran.
     *
     * @return array<string, mixed>
     */
    protected function csvState(): array
    {
        [$sort, $direction] = $this->currentSort();
        $state = ['search' => $this->searchTerm(), 'sort' => $sort, 'direction' => $direction];

        foreach ($this->filterProperties() as $property) {
            $state[$property] = $this->{$property} ?? null;
        }

        return $state;
    }

    /**
     * Restaure l'état d'un lien d'export (propriétés connues seulement, converties au type déclaré).
     *
     * @param  array<string, mixed>  $state
     */
    protected function restoreCsvState(array $state): void
    {
        foreach (['search', 'sort', 'direction', ...$this->filterProperties()] as $property) {
            if (! array_key_exists($property, $state)) {
                continue;
            }

            $result = TypedValue::coerce($this, $property, $state[$property]);

            if ($result['ok']) {
                $this->{$property} = $result['value'];
            }
        }
    }

    private function authorizeCsvExportWithDataTable(bool $full): void
    {
        if ($full) {
            // ADR-33 : un export complet (récits, GPS, contacts, notes) exige `cases.export`, quel que soit l'écran.
            Gate::authorize('cases.export');
        }

        $this->authorizeCsvExport($full);
    }

    private function csvCount(): int
    {
        return $this->applySearch($this->csvQuery())->toBase()->getCountForPagination();
    }

    private static function csvMaxRows(): int
    {
        return max(1, (int) config('xomiton.exports.max_rows', 20000));
    }

    private static function tooManyRowsMessage(int $count): string
    {
        $format = LocaleFormatter::make();

        return (string) __('admin.export.too_many_rows', ['count' => $format->number($count), 'max' => $format->number(self::csvMaxRows())]);
    }

    /**
     * ADR-33 : aucune recherche ni aucun tri sur une colonne chiffrée — cast `encrypted*` du modèle, ou colonne de
     * `config('xomiton.encrypted_columns')` pour la table nommée (colonne qualifiée `profiles.orientation`, jointure).
     *
     * @param  list<string>  $columns
     */
    protected static function assertClearColumns(Model $model, array $columns, string $usage): void
    {
        $casts = $model->getCasts();
        $encrypted = (array) config('xomiton.encrypted_columns', []);

        foreach ($columns as $column) {
            $qualified = str_contains($column, '.');
            $table = $qualified ? substr($column, 0, (int) strrpos($column, '.')) : $model->getTable();
            $name = $qualified ? substr($column, (int) strrpos($column, '.') + 1) : $column;
            $cast = $table === $model->getTable() ? ($casts[$name] ?? null) : null;

            if ((is_string($cast) && str_starts_with($cast, 'encrypted')) || in_array($name, (array) ($encrypted[$table] ?? []), true)) {
                throw new LogicException(sprintf('Colonne chiffrée « %s.%s » interdite en %s (ADR-33).', $table, $name, $usage));
            }
        }
    }
}
