<?php

declare(strict_types=1);

namespace App\Application\Admin\Alerts;

use App\Application\Alerts\OnCallRoster;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DirectoryRole;
use App\Models\Alert;
use App\Models\DirectoryContact;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lectures du bureau des alertes `/admin/alertes` (P10-05, prototype admin/alertes.php) : onglets par type (clés
 * d'URL stables, libellés Q29), compteurs par onglet, KPI, cadence d'actualisation et contacts proposés à la
 * réattribution. Lecture seule : les écritures passent par les cas d'usage d'App\Application\Alerts (P5-02).
 * Aucune colonne chiffrée n'est lue ici.
 */
final class AlertDesk
{
    /** Actualisation de la liste : 10 s pour les agent·es de garde (seul écran à 10 s, D7), 30 s pour les autres. */
    public const POLL_ON_CALL = 10;

    public const POLL_DEFAULT = 30;

    /** Contacts proposés au plus dans la liste de réattribution. */
    public const CONTACT_LIMIT = 100;

    /** Onglet => type d'alerte (null : toutes). */
    public const TABS = [
        'all' => null,
        'sos' => AlertType::Sos,
        'reports' => AlertType::Report,
        'needs' => AlertType::Need,
    ];

    public function __construct(private readonly OnCallRoster $roster) {}

    public static function tabType(string $tab): ?AlertType
    {
        return self::TABS[$tab] ?? null;
    }

    public static function isTab(string $tab): bool
    {
        return array_key_exists($tab, self::TABS);
    }

    /** Cadence d'actualisation : de garde (plage en cours, ou repli des admins habilités sans planning) → 10 s. */
    public function pollSeconds(User $user): int
    {
        return $this->roster->adminsOnCall()->contains(fn (User $onCall): bool => $onCall->is($user))
            ? self::POLL_ON_CALL
            : self::POLL_DEFAULT;
    }

    /**
     * Compteurs par onglet, sous les filtres hors type (requête groupée unique).
     *
     * @param  Builder<Alert>  $filtered  alertes visibles et filtrées, sans condition de type
     * @return array<string, int>
     */
    public function tabCounts(Builder $filtered): array
    {
        /** @var array<string, int> $byType */
        $byType = $filtered->reorder()->toBase()->selectRaw('type, COUNT(*) as n')->groupBy('type')->pluck('n', 'type')->all();
        $counts = [];

        foreach (self::TABS as $tab => $type) {
            $counts[$tab] = $type === null ? (int) array_sum($byType) : (int) ($byType[$type->value] ?? 0);
        }

        return $counts;
    }

    /**
     * KPI de l'en-tête : nouvelles, SOS actifs, priorité critique ouverte, traitées.
     *
     * @param  Builder<Alert>  $scope  AlertScope::routedTo()
     * @return array{new: int, sos_active: int, critical_open: int, handled: int}
     */
    public function kpis(Builder $scope): array
    {
        return [
            'new' => (clone $scope)->where('status', AlertStatus::New)->count(),
            'sos_active' => (clone $scope)->sos()->open()->whereNull('cancelled_at')->count(),
            'critical_open' => (clone $scope)->open()->where('priority', AlertPriority::Critical)->whereNull('cancelled_at')->count(),
            'handled' => (clone $scope)->where('status', AlertStatus::Handled)->count(),
        ];
    }

    /**
     * Contacts actifs proposés à la réattribution : même département d'abord quand il est demandé, rôle facultatif.
     *
     * @return Collection<int, DirectoryContact>
     */
    public function contactsFor(Alert $alert, string $role = '', bool $sameZone = true): Collection
    {
        $query = DirectoryContact::query()->active()->with('zone:id,name');
        $directoryRole = $role === '' ? null : DirectoryRole::tryFrom($role);

        if ($directoryRole !== null) {
            $query->withRole($directoryRole);
        }

        if ($sameZone && $alert->zone_id !== null) {
            $query->inZone((int) $alert->zone_id);
        }

        return $query->ordered()->limit(self::CONTACT_LIMIT)->get();
    }
}
