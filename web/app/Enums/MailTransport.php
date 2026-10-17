<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canal réellement utilisé pour un envoi d'e-mail (DATABASE_DICTIONARY.md §9 `mail_logs.transport`) :
 * SMTP direct, dépôt dans `mail_outbox` faute de SMTP, ou aucun (adresse invalide, prototype « - »).
 */
enum MailTransport: string
{
    case Smtp = 'smtp';
    case Outbox = 'outbox';
    case None = 'none';

    public function label(): string
    {
        return (string) __('enums/communication.mail_transport.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Smtp => 'success',
            self::Outbox => 'warning',
            self::None => 'secondary',
        };
    }

    /** L'e-mail a quitté la plateforme ou attend de le faire : seul `none` ne produit aucun message. */
    public function isDelivering(): bool
    {
        return $this !== self::None;
    }

    /**
     * Valeur du prototype (`mail_logs.transport` : « smtp », « outbox » ou « - » pour une adresse invalide).
     */
    public static function fromPrototype(string $transport): self
    {
        return match (mb_strtolower(trim($transport))) {
            'smtp' => self::Smtp,
            'outbox' => self::Outbox,
            default => self::None,
        };
    }
}
