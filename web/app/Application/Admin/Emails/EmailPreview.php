<?php

declare(strict_types=1);

namespace App\Application\Admin\Emails;

use App\Application\Mail\EmailTemplateService;
use App\Application\Mail\TemplateRenderer;
use App\Models\EmailTemplate;
use App\Support\EmailHtml;

/**
 * Aperçu d'un contenu **non enregistré** (P14-01, prototype `do=preview`) avec des données fictives : mêmes variables,
 * même mise en page que l'envoi réel (vue `mail.layout`). Aucune lecture de la base ni de donnée de membre.
 */
final class EmailPreview
{
    /** Valeurs fictives des variables usuelles ; toute autre variable s'affiche entre crochets. */
    private const SAMPLE = [
        'user_first_name' => 'Awa',
        'user_name' => 'Awa Houngbédji',
        'user_email' => 'awa@exemple.bj',
        'otp_code' => '482913',
        'otp_expiry' => '10 minutes',
        'reset_link' => 'https://exemple.bj/lien',
        'activation_link' => 'https://exemple.bj/lien',
        'unsubscribe_link' => 'https://exemple.bj/desinscription',
    ];

    /**
     * @return array<string, string>
     */
    public static function sampleVars(): array
    {
        return self::SAMPLE + EmailTemplateService::defaultVars();
    }

    /**
     * Variables proposées à la rédaction : celles de la plateforme, les valeurs fictives et celles déjà utilisées par
     * les templates enregistrés (nom => valeur fictive).
     *
     * @return array<string, string>
     */
    public static function availableVariables(): array
    {
        $names = array_keys(self::sampleVars());

        EmailTemplate::query()->get(['variables'])->each(static function (EmailTemplate $template) use (&$names): void {
            $names = array_merge($names, (array) $template->variables);
        });

        $names = array_values(array_unique($names));
        sort($names);

        return array_combine($names, array_map(static fn (string $name): string => self::sampleVars()[$name] ?? '['.$name.']', $names)) ?: [];
    }

    /**
     * @return array{subject: string, html: string, text: string, unknown: list<string>}
     */
    public static function render(string $subject, string $preheader, string $body): array
    {
        $vars = self::sampleVars();
        $bodyHtml = TemplateRenderer::render(EmailHtml::clean($body), $vars);
        $preheaderText = TemplateRenderer::render($preheader, $vars, false);

        $html = view('mail.layout', [
            'siteName' => $vars['site_name'],
            'supportEmail' => $vars['support_email'],
            'preheader' => $preheaderText !== '' ? $preheaderText : null,
            'bodyHtml' => $bodyHtml,
            'logoUrl' => asset('assets/images/xomiton/brand/logo-mono-blanc.svg'),
            'unsubscribeUrl' => null,
            'pixelUrl' => null,
        ])->render();

        $known = array_keys(self::availableVariables());

        return [
            'subject' => TemplateRenderer::render($subject, $vars, false),
            'html' => $html,
            'text' => TemplateRenderer::toPlainText($bodyHtml),
            'unknown' => array_values(array_diff(EmailTemplate::extractVariables($subject, $preheader, $body), $known)),
        ];
    }
}
