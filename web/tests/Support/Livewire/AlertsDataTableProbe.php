<?php

declare(strict_types=1);

namespace Tests\Support\Livewire;

use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Alert;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Écran témoin du trait WithDataTable sur une table à colonnes chiffrées (ADR-33) : `alerts.description` est
 * `encrypted`, la recherche ne porte que sur `category` et `pseudo`. `$unsafeColumn` simule une erreur de programmation
 * (colonne chiffrée déclarée cherchable ou triable, qualifiée ou issue d'une jointure) : le trait doit la refuser.
 * `export($full)` : export CSV des filtres actifs ; l'export complet ajoute le récit chiffré (`cases.export` exigé).
 */
final class AlertsDataTableProbe extends Component
{
    use WithDataTable;

    #[SafeUrl(as: 'st', except: '')]
    public string $status = '';

    public ?string $unsafeSearch = null;

    public ?string $unsafeSort = null;

    protected function sortableColumns(): array
    {
        return ['recue' => 'created_at', 'categorie' => 'category'] + ($this->unsafeSort !== null ? ['danger' => $this->unsafeSort] : []);
    }

    protected function searchableColumns(): array
    {
        return array_values(array_filter(['category', 'pseudo', $this->unsafeSearch]));
    }

    protected function filterProperties(): array
    {
        return ['status'];
    }

    /**
     * @return LengthAwarePaginator<int, Alert>
     */
    public function rows(): LengthAwarePaginator
    {
        return $this->paginateQuery($this->filtered());
    }

    public function export(bool $full = false): void
    {
        $this->requestCsvExport($full);
    }

    protected function csvScreen(): string
    {
        return 'sonde-alertes';
    }

    protected function authorizeCsvExport(bool $full): void
    {
        Gate::authorize('alerts.view');
    }

    protected function csvQuery(): Builder
    {
        return $this->filtered();
    }

    protected function csvColumns(bool $full): array
    {
        return ['id' => 'N°', 'category' => 'Catégorie'] + ($full ? ['description' => 'Récit'] : []);
    }

    protected function csvRow(Model $model, bool $full): array
    {
        /** @var Alert $model */
        return ['id' => $model->id, 'category' => $model->category, 'description' => $full ? $model->description : null];
    }

    /**
     * @return Builder<Alert>
     */
    private function filtered(): Builder
    {
        $query = Alert::query();

        if ($this->status !== '') {
            $query->where('status', $this->status);
        }

        return $query;
    }

    public function render(): string
    {
        return <<<'BLADE'
            <div>
                @php($rows = $this->rows())
                <span data-total>{{ $rows->total() }}</span>
                <ul>@foreach ($rows as $alert)<li data-alert="{{ $alert->id }}">{{ $alert->category }}</li>@endforeach</ul>
            </div>
            BLADE;
    }
}
