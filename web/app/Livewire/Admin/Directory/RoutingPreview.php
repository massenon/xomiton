<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Directory;

use App\Application\Alerts\RouteAlert;
use App\Application\Shared\Referentials;
use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Aperçu du routage des alertes `/admin/annuaire` (P11-04, prototype admin/annuaire.php « Aperçu du routage des
 * alertes ») : le simulateur « besoin × département » rend **la décision même de RouteAlert** (RG-04, quatre niveaux de
 * repli, avec le détail de chaque niveau), et le tableau « département × besoin » les décisions de tous les départements
 * (RouteAlert::decisions, un badge « repli » quand le contact retenu n'est pas celui du rôle demandé). Composant enfant
 * de l'annuaire : il ne se recalcule pas à chaque frappe de la recherche, seulement quand une fiche change
 * (`directory-changed`) ou qu'on modifie le simulateur. Lecture seule, réservée à `directory.manage`.
 */
final class RoutingPreview extends Component
{
    /** Besoin simulé : `sos` (aucun besoin exprimé) ou le slug d'un besoin du référentiel. */
    public string $need = 'sos';

    /** Département simulé (identifiant) ; vide : le premier département. */
    public string $zone = '';

    public function boot(): void
    {
        abort_unless(auth()->user() instanceof User && auth()->user()->can('directory.manage'), 403);
    }

    #[On('directory-changed')]
    public function refreshPreview(): void
    {
        // Le rendu relit l'annuaire : rien d'autre à faire.
    }

    public function render(RouteAlert $route, Referentials $referentials): View
    {
        $zones = $referentials->zones();
        $needs = $referentials->needs();
        $slugs = array_column($needs, 'slug');
        $need = $this->need === 'sos' || in_array($this->need, $slugs, true) ? $this->need : 'sos';
        $zoneId = ctype_digit($this->zone) && $this->zone !== '' && in_array((int) $this->zone, array_column($zones, 'id'), true)
            ? (int) $this->zone
            : ($zones[0]['id'] ?? null);

        $explanation = $route->explain($zoneId, $need === 'sos' ? [] : [$need]);

        // Matrice : un scénario par département et par besoin (+ SOS), lus en une passe.
        $columns = [...array_map(static fn (array $n): string => $n['slug'], $needs), 'sos'];
        $scenarios = [];

        foreach ($zones as $zone) {
            foreach ($columns as $column) {
                $scenarios[] = [$zone['id'], $column === 'sos' ? [] : [$column]];
            }
        }

        $decisions = $route->decisions($scenarios);
        $ids = collect($decisions)->map(fn (RoutingDecision $d): ?int => $d->contactId)->filter()->merge(
            collect($explanation->levels)->flatMap(fn (array $level): array => array_filter([$level['matched'], ...$level['examined']])),
        )->unique()->values()->all();
        $contacts = DirectoryContact::query()->with('zone:id,name')->whereIn('id', $ids)->get()->keyBy('id');

        $matrix = [];
        $index = 0;

        foreach ($zones as $zone) {
            $cells = [];

            foreach ($columns as $column) {
                $cells[$column] = $decisions[$index++];
            }

            $matrix[] = ['zone' => $zone, 'cells' => $cells];
        }

        return view('livewire.admin.directory.routing-preview', [
            'needOptions' => ['sos' => (string) __('admin_data.directory.routing.sos'), ...array_column($needs, 'label', 'slug')],
            'zones' => $zones,
            'selectedNeed' => $need,
            'selectedZone' => $zoneId,
            'explanation' => $explanation,
            'contacts' => $contacts,
            'matrix' => $matrix,
            'columns' => $columns,
            'columnLabels' => ['sos' => (string) __('admin_data.directory.routing.sos'), ...array_column($needs, 'label', 'slug')],
        ]);
    }
}
