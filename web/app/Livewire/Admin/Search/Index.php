<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Search;

use App\Application\Admin\Search\GlobalSearch;
use App\Livewire\Attributes\SafeUrl;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

/**
 * Recherche globale `/admin/recherche` (P10-08, Ctrl K depuis la barre supérieure, prototype admin/recherche.php) :
 * résultats groupés (utilisateur·rices, dossiers, alertes, annuaire, thématiques, réseau, journal), chaque groupe limité
 * aux permissions et au périmètre de la personne, **jamais sur une colonne chiffrée** (GlobalSearch, ADR-33). Le terme
 * n'est ni journalisé (ni audit, ni journal applicatif), ni mémorisé en session (ForgetSearchTerms sur la route) ; il
 * reste dans l'adresse du navigateur de la personne (`?q=`). Au plus 60 recherches par minute et par compte (limiteur `admin-search`).
 */
final class Index extends Component
{
    #[SafeUrl(as: 'q', except: '')]
    public string $q = '';

    public function mount(): void
    {
        $this->authorize('admin.access');
    }

    public function render(): View
    {
        $user = $this->user();
        $term = GlobalSearch::normalize($this->q);
        $groups = [];
        $throttled = false;

        if ($term !== '') {
            // Limiteur nommé `admin-search` (AppServiceProvider, seuil `xomiton.rate_limits.admin_search_per_minute`).
            $limit = RateLimiter::limiter('admin-search')(request());
            $throttled = RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts);

            if (! $throttled) {
                RateLimiter::hit($limit->key, $limit->decaySeconds);
                $groups = app(GlobalSearch::class)->search($user, $term);
            }
        }

        return view('livewire.admin.search.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => array_sum(array_column($groups, 'total')),
            'throttled' => $throttled,
            'tooShort' => $this->q !== '' && trim($this->q) !== '' && $term === '',
            'perGroup' => GlobalSearch::PER_GROUP,
            'min' => GlobalSearch::MIN_LENGTH,
        ])->layout('components.layouts.admin', [
            'title' => __('admin_search.title'),
            'adminPage' => 'recherche',
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
