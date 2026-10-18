<?php

declare(strict_types=1);

use App\Models\User;

/*
 * Gabarit membre (components.layouts.member) servi aux écrans provisoires des routes `auth` (PlaceholderController) :
 * en-tête connecté, sidenav, bandeau « Confirme ton e-mail » (Q12 c), SOS flottant, déconnexion en POST, jamais indexé.
 */

it('sert /fil dans le gabarit membre avec en-tête, sidenav, SOS flottant et déconnexion en POST', function (): void {
    $user = User::factory()->create(['pseudo' => 'Awa_S']);

    $this->actingAs($user)->get('/fil')
        ->assertOk()
        ->assertSee('class="xm-member', false)
        ->assertSee('class="xm-skip-link"', false)
        ->assertSee('id="memberHeader"', false)
        ->assertSee('assets/images/xomiton/brand/logo-', false)
        ->assertDontSee('assets/images/logo.svg', false)
        ->assertSee('id="memberSidenav"', false)
        ->assertSee('<main id="contenu" tabindex="-1"', false)
        ->assertSee('Awa_S')
        ->assertSee('class="xm-sos-fab', false)
        ->assertSee('<form method="post" action="'.route('deconnexion').'"', false)
        ->assertSee('name="robots" content="noindex', false)
        ->assertDontSee('id="pubHeader"', false)
        ->assertDontSee('xm-verify-banner', false);
});

it('affiche le bandeau « Confirme ton e-mail » tant que l’adresse n’est pas vérifiée', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get('/fil')
        ->assertOk()
        ->assertSee('xm-verify-banner', false)
        ->assertSee(__('auth.verify_banner.title'))
        ->assertSee('action="'.route('otp.resend').'"', false);
});
