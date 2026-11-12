<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Type d'un événement (P18-14 ; DATABASE_DICTIONARY.md `events.type`) : groupe de parole, atelier, rencontre ou autre
 * (défaut des événements existants et d'un événement créé sans type).
 */
enum EventType: string
{
    case SupportGroup = 'groupe_parole';
    case Workshop = 'atelier';
    case Meetup = 'rencontre';
    case Other = 'autre';

    public function label(): string
    {
        return (string) __('enums/community.event_type.'.$this->value);
    }

    public function icon(): string
    {
        return match ($this) {
            self::SupportGroup => 'chat-heart',
            self::Workshop => 'tools',
            self::Meetup => 'people',
            self::Other => 'calendar-event',
        };
    }

    /** Lecture tolérante d'une valeur brute (colonne absente ou valeur inconnue → « Autre »). */
    public static function fromRaw(mixed $value): self
    {
        return self::tryFrom(is_string($value) ? $value : '') ?? self::Other;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $type): array => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
