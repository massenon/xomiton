<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catalogue des composants Vue `Xm*` (P6-04) rendu par Inertia (P6-02) : page de démonstration de la coquille Vue,
 * à comparer au catalogue Blade `/design-system`. Comme lui : local et recette seulement, 404 ailleurs ; connexion exigée
 * (props partagées réelles). `/fil` reste en Blade jusqu'au gabarit membre Vue (P6-03), qui porte le SOS flottant.
 */
final class DesignSystemVueController extends Controller
{
    public function __invoke(): Response
    {
        abort_unless(app()->environment('local', 'staging'), 404);

        return Inertia::render('DesignSystem/Index')->withViewData(['title' => __('design_system.vue.title')]);
    }
}
