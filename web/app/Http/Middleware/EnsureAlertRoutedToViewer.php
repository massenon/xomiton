<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Alert;
use App\Models\User;
use App\Policies\Scopes\AlertScope;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garde des routes `pair/alerts/{alert}` (P9-02, docs/API.md §22) : l'espace pair ne sert que les alertes routées vers
 * une fiche d'annuaire du compte (AlertScope::isRoutedTo), **même pour un compte `alerts.view`** — l'administration
 * traite les autres depuis `/admin/alertes`. Non routée → 403 `forbidden` neutre, avant la validation du corps.
 * AlertPolicy reste appliquée ensuite (`alerts.take`, `alerts.close`). À poser après la liaison du paramètre `alert`.
 */
final class EnsureAlertRoutedToViewer
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $alert = $request->route('alert');

        if (! $user instanceof User || ! $alert instanceof Alert || ! AlertScope::isRoutedTo($alert, $user)) {
            throw new AuthorizationException;
        }

        return $next($request);
    }
}
