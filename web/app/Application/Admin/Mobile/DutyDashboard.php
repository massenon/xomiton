<?php

declare(strict_types=1);

namespace App\Application\Admin\Mobile;

use App\Application\Admin\Alerts\AlertDesk;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Alerts\OnCallRoster;
use App\Application\Moderation\ReportQueue;
use App\Application\Platform\MaintenanceOverview;
use App\Models\SyncConflict;
use App\Models\User;
use App\Policies\Scopes\AlertScope;

/**
 * Tableau de bord d'astreinte de l'application mobile (`GET admin/dashboard`, P15-16) : compteurs d'alertes (mêmes
 * définitions que l'écran `/admin/alertes`, AlertDesk::kpis), SOS sans accusé de réception, comptes et demandes de pair
 * en attente, signalements de contenu à modérer, conflits de synchronisation ouverts, mode maintenance et garde. Chaque
 * bloc dépend de la permission de son écran d'origine (absent : `null`) ; aucune donnée personnelle, uniquement des nombres.
 */
final class DutyDashboard
{
    public function __construct(
        private readonly AlertDesk $desk,
        private readonly OnCallRoster $roster,
        private readonly ReportQueue $reports,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(User $user): array
    {
        $scope = AlertScope::routedTo($user);
        $kpis = $this->desk->kpis($scope);
        $overview = MaintenanceOverview::make(0);

        return [
            'alerts' => $user->can('alerts.view') ? $kpis + [
                'sos_unacknowledged' => (clone $scope)->sos()->open()->unacknowledged()->whereNull('cancelled_at')->count(),
                'open' => (clone $scope)->open()->count(),
            ] : null,
            'pending_accounts' => $user->can('users.validate_peers') ? User::query()->pending()->count() : null,
            'pending_peer_requests' => $user->can('users.validate_peers') ? $this->peerRequests() : null,
            'pending_moderation' => $user->can('moderation.manage') ? $this->reports->pendingGroupsForMobile()->toBase()->count() : null,
            'open_sync_conflicts' => $user->can('sync.manage') ? SyncConflict::query()->open()->count() : null,
            'maintenance' => [
                'state' => $overview->state->value,
                'active' => $overview->snapshot->active,
                'ends_at' => $overview->snapshot->endsAt?->toIso8601String(),
            ],
            'on_call' => [
                'me' => $this->roster->adminsOnCall()->contains(fn (User $onCall): bool => $onCall->is($user)),
                'admins' => $this->roster->adminsOnCall()->count(),
                'peers' => $this->roster->activePeers()->count(),
            ],
            'poll_seconds' => $this->desk->pollSeconds($user),
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function peerRequests(): int
    {
        return User::query()->pending()->with('profile')->get()
            ->filter(static fn (User $account): bool => ReviewRegistration::isPeerRequest($account))
            ->count();
    }
}
