<?php

declare(strict_types=1);

namespace App\Enums;

use App\Rules\PhoneBj;

/**
 * Canal préféré de recontact d'une alerte (`alerts.preferred_channel`, design-system/xomiton/pages/signaler-et-sos.md :
 * SMS · WhatsApp · appel · e-mail).
 */
enum ContactChannel: string
{
    case Sms = 'sms';
    case Whatsapp = 'whatsapp';
    case Call = 'call';
    case Email = 'email';

    public function label(): string
    {
        return (string) __('enums/alerts.contact_channel.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Sms => 'info',
            self::Whatsapp => 'success',
            self::Call => 'primary',
            self::Email => 'secondary',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Sms => 'bi-chat-dots',
            self::Whatsapp => 'bi-whatsapp',
            self::Call => 'bi-telephone',
            self::Email => 'bi-envelope',
        };
    }

    /** Canal déduit d'un moyen de contact : e-mail → e-mail, téléphone → appel (demande de rappel, SOS). */
    public static function guessFor(?string $contact): ?self
    {
        if ($contact === null || trim($contact) === '') {
            return null;
        }

        if (filter_var(trim($contact), FILTER_VALIDATE_EMAIL) !== false) {
            return self::Email;
        }

        return PhoneBj::isValid($contact) ? self::Call : null;
    }
}
