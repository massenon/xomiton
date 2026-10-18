<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Thematique;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ancienne adresse `thematique.php?id=<n>` du prototype (P0-09, P4-02) : 301 vers la fiche publiée dont la couverture
 * semée porte cet id (`covers/th-<n>.jpg`, ContentSeeder), sinon vers la liste. La chaîne `?cat=` de `thematiques.php`
 * n'est pas reprise (adresses internes au prototype, jamais indexées).
 */
final class LegacyThematiqueRedirectController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $id = filter_var($request->query('id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $thematique = $id === false ? null : Thematique::query()
            ->published()
            ->where('cover_path', 'like', '%/covers/th-'.$id.'.jpg')
            ->first(['id', 'slug']);

        return redirect()->to($thematique ? route('thematiques.show', $thematique) : route('thematiques.index'), 301);
    }
}
