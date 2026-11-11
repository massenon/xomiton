<?php

declare(strict_types=1);

/*
 * Composant x-newsletter-form (P0-07, P4-07) : variante pied de page (prototype nl_footer_form) et variante page
 * (/newsletter, x-field, centres d'intérêt) ; pot de miel masqué, consentement jamais pré-coché, lien de confidentialité.
 */

it('rend la variante pied de page du prototype : pot de miel, adresse, consentement non coché', function (): void {
    test()->blade('<x-newsletter-form />')
        ->assertSee('class="xm-nl-form"', false)
        ->assertSee('action="'.route('newsletter.subscribe').'"', false)
        ->assertSee('name="_token"', false)
        ->assertSee('name="nl_form" value="footer"', false)
        ->assertSee('<div class="visually-hidden" aria-hidden="true"><label for="nlWebsite">', false)
        ->assertSee('tabindex="-1" autocomplete="off"', false)
        ->assertSee('<label class="visually-hidden" for="nlEmail">'.__('footer.newsletter_email').'</label>', false)
        ->assertSee('type="email" id="nlEmail" name="email"', false)
        ->assertSee('id="nlConsent" name="consent" value="1" required', false)
        ->assertDontSee('checked', false)
        ->assertSee('href="'.route('confidentialite').'#confidentialite"', false)
        ->assertSee(__('footer.newsletter_subscribe'));
});

it('rend la variante page avec libellés visibles, centres d’intérêt facultatifs et identifiants distincts du pied de page', function (): void {
    test()->blade('<x-newsletter-form variant="page" :interests="$interests" />', ['interests' => ['sante' => 'Santé', 'droits' => 'Droits']])
        ->assertSee('id="inscription"', false)
        ->assertSee('name="nl_form" value="page"', false)
        ->assertSee('id="nlPageWebsite"', false)
        ->assertSee('<label class="form-label" for="f-email">'.__('newsletter.public.email_label').'</label>', false)
        ->assertSee('<legend class="form-label fs-6">'.__('newsletter.public.interests_label'), false)
        ->assertSee('name="interests[]" id="f-interests-0" value="sante"', false)
        ->assertSee('id="f-consent" name="consent" value="1" required', false)
        ->assertDontSee('id="nlEmail"', false)
        ->assertSee(__('newsletter.public.submit'));
});

it('prend le texte d’exemple de l’adresse dans lang/fr', function (): void {
    test()->blade('<x-newsletter-form variant="page" />')
        ->assertSee('placeholder="'.__('newsletter.public.email_placeholder').'"', false);
});

it('figure au catalogue /design-system, variante page en section et variante pied de page dans le pied', function (): void {
    $this->app['env'] = 'local';
    config(['app.env' => 'local']);

    $html = (string) $this->get('/design-system')->assertOk()->getContent();

    expect($html)->toContain('id="ds-newsletter"', 'href="#ds-newsletter"', 'name="nl_form" value="page"', 'name="nl_form" value="footer"')
        ->and(substr_count($html, 'id="nlEmail"'))->toBe(1)
        ->and(substr_count($html, 'id="f-email"'))->toBe(1);
});
