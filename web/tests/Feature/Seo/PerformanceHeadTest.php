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

it('omet préchargement et CSS critique sans faire échouer la page quand Vite ne fournit rien', function (): void {
    $this->withoutVite();

    expect((string) $this->get('/')->assertOk()->getContent())
        ->not->toContain('<style nonce="')
        ->not->toContain('rel="preload" as="font"');
});

it('omet préchargement et CSS critique en vite dev (serveur Vite lancé)', function (): void {
    $hot = tempnam(sys_get_temp_dir(), 'vite-hot');
    file_put_contents($hot, 'http://localhost:5173');
    Vite::useHotFile($hot);

    try {
        expect((string) $this->get('/')->assertOk()->getContent())
            ->toContain('http://localhost:5173/@vite/client')
            ->not->toContain('<style nonce="')
            ->not->toContain('rel="preload" as="font"');
    } finally {
        @unlink($hot);
    }
});
