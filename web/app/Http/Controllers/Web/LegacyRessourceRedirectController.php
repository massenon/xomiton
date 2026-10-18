<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Support\LegacyRedirects;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anciennes adresses du prototype (P0-09, P4-03), en 301 :
 *   - `ressources.php?q=&cat=` → `/ressources` en gardant la recherche et la catégorie (permanentRedirect les perdrait) ;
 *   - `ressource.php?id=<n>` redirigeait vers `thematique.php?id=<n>` : même résolution (LegacyRedirects::thematique),
 *     sinon la liste des ressources.
 */
final class LegacyRessourceRedirectController extends Controller
{
    public function index(Request $request): RedirectResponse
    {
        $cat = ListingQuery::slug($request->query('cat'));

        return redirect()->to(route('ressources.index', array_filter([
            'q' => ListingQuery::search($request->query('q')),
            'cat' => is_string($cat) ? $cat : null,
        ])), 301);
    }

    public function show(Request $request): RedirectResponse
    {
        return redirect()->to(LegacyRedirects::thematique($request->query('id')) ?? route('ressources.index'), 301);
    }
}
