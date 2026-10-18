<?php

declare(strict_types=1);

use App\Domain\Alerts\AlertRouter;
use App\Domain\Alerts\ValueObjects\NeedRoleMap;
use App\Domain\Alerts\ValueObjects\RoutingCandidate;
use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Domain\Alerts\ValueObjects\RoutingRequest;
use App\Enums\DirectoryRole;
use App\Enums\RoutingFallback;

/*
 * P5-01 : routage RG-04, sans base de données — table de vérité de `route_alert()` (prototype_social/api.php l.12-25)
 * et règles de la cible (point focal national, département absent, garde).
 */

const ROUTER_ZONES = ['Littoral' => 1, 'Ouémé' => 2, 'Borgou' => 3, 'Zou' => 4, 'Atlantique' => 5];

function routerMap(): NeedRoleMap
{
    // Carte seedée par ReferenceDataSeeder (needs.routed_role), identique à $wantRole du prototype (api.php l.14).
    return NeedRoleMap::fromNeeds([
        ['slug' => 'soutien_psychologique', 'routed_role' => 'psychologue'],
        ['slug' => 'soutien_juridique', 'routed_role' => 'juriste'],
        ['slug' => 'soutien_medical', 'routed_role' => 'gynecologue'],
        ['slug' => 'hebergement', 'routed_role' => null],
        ['slug' => 'accompagnement', 'routed_role' => null],
        ['slug' => 'securite', 'routed_role' => null],
        ['slug' => 'autre', 'routed_role' => null],
    ]);
}

/**
 * Candidats d'un cas du dataset : contacts actifs et non supprimés seulement (RouteAlert les filtre en base).
 *
 * @param  list<array{0: int, 1: string, 2: string|null, 3?: list<string>}>  $directory
 * @return array{0: list<RoutingCandidate>, 1: RoutingCandidate|null}
 */
function routerCandidates(array $directory): array
{
    $candidates = [];
    $onDuty = null;

    foreach ($directory as $row) {
        $options = $row[3] ?? [];

        if (in_array('inactive', $options, true) || in_array('deleted', $options, true)) {
            continue;
        }

        $candidate = new RoutingCandidate($row[0], DirectoryRole::from($row[1]), $row[2] === null ? null : ROUTER_ZONES[$row[2]], in_array('national', $options, true));
        $candidates[] = $candidate;

        if (in_array('on_duty', $options, true)) {
            $onDuty = $candidate;
        }
    }

    // L'ordre de passage ne compte pas : le routeur trie par identifiant (ordre du fichier du prototype).
    return [array_reverse($candidates), $onDuty];
}

it('reproduit la table de vérité de route_alert()', function (array $needs, ?string $zone, array $directory, ?int $expected, ?string $fallback): void {
    [$candidates, $onDuty] = routerCandidates($directory);

    $decision = (new AlertRouter)->route(new RoutingRequest($zone === null ? null : ROUTER_ZONES[$zone], $needs), routerMap(), $candidates, $onDuty);

    expect($decision->contactId)->toBe($expected)
        ->and($decision->fallback?->value)->toBe($fallback);
})->with('route_alert_cases');

it('respecte l’ordre des besoins, saute les besoins sans rôle et les slugs inconnus sans erreur', function (): void {
    $router = new AlertRouter;
    $candidates = [new RoutingCandidate(1, DirectoryRole::Psychologue, 1), new RoutingCandidate(2, DirectoryRole::Juriste, 1)];

    expect($router->route(new RoutingRequest(1, ['soutien_psychologique', 'soutien_juridique']), routerMap(), $candidates)->contactId)->toBe(1)
        ->and($router->route(new RoutingRequest(1, ['soutien_juridique', 'soutien_psychologique']), routerMap(), $candidates)->contactId)->toBe(2)
        ->and($router->route(new RoutingRequest(1, ['hebergement', 'inconnu', '', 'soutien_juridique']), routerMap(), $candidates)->contactId)->toBe(2);
});

