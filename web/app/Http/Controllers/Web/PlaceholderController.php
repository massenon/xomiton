<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Écran provisoire pour les routes dont la page n'est pas encore transposée.
 * Chaque route disparaît de ce contrôleur quand sa phase du plan est livrée (docs/IMPLEMENTATION_PLAN.md).
 * Les routes de l'espace membre (middleware `auth`, gabarit non précisé) sont servies par la coquille membre
 * (components.layouts.member : en-tête connecté, sidenav, déconnexion) en attendant Vue + Inertia (P6).
 */
final class PlaceholderController extends Controller
{
    public function __invoke(Request $request, string $title, string $prototype, string $layout = 'public'): View
    {
        if ($layout === 'public' && in_array('auth', $request->route()?->gatherMiddleware() ?? [], true)) {
            $layout = 'member';
        }

        return view('public.placeholder', [
            'title' => $title,
            'prototype' => $prototype,
            'layout' => $layout,
        ]);
    }
}
