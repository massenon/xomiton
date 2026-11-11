<?php

declare(strict_types=1);

use App\Application\Mail\TemplateRenderer;

/*
 * Variables {{nom}} des templates e-mails (port de x_mail_render()) : échappement, variables inconnues, version texte.
 */

it('remplace les variables en échappant les valeurs dans le HTML', function (): void {
    $html = TemplateRenderer::render('<p>Bonjour {{ user_first_name }}, code {{otp_code}}</p>', [
        'user_first_name' => '<b>Awa</b> & Cie',
        'otp_code' => '482913',
    ]);

    expect($html)->toBe('<p>Bonjour &lt;b&gt;Awa&lt;/b&gt; &amp; Cie, code 482913</p>');
});

it('laisse le sujet sans échappement et ignore la casse des noms de variables', function (): void {
    expect(TemplateRenderer::render('{{Site_Name}} : « {{otp_code}} »', ['site_name' => 'Xomiton & vous', 'otp_code' => '1'], false))
        ->toBe('Xomiton & vous : « 1 »');
});

it('laisse visibles les variables non fournies et les signale', function (): void {
    $text = 'Bonjour {{user_first_name}}, lien {{reset_link}} ({{site_name}})';

    expect(TemplateRenderer::render($text, ['site_name' => 'Xomiton']))->toBe('Bonjour {{user_first_name}}, lien {{reset_link}} (Xomiton)')
        ->and(TemplateRenderer::unknownVariables(['site_name' => 'Xomiton'], $text))->toBe(['user_first_name', 'reset_link'])
        ->and(TemplateRenderer::unknownVariables(['site_name' => 'x', 'user_first_name' => 'y', 'reset_link' => 'z'], $text))->toBe([]);
});

it('affiche une valeur nulle comme chaîne vide', function (): void {
    expect(TemplateRenderer::render('<p>{{user_first_name}}</p>', ['user_first_name' => null]))->toBe('<p></p>');
});

it('convertit le HTML en texte lisible', function (): void {
    $text = TemplateRenderer::toPlainText('<h2>Bonjour&nbsp;Awa,</h2><p>Voici ton code&nbsp;:</p><h1>482913</h1><ul><li>Un</li><li>Deux</li></ul>');

    expect($text)->toBe("Bonjour Awa,\n\nVoici ton code :\n\n482913\n\nUn\nDeux");
});
