<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Application\Alerts\SafePlaces;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Application\Shared\Referentials;
use App\Http\Controllers\Controller;
use App\Models\Zone;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Page `/urgences` (P4-06, prototype urgences.php) : gabarit sobre (Q18 a), tu (Q47), publique et indexable, servie
 * pendant la maintenance (RG-01). Numéros nationaux et fiches de l'annuaire depuis la même source que
 * `GET /api/v1/urgences` (ListEmergencyContacts, aucun numéro en dur) ; `?zone=` facultatif : un identifiant de
 * département inconnu, inactif ou mal formé est ignoré (page de crise : jamais de 404 ni de redirection), là où l'API
 * répond 422 (EmergencyContactsRequest). Lieux sûrs : SafePlaces. Tout est rendu côté serveur : la page reste lisible
 * depuis le cache hors connexion (P5-10) sans appel réseau ; seul le plan de sécurité utilise un script en ligne.
 */
final class UrgencesController extends Controller
{
    public function __invoke(Request $request, ListEmergencyContacts $list, Referentials $referentials): View
    {
        $zones = array_map(static fn (array $zone): array => ['id' => $zone['id'], 'name' => $zone['name']], $referentials->zones());
        $zoneId = filter_var($request->query('zone'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $zone = is_int($zoneId) && in_array($zoneId, array_column($zones, 'id'), true) ? Zone::query()->find($zoneId) : null;
        $result = $list($zone);
        // Titre et description SEO : premier numéro administré, jamais un numéro figé dans les textes (Q62).
        $firstNumber = (string) ($result->services[0]['number'] ?? '');
        $seoKey = $firstNumber === '' ? '_plain' : '';

        return view('public.urgences.index', [
            'services' => array_map(static fn (array $service): array => $service + [
                'tel' => KnowledgeBaseAnswerer::telHref($service['number']),
            ], $result->services),
            'contacts' => $result->contacts,
            'truncated' => $result->truncated,
            'zone' => $zone,
            'zones' => $zones,
            'places' => SafePlaces::all(),
            'planItems' => (array) trans('pages/urgences.plan.items'),
            'seo' => [
                'title' => __('seo.pages.urgences.title'.$seoKey, ['number' => $firstNumber]),
                'description' => __('seo.pages.urgences.description'.$seoKey, ['number' => $firstNumber]),
                'breadcrumb' => [[__('pages/urgences.title'), route('urgences')]],
            ],
        ]);
    }
}
