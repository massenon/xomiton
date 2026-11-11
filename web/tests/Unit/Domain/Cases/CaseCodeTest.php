<?php

declare(strict_types=1);

use App\Domain\Cases\CaseCode;
use App\Enums\CaseKind;

/*
 * Code de registre d'un dossier (RG-06, règle `case_code`, P9-01) : `VBG-0000` / `VDH-0000`, compteur par nature,
 * jamais réutilisé ; format strict pour la règle du catalogue, lecture tolérante pour une adresse ou une recherche.
 */

it('formate le compteur sur quatre chiffres avec le préfixe de la nature', function (): void {
    expect(CaseCode::first(CaseKind::Vbg)->value())->toBe('VBG-0001')
        ->and(CaseCode::of(CaseKind::Vdh, 42)->value())->toBe('VDH-0042')
        ->and((string) CaseCode::of(CaseKind::Vbg, 12345))->toBe('VBG-12345');
});

it('suit le dernier code de la même nature, le premier si aucun', function (): void {
    expect(CaseCode::after(CaseKind::Vbg, null)->value())->toBe('VBG-0001')
        ->and(CaseCode::after(CaseKind::Vbg, 'VBG-0041')->value())->toBe('VBG-0042')
        ->and(CaseCode::after(CaseKind::Vdh, 'VDH-9999')->value())->toBe('VDH-10000')
        ->and(CaseCode::after(CaseKind::Vbg, 'VBG-42')->value())->toBe('VBG-0043');
});

it('refuse de repartir de 1 sur un dernier code illisible ou d’une autre nature', function (string $last): void {
    CaseCode::after(CaseKind::Vbg, $last);
})->with(['VDH-0003', 'n’importe quoi'])->throws(InvalidArgumentException::class);

it('valide le format normalisé exact (règle case_code)', function (string $code, bool $valid): void {
    expect(CaseCode::isValid($code))->toBe($valid);
})->with([
    ['VBG-0042', true],
    ['VDH-0001', true],
    ['VBG-10000', true],
    ['vbg42', false],
    ['VBG-42', false],
    ['vbg-0042', false],
    ['ABC-0042', false],
    ['VBG-0042 ', false],
]);

it('lit une saisie tolérante sans jamais lever d’exception', function (string $input, ?string $expected): void {
    expect(CaseCode::tryParse($input)?->value())->toBe($expected);
})->with([
    ['VBG-0042', 'VBG-0042'],
    [' vbg 42 ', 'VBG-0042'],
    ['vdh-7', 'VDH-0007'],
    ['VBG0042', 'VBG-0042'],
    ['VBG-0000', null],
    ['SIG-ABCD', null],
    ['', null],
    ['VBG-1234567', null],
]);

it('décompose un code en nature et compteur', function (): void {
    $code = CaseCode::parse('VDH-0012');

    expect($code->kind)->toBe(CaseKind::Vdh)
        ->and($code->sequence)->toBe(12)
        ->and($code->next()->value())->toBe('VDH-0013')
        ->and($code->equals(CaseCode::tryParse('vdh 12')))->toBeTrue();
});

it('refuse un compteur nul ou qui déborde de la colonne', function (int $sequence): void {
    CaseCode::of(CaseKind::Vbg, $sequence);
})->with([0, -1, 10_000_000])->throws(InvalidArgumentException::class);
