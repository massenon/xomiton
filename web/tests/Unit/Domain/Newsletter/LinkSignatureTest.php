<?php

declare(strict_types=1);

use App\Domain\Newsletter\LinkSignature;

/*
 * Signature HMAC des liens de suivi d'une campagne (P4-07, ADR-14) : 32 caractères hexadécimaux en minuscules,
 * vérification en temps constant insensible à la casse, refus d'une longueur ou d'un alphabet inattendus.
 */

it('signe en 32 caractères hexadécimaux minuscules, de façon déterministe, selon l’usage et les parties', function (): void {
    $signer = new LinkSignature('cle-de-test');
    $signature = $signer->sign(LinkSignature::CLICK, 42, 'https://xomiton.org/ressources');

    expect($signature)->toMatch('/^[a-f0-9]{32}$/')
        ->and(strlen($signature))->toBe(LinkSignature::LENGTH)
        ->and($signer->sign(LinkSignature::CLICK, 42, 'https://xomiton.org/ressources'))->toBe($signature)
        ->and($signer->sign(LinkSignature::OPEN, 42, 'https://xomiton.org/ressources'))->not->toBe($signature)
        ->and($signer->sign(LinkSignature::CLICK, 43, 'https://xomiton.org/ressources'))->not->toBe($signature)
        ->and($signer->sign(LinkSignature::CLICK, '42', 'https://xomiton.org/ressources'))->toBe($signature)
        ->and((new LinkSignature('autre-cle'))->sign(LinkSignature::CLICK, 42, 'https://xomiton.org/ressources'))->not->toBe($signature);
});

it('vérifie une signature quelle que soit sa casse, et refuse une longueur, un alphabet ou un contenu différents', function (): void {
    $signer = new LinkSignature('cle-de-test');
    $signature = $signer->sign(LinkSignature::OPEN, 7);

    expect($signer->verify($signature, LinkSignature::OPEN, 7))->toBeTrue()
        ->and($signer->verify(strtoupper($signature), LinkSignature::OPEN, 7))->toBeTrue()
        ->and($signer->verify(substr($signature, 0, 31), LinkSignature::OPEN, 7))->toBeFalse()
        ->and($signer->verify($signature.'0', LinkSignature::OPEN, 7))->toBeFalse()
        ->and($signer->verify(substr($signature, 0, 31).'g', LinkSignature::OPEN, 7))->toBeFalse()
        ->and($signer->verify('', LinkSignature::OPEN, 7))->toBeFalse()
        ->and($signer->verify($signature, LinkSignature::CLICK, 7))->toBeFalse()
        ->and($signer->verify($signature, LinkSignature::OPEN, 8))->toBeFalse()
        ->and($signer->verify(str_repeat('0', 32), LinkSignature::OPEN, 7))->toBeFalse();
});

it('refuse une clé vide', function (): void {
    new LinkSignature('');
})->throws(InvalidArgumentException::class);
