<?php

declare(strict_types=1);

use App\Application\Admin\Roles\TeamInvitations;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/*
 * Repli web des liens universels (/invitation/*, /signup/*, /alerte/*) : 302 vers /connexion, réponse identique que la
 * ressource existe ou non, aucune redirection externe, en-têtes sûrs, AASA inchangé.
 */

it('redirige /invitation/{code} et /signup/{code} vers /connexion avec le message et le lien d’inscription', function (string $path): void {
    $response = $this->get($path.'/ABCD2345');

    $response->assertStatus(302)->assertRedirect(route('connexion'));
    expect($response->headers->get('Location'))->not->toContain('ABCD2345');

    $this->followingRedirects()->get($path.'/ABCD2345')
        ->assertOk()
        ->assertSee('Ouvrez ce lien dans l’application Xomiton, ou connectez-vous / créez votre compte ici.', false)
        ->assertSee('aria-live="polite"', false)
        ->assertSee('Créer un compte avec cette invitation')
        ->assertSee(e(route('inscription', ['invitation' => 'ABCD2345'])), false);
})->with(['/invitation', '/signup']);

it('redirige /alerte/{id} vers /connexion sans lien d’inscription ni écho de l’identifiant', function (): void {
    $this->get('/alerte/01HZZZZZZZZZZZZZZZZZZZZZZZ')->assertStatus(302)->assertRedirect(route('connexion'));

    $this->followingRedirects()->get('/alerte/01HZZZZZZZZZZZZZZZZZZZZZZZ')
        ->assertOk()
        ->assertSee('connectez-vous ici pour accéder à votre espace')
        ->assertDontSee('Créer un compte avec cette invitation')
        ->assertDontSee('01HZZZZZZZZZZZZZZZZZZZZZZZ');
});

it('ne révèle pas si l’invitation existe : même réponse pour un code valide et un code inconnu', function (): void {
    $this->seed(Database\Seeders\RolesAndPermissionsSeeder::class);
    $admin = User::factory()->create();
    $admin->syncRoles(['admin']);
    $valid = app(TeamInvitations::class)->create($admin, 'pair')['code'];

    $a = $this->get('/invitation/'.$valid);
    $b = $this->get('/invitation/ZZZZ9999');

    expect($a->getStatusCode())->toBe(302)->and($b->getStatusCode())->toBe(302)
        ->and($a->headers->get('Location'))->toBe($b->headers->get('Location'))
        ->and($a->headers->get('Cache-Control'))->toBe($b->headers->get('Cache-Control'));
});

it('n’accepte aucune URL externe : redirection toujours vers /connexion', function (string $path): void {
    foreach (['https%3A%2F%2Fevil.example', '%2F%2Fevil.example', 'x'] as $payload) {
        $response = $this->get($path.'/'.$payload.'?next=https://evil.example&redirect=//evil.example');
        $response->assertStatus(302);
        expect($response->headers->get('Location'))->toBe(route('connexion'));
    }
})->with(['/invitation', '/signup', '/alerte']);

it('pose Referrer-Policy no-referrer, interdit le cache et ne met pas la réponse en cache invité', function (string $path): void {
    $response = $this->get($path.'/ABCD2345');

    expect($response->headers->get('Referrer-Policy'))->toBe('no-referrer')
        ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')->not->toContain('public')
        ->and($response->headers->has('ETag'))->toBeFalse()
        ->and($response->headers->get('Location'))->not->toContain('ABCD2345');
    // La session nécessaire au message flash est bien posée (pas supprimée par CacheableGuestPage).
    expect(collect($response->headers->getCookies())->map->getName()->all())->toContain(config('session.cookie'));
})->with(['/invitation', '/signup', '/alerte']);

it('n’écrit pas le code d’invitation dans les journaux applicatifs', function (): void {
    Log::spy();

    $this->get('/signup/SECRETCODE23');

    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('error');
});

it('laisse l’AASA et assetlinks en 200 JSON sans redirection', function (): void {
    foreach (['/.well-known/apple-app-site-association', '/.well-known/assetlinks.json'] as $path) {
        $response = $this->get($path);
        $response->assertOk();
        expect($response->headers->get('Location'))->toBeNull()
            ->and($response->headers->get('Content-Type'))->toStartWith('application/json');
    }
});
