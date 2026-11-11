<?php

declare(strict_types=1);

namespace App\Application\Mail;

/**
 * Point d'entrée UNIQUE de la mise en page des e-mails (envoi réel, aperçu admin, campagnes) : habille le corps
 * (EmailStyler), le place dans `mail.layout` et fabrique la version texte brut complète (pied et désinscription compris).
 */
final class EmailLayout
{
    /**
     * @return array{html: string, text: string}
     */
    public static function render(
        string $siteName,
        string $supportEmail,
        ?string $preheader,
        string $bodyHtml,
        ?string $unsubscribeUrl = null,
        ?string $pixelUrl = null,
    ): array {
        $html = view('mail.layout', [
            'siteName' => $siteName,
            'supportEmail' => $supportEmail,
            'preheader' => $preheader !== null && $preheader !== '' ? $preheader : null,
            'bodyHtml' => EmailStyler::apply($bodyHtml),
            'unsubscribeUrl' => $unsubscribeUrl,
            'pixelUrl' => $pixelUrl,
        ])->render();

        $text = mb_strtoupper($siteName)."\n\n".TemplateRenderer::toPlainText($bodyHtml)
            ."\n\n--\n".__('mail.footer.tagline', ['site' => $siteName])
            ."\n".__('mail.footer.help').' '.$supportEmail
            ."\n".__('mail.footer.privacy');

        if ($unsubscribeUrl !== null && $unsubscribeUrl !== '') {
            $text .= "\n\n".__('mail.footer.unsubscribe').' : '.$unsubscribeUrl;
        }

        return ['html' => $html, 'text' => $text];
    }
}
