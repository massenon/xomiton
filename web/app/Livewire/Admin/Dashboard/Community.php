<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Dashboard;

use App\Application\Admin\Dashboard\DashboardStats;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/**
 * Indicateurs communautaires du tableau de bord `/admin` (P18-01, P18-02, P18-03) : membres (total, nouveaux du jour,
 * rétention à 30 jours), échanges du jour et sur 7 jours (sujets, réponses de forum, messages privés : **comptages
 * seulement**), signalements en attente dont priorité haute, taux de résolution des sujets (réponse validée).
 *
 * Composant enfant du tableau de bord, actualisé par son propre sondage toutes les POLL_SECONDS secondes sans recharger
 * la page ; chaque passage lit les agrégats en cache 60 s (DashboardStats::community()) : aucun comptage lourd à chaque
 * passage. Livewire ralentit de lui-même le sondage quand l'onglet est en arrière-plan (pas de `keep-alive`). Blocs selon
 * la permission de leur écran d'origine : membres `users.view`, échanges, signalements et résolution `moderation.manage` ;
 * rien n'est rendu sans l'une des deux. Les graphiques de dossiers du tableau de bord (Chart.js, cache 60 s) restent
 * dans le composant parent.
 */
final class Community extends Component
{
    public const POLL_SECONDS = 60;

    public function render(DashboardStats $stats): View
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        $members = $user->can('users.view');
        $moderation = $user->can('moderation.manage');

        return view('livewire.admin.dashboard.community', [
            'stats' => $members || $moderation ? $stats->community() : null,
            'canMembers' => $members,
            'canModeration' => $moderation,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'pollSeconds' => self::POLL_SECONDS,
        ]);
    }
}
