<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catégorie d'un modèle d'e-mail (DATABASE_DICTIONARY.md §9 `email_templates.category`).
 */
enum TemplateCategory: string
{
    case Authentication = 'authentification';
    case Community = 'communaute';
    case Alerts = 'alertes';
    case Newsletter = 'newsletter';
    case Other = 'autre';

    public function label(): string
    {
        return (string) __('enums/communication.template_category.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Authentication => 'primary',
            self::Community => 'success',
            self::Alerts => 'danger',
            self::Newsletter => 'info',
            self::Other => 'secondary',
        };
    }

    /**
     * Libellé du prototype (`email_templates.json` : « Authentification », « Communauté »…).
     */
    public static function fromPrototype(string $category): self
    {
        return match (mb_strtolower(trim($category))) {
            'authentification' => self::Authentication,
            'communauté', 'communaute' => self::Community,
            'alertes', 'alerte' => self::Alerts,
            'newsletter' => self::Newsletter,
            default => self::Other,
        };
    }
}
