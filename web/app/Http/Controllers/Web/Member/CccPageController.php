<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Cases\PeerSpace;
use App\Application\Ccc\BrowseCcc;
use App\Http\Controllers\Controller;
use App\Http\Resources\Ccc\CccToolResource;
use App\Models\CccTool;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran Vue « Outils CCC » `/ccc` (P8-12, prototype ccc.php, manuel-pair-animateur.php, boites-a-images.php,
 * bd-ssr.php) : page Inertia `Member/Ccc` dans le gabarit membre, **une seule implémentation pour le membre et le pair**
 * (P9-05). Onglets Manuel · Boîtes à images · BD SSR · Lois ; les outils sont ceux de `GET /api/v1/ccc` (publiés
 * seulement, ordre stocké, images avec image de remplacement), la progression du manuel reste sur l'appareil.
 * `presentation` : drapeau « mode présentation » de P9-05, vrai pour un compte pair·e actif (même règle que l'espace pair,
 * RG-03) — le guide de l'animateur·rice accompagne alors chaque planche du mode animation. Page d'une session : jamais
 * gardée hors ligne par le service worker (P5-10, pas de `X-Xm-Audience: guest`), noindex ; sans `ccc_tools.view`, 403
 * comme l'API (`can:ccc_tools.view` sur la route).
 */
final class CccPageController extends Controller
{
    public function __invoke(Request $request, BrowseCcc $ccc): Response
    {
        $viewer = $request->user();
        $title = (string) __('ccc.title');

        return Inertia::render('Member/Ccc', [
            'title' => $title,
            'presentation' => $viewer instanceof User && PeerSpace::allows($viewer),
            'tools' => array_map(
                /** @param Collection<int, CccTool> $tools */
                static fn (Collection $tools): array => CccToolResource::collection($tools)->resolve($request),
                $ccc->families(),
            ),
            'laws' => ['count' => $ccc->publishedLaws(), 'url' => route('lois')],
        ])->withViewData(['title' => $title]);
    }
}
