<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Inactivité de session (Q30, P13-04 `session_minutes` ; revue admin-socle) : le polling Livewire nu (`wire:poll` sans
 * méthode, cloche de l'administration 30 s, alertes 10 s) prolongeait la session à chaque requête, si bien qu'un onglet
 * d'administration laissé ouvert sur un poste partagé ne se déconnectait jamais. L'horodatage de la dernière activité
 * **réelle** est gardé en session ; une requête Livewire sans appel de méthode ni modification de propriété ne le met
 * pas à jour. Au-delà de la durée d'inactivité (`session.lifetime`, que P13-04 alimentera depuis `session_minutes`),
 * la session est fermée : 419 `session_idle` pour Livewire (la page se recharge sur la connexion, admin/shell.js),
 * 401 en JSON, sinon redirection vers la connexion. « Rester connecté·e » (Q6) reste distinct : un cookie de rappel
 * suspend ce contrôle, comme il prolonge déjà la session native.
 */
final class EnforceIdleTimeout
{
    public const SESSION_KEY = 'xm_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! Auth::guard('web')->check()) {
            return $next($request);
        }

        $session = $request->session();
        $last = $session->get(self::SESSION_KEY);
        $now = now()->getTimestamp();
        $remembered = $request->cookies->has(Auth::guard('web')->getRecallerName());

        if (! $remembered && is_int($last) && $now - $last > self::idleSeconds()) {
            return $this->expire($request);
        }

        if (! is_int($last) || ! self::isPassive($request)) {
            $session->put(self::SESSION_KEY, $now);
        }

        return $next($request);
    }

    public static function idleSeconds(): int
    {
        return max(1, (int) config('session.lifetime', 120)) * 60;
    }

    /** Requête Livewire de simple rafraîchissement : aucun appel de méthode, aucune propriété modifiée. */
    public static function isPassive(Request $request): bool
    {
        if (! $request->hasHeader('X-Livewire') || ! $request->isMethod('POST')) {
            return false;
        }

        $components = $request->input('components');

        if (! is_array($components) || $components === []) {
            return false;
        }

        foreach ($components as $component) {
            if (! is_array($component) || ($component['calls'] ?? []) !== [] || ($component['updates'] ?? []) !== []) {
                return false;
            }
        }

        return true;
    }

    private function expire(Request $request): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->hasHeader('X-Livewire')) {
            return response()->json(['message' => (string) __('auth.session_idle'), 'reason' => 'session_idle'], 419);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => (string) __('auth.session_idle'), 'reason' => 'session_idle'], 401);
        }

        return redirect()->guest(route('login'))->with(['toast' => (string) __('auth.session_idle'), 'toast_type' => 'info']);
    }
}
