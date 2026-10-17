<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canal d'une notification d'alerte (DATABASE_DICTIONARY.md §5 `alert_notifications.channel`).
 * `database` = cloche de l'administration ; `push` = FCM / Web Push du contact lié.
 */
enum NotificationChannel: string
{
    case Push = 'push';
    case Sms = 'sms';
    case Mail = 'mail';
    case Database = 'database';

    public function label(): string
    {
        return (string) __('enums/alerts.notification_channel.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Push => 'primary',
            self::Sms => 'success',
            self::Mail => 'info',
            self::Database => 'secondary',
        };
    }

    /** Canal qui dépend d'un fournisseur externe (soumis au délai `provider_timeout_seconds` et au rejeu). */
    public function isExternal(): bool
    {
        return $this !== self::Database;
    }
}
