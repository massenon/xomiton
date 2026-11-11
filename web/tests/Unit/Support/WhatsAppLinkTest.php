<?php

declare(strict_types=1);

use App\Enums\DirectoryRole;
use App\Support\WhatsAppLink;

/*
 * Lien WhatsApp d'une fiche de l'annuaire (P8-12 ; prototype x_wa($r['whatsapp'] ?? $r['phone'])) : numéro WhatsApp
 * sinon téléphone, numéro béninois local mis au format international (indicatif 229), autre numéro seulement avec son
 * indicatif ; couleur de rôle côté membre (Q62).
 */

it('ajoute l’indicatif 229 à un numéro béninois local', function (string $number, string $expected): void {
    expect(WhatsAppLink::for($number))->toBe($expected);
})->with([
    'plan 2024 local' => ['01 97 38 88 47', 'https://wa.me/2290197388847'],
    'ancien plan à 8 chiffres' => ['97.38.88.47', 'https://wa.me/2290197388847'],
    'déjà international' => ['+229 01 97 38 88 47', 'https://wa.me/2290197388847'],
    'préfixe 00229' => ['00229 01 97 38 88 47', 'https://wa.me/2290197388847'],
    'étranger avec indicatif' => ['+33 6 12 34 56 78', 'https://wa.me/33612345678'],
    'étranger en 00' => ['0033 6 12 34 56 78', 'https://wa.me/33612345678'],
]);

it('prend le téléphone quand le champ WhatsApp est vide, et rien sans numéro exploitable', function (): void {
    expect(WhatsAppLink::for(null, '01 97 38 88 47'))->toBe('https://wa.me/2290197388847')
        ->and(WhatsAppLink::for('  ', '01 97 38 88 47'))->toBe('https://wa.me/2290197388847')
        ->and(WhatsAppLink::for('01 61 00 00 02', '01 97 38 88 47'))->toBe('https://wa.me/2290161000002')
        ->and(WhatsAppLink::for(null, null))->toBeNull()
        ->and(WhatsAppLink::for('123'))->toBeNull()
        ->and(WhatsAppLink::for('06 12 34 56 78 90'))->toBeNull();
});

it('remplace le rouge et le gris des rôles par la couleur primaire côté membre (Q62)', function (): void {
    expect(DirectoryRole::PointFocal->memberBadge())->toBe('primary')
        ->and(DirectoryRole::TravailleurSocial->memberBadge())->toBe('primary')
        ->and(DirectoryRole::Juriste->memberBadge())->toBe('info')
        ->and(DirectoryRole::PointFocal->badge())->toBe('danger');
});
