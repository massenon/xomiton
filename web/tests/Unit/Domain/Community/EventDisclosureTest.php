<?php

declare(strict_types=1);

use App\Domain\Community\EventDisclosure;

/*
 * P8-04 : ce qu'un événement dévoile, et à qui (simulateur.html l.1164, Q35 a).
 */

it('ne révèle l’adresse exacte qu’aux participant·es confirmé·es, à l’organisation et à la modération', function (bool $going, bool $organizer, bool $moderator, bool $expected): void {
    expect(EventDisclosure::revealsExactPlace($going, $organizer, $moderator))->toBe($expected);
})->with([
    'simple membre' => [false, false, false, false],
    'participante' => [true, false, false, true],
    'organisatrice' => [false, true, false, true],
    'modération' => [false, false, true, true],
]);

it('n’ouvre au public qu’un événement public, publié, hors groupe privé et page non approuvée', function (bool $public, bool $published, ?bool $group, ?bool $page, bool $expected): void {
    expect(EventDisclosure::isOpenToPublic($public, $published, $group, $page))->toBe($expected);
})->with([
    'public sans groupe ni page' => [true, true, null, null, true],
    'public d’un groupe public' => [true, true, true, null, true],
    'public d’un groupe privé' => [true, true, false, null, false],
    'public d’une page non approuvée' => [true, true, null, false, false],
    'brouillon public' => [true, false, null, null, false],
    'réservé aux membres' => [false, true, null, null, false],
]);

it('n’accepte « Je participe » que sur un événement publié et pas terminé', function (): void {
    expect(EventDisclosure::acceptsResponses(true, true))->toBeTrue()
        ->and(EventDisclosure::acceptsResponses(true, false))->toBeFalse()
        ->and(EventDisclosure::acceptsResponses(false, true))->toBeFalse();
});
