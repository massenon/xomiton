<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Vite;

/*
 * En-tête de performance (P4-12, PERFORMANCE.md §3 et §6) : CSS critique inliné avec nonce CSP, préchargement de la
 * police des titres, CSS principal toujours chargé. Les tests s'appuient sur public/build/manifest.json (vite build),
 * comme les autres tests de pages.
 */

it('inline le CSS critique avec le nonce CSP avant le CSS principal, sur le gabarit public', function (): void {
    $html = (string) $this->get('/')->assertOk()->getContent();
    $nonce = Vite::cspNonce();

    expect($html)
        ->toContain('<style nonce="'.$nonce.'">')
        ->toContain('.xm-skip-link')
        ->toContain('rel="preload" as="font" type="font/woff2" crossorigin')
        ->toContain('Montserrat-latin-normal')
        ->toMatch('~<link rel="stylesheet" href="[^"]*/build/assets/xomiton-[^"]+\.css"~');

    // Le CSS critique précède la feuille principale.
    expect(strpos($html, '<style nonce='))->toBeLessThan((int) strpos($html, '/build/assets/xomiton-'));
});

it('inline aussi le CSS critique sur le gabarit sobre, mais pas sur le gabarit d’authentification', function (): void {
    expect((string) $this->get('/urgences')->assertOk()->getContent())->toContain('<style nonce="');
    expect((string) $this->get('/connexion')->assertOk()->getContent())->not->toContain('<style nonce="');
});

it('tient le budget du CSS critique : moins de 14 Ko non compressés', function (): void {
    expect(strlen(Vite::content('resources/css/critical.css')))->toBeLessThan(14 * 1024);
});
