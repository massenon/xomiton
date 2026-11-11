<?php

declare(strict_types=1);

namespace App\Application\Mail;

/**
 * UNIQUE source des valeurs visuelles des e-mails Xomiton (Q-design e-mails, 2026-10-28).
 *
 * Les clients de messagerie ne supportent ni les variables CSS ni les polices web de façon fiable : les jetons
 * `--xm-*` de design-system/tokens/tokens.css sont donc recopiés ici en hexadécimal (jeton d'origine en commentaire).
 * Toute modification de la charte se répercute dans ce fichier seulement ; mise en page : resources/views/mail/layout.blade.php.
 */
final class EmailTheme
{
    public const WIDTH = 600;

    /** Largeur du PNG d'en-tête (2x de 180 px pour les écrans denses) ; jamais de SVG dans un e-mail. */
    public const LOGO_PATH = 'assets/images/xomiton/brand/logo-mono-blanc-email.png';

    public const LOGO_WIDTH = 180;

    public const LOGO_HEIGHT = 46;

    /** Montserrat n'est pas garantie : pile de repli (titres) puis Arial (texte), comme --xm-font-heading / --xm-font-body. */
    public const FONT_HEADING = "Montserrat,'Segoe UI',Arial,'Liberation Sans',Helvetica,sans-serif";

    public const FONT_BODY = "Arial,'Liberation Sans',Helvetica,sans-serif";

    /** Thème clair. @var array<string, string> */
    public const LIGHT = [
        'page' => '#FBF7FA',        // --xm-bg
        'card' => '#FFFFFF',        // --xm-surface
        'header' => '#0433C3',      // --xm-primary (bandeau du logo blanc)
        'text' => '#1B1630',        // --xm-text
        'muted' => '#5B5670',       // --xm-text-muted
        'border' => '#E4DCE6',      // --xm-border
        'footer' => '#F3EDF4',      // --xm-surface-muted
        'primary' => '#0433C3',     // --xm-primary
        'on_primary' => '#FFFFFF',  // --xm-on-primary
        'subtle' => '#EBEFFA',      // --xm-primary-subtle (fond du code)
        'subtle_text' => '#021C6B', // --xm-primary-subtle-text
    ];

    /** Thème sombre (prefers-color-scheme: dark). @var array<string, string> */
    public const DARK = [
        'page' => '#0E1020',        // --xm-neutral-d-0
        'card' => '#171A2E',        // --xm-neutral-d-100
        'header' => '#02134A',      // --xm-blue-900
        'text' => '#EDEAF7',        // --xm-neutral-d-900
        'muted' => '#A9A5C0',       // --xm-neutral-d-600
        'border' => '#2A2E48',      // --xm-neutral-d-200
        'footer' => '#0E1020',      // --xm-neutral-d-0
        'primary' => '#7C97FF',     // --xm-blue-dark-accent
        'on_primary' => '#0E1020',  // texte sombre sur l'accent clair (contraste AA)
        'subtle' => '#2A2E48',      // --xm-neutral-d-200
        'subtle_text' => '#EDEAF7',
    ];

    public static function logoUrl(): string
    {
        return self::https(asset(self::LOGO_PATH));
    }

    /** Les e-mails n'embarquent que des liens https. */
    public static function https(string $url): string
    {
        return (string) preg_replace('#^http://#i', 'https://', $url);
    }

    /**
     * Règles du mode sombre : classe => déclarations (appliquées avec !important sous prefers-color-scheme: dark
     * et pour Outlook.com via [data-ogsc]). Les styles en ligne clairs restent le repli.
     *
     * @return array<string, string>
     */
    public static function darkRules(): array
    {
        $d = self::DARK;

        return [
            '.xm-bg' => "background-color:{$d['page']} !important",
            '.xm-card' => "background-color:{$d['card']} !important",
            '.xm-hdr' => "background-color:{$d['header']} !important",
            '.xm-text' => "color:{$d['text']} !important",
            '.xm-muted' => "color:{$d['muted']} !important",
            '.xm-link' => "color:{$d['primary']} !important",
            '.xm-line' => "border-color:{$d['border']} !important",
            '.xm-foot' => "background-color:{$d['footer']} !important",
            '.xm-code' => "background-color:{$d['subtle']} !important;color:{$d['subtle_text']} !important",
            '.xm-btn-cell' => "background-color:{$d['primary']} !important",
            '.xm-btn' => "background-color:{$d['primary']} !important;color:{$d['on_primary']} !important",
        ];
    }
}
