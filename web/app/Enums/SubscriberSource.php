<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Origine d'un abonnement à la newsletter (DATABASE_DICTIONARY.md §9 `newsletter_subscribers.source`).
 */
enum SubscriberSource: string
{
    case Footer = 'footer';
    case Import = 'import';
    case Admin = 'admin';
    case App = 'app';

    public function label(): string
    {
        return (string) __('enums/communication.subscriber_source.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Footer => 'primary',
            self::Import => 'info',
            self::Admin => 'secondary',
            self::App => 'violet',
        };
    }

    /** Inscription faite par la personne elle-même : la confirmation en double opt-in est requise. */
    public function requiresConfirmation(): bool
    {
        return $this === self::Footer || $this === self::App;
    }

    /**
     * Valeur du prototype (`newsletter_subscribers.source`, identique aux valeurs stockées).
     */
    public static function fromPrototype(string $source): self
    {
        return self::tryFrom(mb_strtolower(trim($source))) ?? self::Footer;
    }
}
