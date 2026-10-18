<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Alerts\ValueObjects\NeedRoleMap;
use App\Domain\Alerts\ValueObjects\RoutingCandidate;
use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Domain\Alerts\ValueObjects\RoutingExplanation;
use App\Domain\Alerts\ValueObjects\RoutingRequest;
use App\Enums\RoutingFallback;

/**
 * Routage automatique RG-04 (FR-ALT-04 : « identique à `route_alert()` du prototype », prototype_social/api.php
 * l.12-25), quatre niveaux de repli :
 *  1. `role_zone` — pour chaque besoin, dans l'ordre fourni, le rôle ciblé (`needs.routed_role`) : premier contact de
 *     ce rôle dans le département de l'alerte ;
 *  2. `focal_zone` — point focal du département (remplaçant·e de garde fourni·e par l'appelant, sinon premier point
 *     focal du département) ;
 *  3. `focal_national` — point focal national (garde, sinon fiche `is_national_focal`), sinon premier point focal de
 *     toute zone (parité prototype, api.php l.22) ;
 *  4. `first_active` — premier contact actif.
 * Sans département (SOS localisé par GPS seul), les niveaux 1 et 2 sont sautés. Pur : ni base, ni horloge ; les
 * candidats sont les contacts actifs dans l'ordre d'identifiant croissant.
 */
final class AlertRouter
{
    /**
     * @param  iterable<RoutingCandidate>  $candidates
     */
    public function route(
        RoutingRequest $request,
        NeedRoleMap $map,
        iterable $candidates,
        ?RoutingCandidate $zoneFocal = null,
        ?RoutingCandidate $nationalFocal = null,
    ): RoutingDecision {
        return $this->explain($request, $map, $candidates, $zoneFocal, $nationalFocal)->decision;
    }

    /**
     * Même calcul que route(), avec le détail de chaque niveau (aperçu du routage de l'administration).
     *
     * @param  iterable<RoutingCandidate>  $candidates
     */
    public function explain(
        RoutingRequest $request,
        NeedRoleMap $map,
        iterable $candidates,
        ?RoutingCandidate $zoneFocal = null,
        ?RoutingCandidate $nationalFocal = null,
    ): RoutingExplanation {
        $pool = $this->sorted($candidates);
        $levels = [];
        $decision = null;

        foreach ($this->levels() as $fallback) {
            $skipped = $request->zoneId === null && in_array($fallback, [RoutingFallback::RoleZone, RoutingFallback::FocalZone], true);
            $examined = [];
            $matched = null;

            if (! $skipped && $decision === null) {
                [$examined, $matched] = match ($fallback) {
                    RoutingFallback::RoleZone => $this->byRoleInZone($request, $map, $pool),
                    RoutingFallback::FocalZone => $this->zoneFocal($request, $pool, $zoneFocal),
                    RoutingFallback::FocalNational => $this->nationalFocal($pool, $nationalFocal),
                    default => $this->firstActive($pool),
                };
            }

            $retained = $decision === null && $matched !== null;

            if ($retained) {
                $decision = RoutingDecision::at($fallback, $matched);
            }

            $levels[] = [
                'fallback' => $fallback,
                'level' => $fallback->level(),
                'examined' => $examined,
                'matched' => $matched,
                'skipped' => $skipped,
                'retained' => $retained,
            ];
        }

        return new RoutingExplanation($levels, $decision ?? RoutingDecision::none());
    }

    /**
     * @return list<RoutingFallback>
     */
    private function levels(): array
    {
        return [RoutingFallback::RoleZone, RoutingFallback::FocalZone, RoutingFallback::FocalNational, RoutingFallback::FirstActive];
    }

    /**
     * @param  iterable<RoutingCandidate>  $candidates
     * @return list<RoutingCandidate>
     */
    private function sorted(iterable $candidates): array
    {
        $pool = [];

        foreach ($candidates as $candidate) {
            $pool[$candidate->id] = $candidate;
        }

        ksort($pool);

        return array_values($pool);
    }

    /**
     * Niveau 1 : besoins dans l'ordre de l'alerte ; un besoin sans rôle (ou inconnu) est sauté ; le premier contact du
     * premier besoin pourvu l'emporte (« break 2 » du prototype). `examined` : tous les contacts éligibles, dans cet ordre.
     *
     * @param  list<RoutingCandidate>  $pool
     * @return array{0: list<int>, 1: int|null}
     */
    private function byRoleInZone(RoutingRequest $request, NeedRoleMap $map, array $pool): array
    {
        $eligible = [];

        foreach ($request->needs as $need) {
            $role = $map->roleFor($need);

            if ($role === null) {
                continue;
            }

            foreach ($pool as $candidate) {
                if ($candidate->role === $role && $candidate->isInZone($request->zoneId)) {
                    $eligible[] = $candidate->id;
                }
            }
        }

        $eligible = array_values(array_unique($eligible));

        return [$eligible, $eligible[0] ?? null];
    }

    /**
     * Niveau 2 : remplaçant·e de garde du département s'il ou elle est fourni·e, sinon premier point focal du département.
     *
     * @param  list<RoutingCandidate>  $pool
     * @return array{0: list<int>, 1: int|null}
     */
    private function zoneFocal(RoutingRequest $request, array $pool, ?RoutingCandidate $onDuty): array
    {
        $eligible = $this->ids(array_filter($pool, static fn (RoutingCandidate $c): bool => $c->isFocal() && $c->isInZone($request->zoneId)));

        if ($onDuty !== null) {
            return [array_values(array_unique([$onDuty->id, ...$eligible])), $onDuty->id];
        }

        return [$eligible, $eligible[0] ?? null];
    }

    /**
     * Niveau 3 : point focal national (garde, puis fiche nationale), sinon premier point focal toute zone.
     *
     * @param  list<RoutingCandidate>  $pool
     * @return array{0: list<int>, 1: int|null}
     */
    private function nationalFocal(array $pool, ?RoutingCandidate $onDuty): array
    {
        $national = $this->ids(array_filter($pool, static fn (RoutingCandidate $c): bool => $c->isNationalFocal));
        $focal = $this->ids(array_filter($pool, static fn (RoutingCandidate $c): bool => $c->isFocal()));
        $eligible = array_values(array_unique([...($onDuty !== null ? [$onDuty->id] : []), ...$national, ...$focal]));

        return [$eligible, $eligible[0] ?? null];
    }

    /**
     * Niveau 4 : premier contact actif (annuaire vide : aucun).
     *
     * @param  list<RoutingCandidate>  $pool
     * @return array{0: list<int>, 1: int|null}
     */
    private function firstActive(array $pool): array
    {
        $eligible = $this->ids($pool);

        return [$eligible, $eligible[0] ?? null];
    }

    /**
     * @param  array<int, RoutingCandidate>  $candidates
     * @return list<int>
     */
    private function ids(array $candidates): array
    {
        return array_values(array_map(static fn (RoutingCandidate $c): int => $c->id, $candidates));
    }
}
