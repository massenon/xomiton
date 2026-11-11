<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * État d'un·e abonné·e à la newsletter (DATABASE_DICTIONARY.md §9 `newsletter_subscribers.status`,
 * prototype `nl_statuses()`).
 */
enum SubscriberStatus: string
{
    case Pending = 'en_attente';
    case Active = 'actif';
    case Unsubscribed = 'desabonne';
    case Bounced = 'rebond';

    public function label(): string
    {
        return (string) __('enums/communication.subscriber_status.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Active => 'success',
            self::Unsubscribed => 'secondary',
            self::Bounced => 'danger',
        };
    }

    /** Seul·es les abonné·es actif·ves reçoivent les campagnes (`nl_segment()`). */
    public function isMailable(): bool
    {
        return $this === self::Active;
    }

    /** Peut encore confirmer son inscription ou se réabonner (le rebond est définitif). */
    public function canResubscribe(): bool
    {
        return $this !== self::Bounced;
    }

    /**
     * Valeur du prototype (`newsletter_subscribers.status`, identique aux valeurs stockées).
     */
    public static function fromPrototype(string $status): self
    {
        return self::tryFrom(mb_strtolower(trim($status))) ?? self::Pending;
    }
}
