<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Alerts\Dispatch\NotifiedPeople;
use App\Application\Alerts\Queries\FindOwnSos;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Application\Shared\Referentials;
use App\Http\Controllers\Controller;
use App\Http\Resources\Alerts\SosStatusResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Page `/sos` (P5-05, gabarit sobre, tu) : grand bouton `x-sos-button` (maintien 2 s, décompte 5 s annulable), case
 * « Partager ma position » (dernier choix : `user_settings.sos_share_gps` pour un compte, `localStorage` pour un·e
 * visiteur·se), repli département / commune, envoi par `POST /api/v1/sos` (composant Alpine xmSosPage). Tout l'envoi
 * passe par l'API (Idempotency-Key, ADR-26 : jamais de formulaire web CSRF). `?alerte={ulid}` : SOS du compte connecté
 * (envoyé par le bouton flottant P5-08 ou avant un rechargement) affiché dans l'état « Alerte envoyée » ; ignoré pour
 * une alerte d'un autre compte ou sans session. Numéros d'urgence : KnowledgeBaseAnswerer::emergencyNumbers() (aucun en dur).
 */
final class SosPageController extends Controller
{
    public function __invoke(Request $request, FindOwnSos $findOwnSos, Referentials $referentials): Response
    {
        $user = $request->user();
        $member = $user instanceof User;
        $alert = $member ? $findOwnSos($user, $request->query('alerte')) : null;
        $initial = null;

        if ($alert !== null) {
            $resource = new SosStatusResource($alert, NotifiedPeople::for($alert));
            $initial = ['data' => $resource->resolve($request), 'meta' => $resource->with($request)['meta'] ?? []];
        }

        $response = response()->view('public.sos', [
            'member' => $member,
            'shareDefault' => $member ? (bool) ($user->settings?->sos_share_gps ?? true) : true,
            'profile' => [
                'zone_id' => $member && $user->zone_id !== null ? (int) $user->zone_id : null,
                'commune_id' => $member && $user->commune_id !== null ? (int) $user->commune_id : null,
            ],
            'zones' => self::zones($referentials),
            'numbers' => KnowledgeBaseAnswerer::emergencyNumbers(),
            'initial' => $initial,
            'seo' => [
                'title' => __('seo.pages.sos.title'),
                'description' => __('seo.pages.sos.description'),
                'index' => $alert === null ? null : false,
            ],
        ]);

        // Une alerte affichée est une donnée personnelle : jamais en cache (navigateur ou intermédiaire).
        return $alert === null ? $response : $response->header('Cache-Control', 'no-store, private');
    }

    /**
     * Départements et communes actifs, réduits à l'identifiant et au nom (repli manuel sans GPS).
     *
     * @return list<array{id: int, name: string, communes: list<array{id: int, name: string}>}>
     */
    private static function zones(Referentials $referentials): array
    {
        return array_map(static fn (array $zone): array => [
            'id' => $zone['id'],
            'name' => $zone['name'],
            'communes' => array_map(static fn (array $commune): array => ['id' => $commune['id'], 'name' => $commune['name']], $zone['communes']),
        ], $referentials->zones());
    }
}
