<?php

declare(strict_types=1);

use App\Support\SmallCellMask;

/*
 * P10-04 : masquage des petits effectifs — une valeur de 1 à 4 est masquée, 5 est affichée, 0 aussi (aucune personne).
 */

it('masque un effectif de 4 et affiche 5', function (): void {
    expect(SmallCellMask::isMasked(4))->toBeTrue()
        ->and(SmallCellMask::mask(4))->toBeNull()
        ->and(SmallCellMask::isMasked(5))->toBeFalse()
        ->and(SmallCellMask::mask(5))->toBe(5);
});

it('masque de 1 à 4 seulement : zéro et les grands effectifs restent affichés', function (): void {
    expect(array_map(SmallCellMask::isMasked(...), [0, 1, 2, 3, 4, 5, 6, 1000]))->toBe([false, true, true, true, true, false, false, false])
        ->and(SmallCellMask::maskAll(['a' => 0, 'b' => 3, 'c' => 12]))->toBe(['a' => 0, 'b' => null, 'c' => 12])
        ->and(SmallCellMask::MIN)->toBe(5);
});

it('masque en plus la plus petite cellule restante quand une seule est masquée (suppression complémentaire)', function (): void {
    // Une seule cellule masquée se retrouverait par différence avec un total connu : la plus petite autre l'est aussi.
    expect(SmallCellMask::maskGroup(['a' => 5, 'b' => 4]))->toBe(['a' => null, 'b' => null])
        ->and(SmallCellMask::maskGroup(['a' => 40, 'b' => 3, 'c' => 9, 'd' => 12]))->toBe(['a' => 40, 'b' => null, 'c' => null, 'd' => 12])
        ->and(SmallCellMask::maskGroup(['a' => 0, 'b' => 2, 'c' => 7]))->toBe(['a' => 0, 'b' => null, 'c' => null])
        // Deux cellules masquées ou aucune : rien à ajouter. Une cellule à zéro ne passe qu'en dernier recours.
        ->and(SmallCellMask::maskGroup(['a' => 1, 'b' => 2, 'c' => 30]))->toBe(['a' => null, 'b' => null, 'c' => 30])
        ->and(SmallCellMask::maskGroup(['a' => 10, 'b' => 20]))->toBe(['a' => 10, 'b' => 20])
        ->and(SmallCellMask::maskGroup(['a' => 3, 'b' => 0]))->toBe(['a' => null, 'b' => null])
        ->and(SmallCellMask::maskGroup(['a' => 3]))->toBe(['a' => null]);
});
