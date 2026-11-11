<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Catalogue vivant du design system (COMPOSANTS.md §Catalogue, IMPLEMENTATION_PLAN P0-10) :
 * chaque composant x-* dans chacun de ses états. Disponible uniquement en local et en recette (404 ailleurs).
 */
final class DesignSystemController extends Controller
{
    public function __invoke(): View
    {
        abort_unless(app()->environment('local', 'staging'), 404);

        return view('design-system');
    }
}
