<?php

declare(strict_types=1);

use App\Domain\Directory\DirectoryReach;

/*
 * P8-12 : portée de l'annuaire membre (ROLE_ET_PERMISSIONS.md §3 ; RG-03 ; Q51) — sans `directory.view` aucune fiche,
 * fiches publiques par défaut, réseau seulement pour un compte actif du réseau ; rappel vers une fiche publique seule.
 */

it('déduit la portée du droit, de l’état du compte et de l’appartenance au réseau', function (bool $canView, bool $active, bool $network, DirectoryReach $expected): void {
    expect(DirectoryReach::of($canView, $active, $network))->toBe($expected);
})->with([
    'sans directory.view' => [false, true, true, DirectoryReach::None],
    'membre' => [true, true, false, DirectoryReach::Public],
    'pair en attente (RG-03)' => [true, false, true, DirectoryReach::Public],
    'pair validé·e, modération, administration' => [true, true, true, DirectoryReach::Network],
]);

it('ouvre les fiches non publiques au réseau seul et le rappel aux fiches publiques seules', function (): void {
    expect(DirectoryReach::None->canBrowse())->toBeFalse()
        ->and(DirectoryReach::Public->seesNonPublic())->toBeFalse()
        ->and(DirectoryReach::Network->seesNonPublic())->toBeTrue()
        ->and(DirectoryReach::Network->canRequestCallback(false))->toBeFalse()
        ->and(DirectoryReach::Public->canRequestCallback(true))->toBeTrue()
        ->and(DirectoryReach::None->canRequestCallback(true))->toBeFalse();
});
