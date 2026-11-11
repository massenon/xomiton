<?php

declare(strict_types=1);

use App\Domain\Search\SearchTerm;
use App\Domain\Search\SearchType;

/*
 * Domaine de la recherche membre (P8-13) : familles ouvertes selon l'annuaire et le réseau, dans l'ordre d'affichage ;
 * terme assez long pour lancer une requête.
 */

it('ouvre les contacts avec l’annuaire et les membres avec le réseau, dans l’ordre d’affichage', function (bool $directory, bool $network, array $expected): void {
    expect(array_map(static fn (SearchType $type): string => $type->value, SearchType::availableTo($directory, $network)))->toBe($expected);
})->with([
    'membre' => [true, true, ['ressources', 'thematiques', 'contacts', 'membres']],
    'sans annuaire' => [false, true, ['ressources', 'thematiques', 'membres']],
    'sans réseau' => [true, false, ['ressources', 'thematiques', 'contacts']],
    'contenus seuls' => [false, false, ['ressources', 'thematiques']],
]);

it('ne lance la recherche qu’à partir de la longueur minimale (caractères, pas octets)', function (): void {
    expect(SearchTerm::of('é')->isSearchable(2))->toBeFalse()
        ->and(SearchTerm::of('éa')->isSearchable(2))->toBeTrue()
        ->and(SearchTerm::of('é')->length())->toBe(1)
        ->and(SearchTerm::none()->isEmpty())->toBeTrue()
        ->and(SearchTerm::of('ab')->isSearchable(0))->toBeTrue();
});
