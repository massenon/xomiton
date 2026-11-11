<?php

declare(strict_types=1);

use App\Domain\Community\ForumAudience;

/*
 * P8-05 : qui lit un sujet ou une réponse de forum (même règle que le fil, Q72, RG-02).
 */

it('applique la règle de lecture des forums', function (array $facts, bool $expected): void {
    expect(ForumAudience::allows(...$facts))->toBe($expected);
})->with([
    'membre, contenu publié' => [['isAuthor' => false, 'isPublished' => true], true],
    'auteur·rice, contenu masqué' => [['isAuthor' => true, 'isPublished' => false, 'forumActive' => false], true],
    'contenu masqué' => [['isAuthor' => false, 'isPublished' => false], false],
    'forum fermé' => [['isAuthor' => false, 'isPublished' => true, 'forumActive' => false], false],
    'bloquée par l’auteur·rice, signé' => [['isAuthor' => false, 'isPublished' => true, 'blockedByAuthor' => true], false],
    'bloquée par l’auteur·rice, anonyme' => [['isAuthor' => false, 'isPublished' => true, 'blockedByAuthor' => true, 'anonymous' => true], false],
    'auteur·rice bloqué·e par qui lit, signé' => [['isAuthor' => false, 'isPublished' => true, 'blockedByViewer' => true], false],
    'auteur·rice bloqué·e par qui lit, anonyme : reste lisible' => [['isAuthor' => false, 'isPublished' => true, 'blockedByViewer' => true, 'anonymous' => true], true],
]);
