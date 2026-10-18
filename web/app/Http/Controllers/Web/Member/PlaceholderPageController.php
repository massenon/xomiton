<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Écran provisoire de l'espace membre (P6-03) : page Inertia `Member/Placeholder` dans le gabarit membre Vue
 * (MemberLayout : en-tête, sidenav, SOS flottant), en attendant l'écran réel de sa phase (P7 à P9). Remplace le gabarit
 * Blade provisoire de P3 (components.layouts.member, supprimé). Paramètres lus dans les `defaults` de la route
 * (routes/web.php `$memberPlaceholder`) : title (clé de lang/fr/menus.php `member_pages.*`, traduite ici pour suivre la
 * locale de la requête), prototype (page du prototype reprise à l'identique), aside (colonne droite du prototype : /fil).
 * Les paramètres d'adresse ({post}, {group}…) sont ignorés : rien n'est encore résolu.
 */
final class PlaceholderPageController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $defaults = $request->route()?->defaults ?? [];
        $title = __((string) ($defaults['title'] ?? ''));
        $title = is_string($title) ? $title : '';

        return Inertia::render('Member/Placeholder', [
            'title' => $title,
            'prototype' => (string) ($defaults['prototype'] ?? ''),
            'aside' => (bool) ($defaults['aside'] ?? false),
        ])->withViewData(['title' => $title]);
    }
}
