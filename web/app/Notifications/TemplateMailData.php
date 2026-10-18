<?php

declare(strict_types=1);

namespace App\Notifications;

/**
 * Ce que `toMail()` renvoie pour une notification Xomiton : le slug d'un template de `email_templates`, ses variables
 * et le contexte journalisé dans `mail_logs`. Le canal `mail` (App\Notifications\Channels\TemplateMailChannel) le passe à
 * `EmailTemplateService::send()` : aucun Mailable Markdown, la mise en page et les textes restent administrables.
 */
final readonly class TemplateMailData
{
    /**
     * @param  array<string, scalar|\Stringable|null>  $variables
     */
    public function __construct(
        public string $slug,
        public array $variables = [],
        public ?string $context = null,
    ) {}
}
