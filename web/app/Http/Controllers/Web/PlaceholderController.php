<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;

/**
 * Écran provisoire Blade pour les routes publiques et d'administration dont la page n'est pas encore transposée.
 * Chaque route disparaît de ce contrôleur quand sa phase du plan est livrée (docs/IMPLEMENTATION_PLAN.md).
 * Les routes de l'espace membre sont servies par Member\PlaceholderPageController (page Inertia dans le gabarit
 * membre Vue, P6-03) : le gabarit Blade provisoire components.layouts.member est supprimé.
 */
final class PlaceholderController extends Controller
{
    public function __invoke(string $title, string $prototype, string $layout = 'public'): View
    {
        return view('public.placeholder', [
            'title' => $title,
            'prototype' => $prototype,
            'layout' => $layout,
        ]);
    }
}
