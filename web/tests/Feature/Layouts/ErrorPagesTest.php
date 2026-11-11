<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\Route;

/*
 * Pages d'erreur resources/views/errors/{403,404,419,429,500} (gabarit sobre x-error-page, lang/fr/errors.php) :
 * jamais indexées, rappel de crise (SOS + contacts d'urgence), 403 avec le message de l'exception, 419 vous / tu.
 */

beforeEach(function (): void {
    Route::middleware('web')->group(function (): void {
        Route::get('/_test/403-message', static fn () => abort(403, 'Confirme ton e-mail pour continuer.'));
        Route::get('/_test/403-defaut', static fn () => abort(403));
        Route::get('/_test/419', static fn () => abort(419));
        Route::get('/_test/429', static fn () => abort(429, '', ['Retry-After' => '42']));
        Route::get('/_test/429-sans-delai', static fn () => abort(429));
        Route::get('/_test/500', static fn () => abort(500));
    });
});

it('rend la 404 dans le gabarit sobre, non indexée, avec le rappel de crise', function (): void {
    $this->get('/page-qui-n-existe-pas')
        ->assertNotFound()
        ->assertSee('data-xm-error="404"', false)
        ->assertSee(__('errors.404.title'))
        ->assertSee('href="'.route('aide.index').'"', false)
        ->assertSee('name="robots" content="noindex', false)
        ->assertSee('href="'.route('sos').'"', false)
        ->assertSee(__('errors.emergency.title'));
});

it('affiche le message porté par la 403, sinon le texte générique, jamais le message anglais', function (): void {
    $this->get('/_test/403-message')
        ->assertForbidden()
        ->assertSee('Confirme ton e-mail pour continuer.')
        ->assertDontSee(__('errors.403.text'));

    $this->get('/_test/403-defaut')
        ->assertForbidden()
        ->assertSee(__('errors.403.title'))
        ->assertSee(__('errors.403.text'))
        ->assertDontSee('Forbidden');
});

it('vouvoie la 419 pour un·e visiteur·se et tutoie une personne connectée', function (): void {
    $this->get('/_test/419')
        ->assertStatus(419)
        ->assertSee(__('errors.419.public.text'))
        ->assertDontSee(__('errors.419.member.text'));

    $this->actingAs(User::factory()->create())->get('/_test/419')
        ->assertStatus(419)
        ->assertSee(__('errors.419.member.text'))
        ->assertSee(__('errors.419.member.action'))
        ->assertDontSee(__('errors.419.public.text'));
});

it('annonce le délai de la 429 quand Retry-After est connu', function (): void {
    $this->get('/_test/429')
        ->assertStatus(429)
        ->assertSee(__('errors.429.text', ['seconds' => 42]));

    $this->get('/_test/429-sans-delai')
        ->assertStatus(429)
        ->assertSee(__('errors.429.text_no_delay'));
});

it('rend la 500 sans détail technique', function (): void {
    $this->get('/_test/500')
        ->assertStatus(500)
        ->assertSee('data-xm-error="500"', false)
        ->assertSee(__('errors.500.title'))
        ->assertDontSee('Stack trace')
        ->assertDontSee('vendor/laravel');
});
