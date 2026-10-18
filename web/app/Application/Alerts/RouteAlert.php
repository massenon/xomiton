<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Shared\Referentials;
use App\Domain\Alerts\AlertRouter;
use App\Domain\Alerts\ValueObjects\NeedRoleMap;
use App\Domain\Alerts\ValueObjects\RoutingCandidate;
use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Domain\Alerts\ValueObjects\RoutingExplanation;
use App\Domain\Alerts\ValueObjects\RoutingRequest;
use App\Models\DirectoryContact;
use App\Models\Zone;
use Carbon\CarbonInterface;

/**
 * Service de routage RG-04 : lit l'annuaire actif (ordre d'identifiant), la carte besoin → rôle (référentiel `needs`
 * en cache, P3-06) et la garde en cours (OnCallRoster::focalOnDuty, P1-17), puis délègue la décision au domaine (AlertRouter, pur).
 * Appelé par CreateReport et TriggerSos ; jamais par RequestCallback (contact choisi, `manual`).
 */
final class RouteAlert
{
    public function __construct(
        private readonly AlertRouter $router,
        private readonly OnCallRoster $roster,
        private readonly Referentials $referentials,
    ) {}

    /**
     * @param  list<string>  $needs
     */
    public function __invoke(?int $zoneId, array $needs, ?CarbonInterface $at = null): RoutingDecision
    {
        return $this->explain($zoneId, $needs, $at)->decision;
    }

    /**
     * Détail des 4 niveaux (aperçu du routage de l'administration, P11).
     *
     * @param  list<string>  $needs
     */
    public function explain(?int $zoneId, array $needs, ?CarbonInterface $at = null): RoutingExplanation
    {
        $at ??= now();
        $zone = $zoneId === null ? null : Zone::query()->find($zoneId);

        return $this->router->explain(
            new RoutingRequest($zone?->getKey() === null ? null : (int) $zone->getKey(), $needs),
            NeedRoleMap::fromNeeds($this->referentials->needs()),
            $this->candidates(),
            // Garde seulement : hors garde, le routeur retient le premier point focal par identifiant (pas ordered()).
            $zone === null ? null : self::candidate($this->roster->focalOnDuty($zone, $at)),
            self::candidate($this->roster->focalOnDuty(null, $at)),
        );
    }

    /**
     * Contacts éligibles : actifs, non supprimés, `is_public` et joignabilité sans effet (parité api.php l.13).
     *
     * @return list<RoutingCandidate>
     */
    private function candidates(): array
    {
        return DirectoryContact::query()
            ->active()
            ->orderBy('id')
            ->get(['id', 'role', 'zone_id', 'is_national_focal'])
            ->map(static fn (DirectoryContact $contact): RoutingCandidate => self::toCandidate($contact))
            ->values()
            ->all();
    }

    private static function candidate(?DirectoryContact $contact): ?RoutingCandidate
    {
        return $contact === null ? null : self::toCandidate($contact);
    }

    private static function toCandidate(DirectoryContact $contact): RoutingCandidate
    {
        return new RoutingCandidate(
            id: (int) $contact->getKey(),
            role: $contact->role,
            zoneId: $contact->zone_id === null ? null : (int) $contact->zone_id,
            isNationalFocal: (bool) $contact->is_national_focal,
        );
    }
}
