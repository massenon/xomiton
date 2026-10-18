<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Application\Content\HelpCenter;
use App\Http\Controllers\Controller;
use App\Support\ListingQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Anciennes adresses du centre d'aide (P0-09, P4-05), en 301 :
 *   - `help.php?cat=&q=` → `/aide` en gardant la catégorie et la recherche (mêmes slugs que x_help_categories() ;
 *     permanentRedirect les perdrait) ;
 *   - `help-details.php?id=<n>` adressait la n-ième question de x_faq() (rang 1…) : même ordre que le seed
 *     (`slug_by_index` de database/seeders/data/content/faqs.php), donc l'article de ce slug s'il est encore au centre
 *     d'aide, sinon `/aide`.
 */
final class LegacyAideRedirectController extends Controller
{
    public const SEED_FILE = 'seeders/data/content/faqs.php';

    public function index(Request $request): RedirectResponse
    {
        $cat = ListingQuery::slug($request->query('cat'));

        return redirect()->to(route('aide.index', array_filter([
            'cat' => is_string($cat) ? $cat : null,
            'q' => ListingQuery::search($request->query('q')),
        ])), 301);
    }

    public function show(Request $request, HelpCenter $helpCenter): RedirectResponse
    {
        $slug = self::seededSlug($request->query('id'));
        $found = $slug !== null && $helpCenter->faqs()->where('slug', $slug)->exists();

        return redirect()->to($found ? route('aide.show', $slug) : route('aide.index'), 301);
    }

    /** Slug semé de la question de rang `id` (entier ≥ 1) du prototype, sinon null. */
    public static function seededSlug(mixed $id): ?string
    {
        $id = is_string($id) || is_int($id) ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $file = database_path(self::SEED_FILE);

        if ($id === false || ! is_file($file)) {
            return null;
        }

        $slug = (require $file)['slug_by_index'][$id - 1] ?? null;

        return is_string($slug) ? $slug : null;
    }
}
