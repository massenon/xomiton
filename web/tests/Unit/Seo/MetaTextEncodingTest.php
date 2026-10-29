<?php

declare(strict_types=1);

use App\Application\Seo\MetaText;

it('ne laisse jamais un caractère UTF-8 amputé en fin de texte coupé (« à » finissant la coupe)', function () {
    // Une coupe qui tombe après « à » (C3 A0) : l'ancien rtrim() octet par octet retirait A0 et laissait un C3 orphelin.
    $title = MetaText::title(str_repeat('à', 60));
    $description = MetaText::description(str_repeat('à', 200));

    expect(mb_check_encoding($title, 'UTF-8'))->toBeTrue()
        ->and(mb_check_encoding($description, 'UTF-8'))->toBeTrue()
        ->and(str_ends_with($title, 'à…'))->toBeTrue()
        ->and(mb_strlen($description, 'UTF-8'))->toBeLessThanOrEqual(MetaText::DESCRIPTION_MAX);
});

it('retire toujours la ponctuation et les espaces en fin de coupe', function () {
    $text = MetaText::title('Se protéger, se tester, se soigner : trois gestes simples pour toutes les filles');

    expect(mb_check_encoding($text, 'UTF-8'))->toBeTrue()
        ->and($text)->toEndWith('…')
        ->and($text)->not->toMatch('/[,;:\s]…$/u');
});