it('saute les niveaux 1 et 2 quand l’alerte n’a pas de département', function (): void {
    $explanation = (new AlertRouter)->explain(new RoutingRequest(null, ['soutien_juridique']), routerMap(), [
        new RoutingCandidate(2, DirectoryRole::Juriste, 1),
        new RoutingCandidate(7, DirectoryRole::PointFocal, 1),
    ]);

    expect($explanation->levels[0]['skipped'])->toBeTrue()
        ->and($explanation->levels[1]['skipped'])->toBeTrue()
        ->and($explanation->levels[2]['skipped'])->toBeFalse()
        ->and($explanation->decision->contactId)->toBe(7)
        ->and($explanation->decision->fallback)->toBe(RoutingFallback::FocalNational);
});

it('donne la priorité au point focal national fourni (garde), puis à la fiche nationale, puis au premier point focal', function (): void {
    $router = new AlertRouter;
    $request = new RoutingRequest(4, ['soutien_medical']);
    $pool = [new RoutingCandidate(4, DirectoryRole::PointFocal, 2), new RoutingCandidate(10, DirectoryRole::PointFocal, null, true)];

    expect($router->route($request, routerMap(), $pool, null, new RoutingCandidate(12, DirectoryRole::PointFocal, 3))->contactId)->toBe(12)
        ->and($router->route($request, routerMap(), $pool)->contactId)->toBe(10)
        ->and($router->route($request, routerMap(), [$pool[0]])->contactId)->toBe(4);
});

it('fait primer la garde au niveau 2 mais jamais sur le rôle demandé', function (): void {
    $router = new AlertRouter;
    $onDuty = new RoutingCandidate(11, DirectoryRole::PointFocal, 1);
    $pool = [new RoutingCandidate(2, DirectoryRole::Juriste, 1), new RoutingCandidate(7, DirectoryRole::PointFocal, 1), $onDuty];

    expect($router->route(new RoutingRequest(1, ['soutien_juridique']), routerMap(), $pool, $onDuty)->contactId)->toBe(2)
        ->and($router->route(new RoutingRequest(1, ['hebergement']), routerMap(), $pool, $onDuty)->contactId)->toBe(11)
        ->and($router->route(new RoutingRequest(1, ['hebergement']), routerMap(), $pool)->contactId)->toBe(7);
});

it('renvoie une décision vide quand l’annuaire est vide', function (): void {
    $decision = (new AlertRouter)->route(new RoutingRequest(1, ['soutien_juridique']), routerMap(), []);

    expect($decision)->toEqual(RoutingDecision::none())
        ->and($decision->contactId)->toBeNull()
        ->and($decision->fallback)->toBeNull()
        ->and($decision->level)->toBe(0)
        ->and($decision->isRouted())->toBeFalse()
        ->and($decision->isFallback())->toBeFalse();
});

it('explique les 4 niveaux, marque le niveau retenu et signale le repli', function (): void {
    $explanation = (new AlertRouter)->explain(new RoutingRequest(1, ['hebergement']), routerMap(), [
        new RoutingCandidate(2, DirectoryRole::Juriste, 1),
        new RoutingCandidate(7, DirectoryRole::PointFocal, 1),
    ]);

    expect($explanation->levels)->toHaveCount(4)
        ->and(array_column($explanation->levels, 'fallback'))->toBe([RoutingFallback::RoleZone, RoutingFallback::FocalZone, RoutingFallback::FocalNational, RoutingFallback::FirstActive])
        ->and(array_column($explanation->levels, 'retained'))->toBe([false, true, false, false])
        ->and($explanation->retainedLevel()['matched'])->toBe(7)
        ->and($explanation->decision->isFallback())->toBeTrue()
        ->and(RoutingDecision::at(RoutingFallback::RoleZone, 2)->isFallback())->toBeFalse()
        ->and(RoutingDecision::at(RoutingFallback::FocalNational, 2)->isFallback())->toBeTrue()
        ->and(RoutingDecision::at(RoutingFallback::FirstActive, 2)->isFallback())->toBeTrue()
        ->and(RoutingDecision::manual(2)->isFallback())->toBeFalse();
});
