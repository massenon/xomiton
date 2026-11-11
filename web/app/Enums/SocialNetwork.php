<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Réseau social relié par une passerelle (DATABASE_DICTIONARY.md §8 `social_channels.network`).
 * Les réseaux sans API publique (Roméo, GanXo, Tinder) ne reçoivent que des publications manuelles assistées.
 */
enum SocialNetwork: string
{
    case Facebook = 'facebook';
    case TikTok = 'tiktok';
    case Romeo = 'romeo';
    case GanXo = 'ganxo';
    case Telegram = 'telegram';
    case Tinder = 'tinder';

    public function label(): string
    {
        return (string) __('enums/gateways.social_network.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Facebook => 'primary',
            self::TikTok => 'secondary',
            self::Romeo => 'danger',
            self::GanXo => 'violet',
            self::Telegram => 'info',
            self::Tinder => 'warning',
        };
    }

    /** Icône Bootstrap Icons du prototype (x_social_gateways()). */
    public function defaultIcon(): string
    {
        return match ($this) {
            self::Facebook => 'bi-facebook',
            self::TikTok => 'bi-tiktok',
            self::Romeo => 'bi-heart-fill',
            self::GanXo => 'bi-chat-heart-fill',
            self::Telegram => 'bi-telegram',
            self::Tinder => 'bi-fire',
        };
    }

    /** Couleur de marque du prototype (x_social_gateways()). */
    public function defaultColor(): string
    {
        return match ($this) {
            self::Facebook => '#1877F2',
            self::TikTok => '#000000',
            self::Romeo => '#E4007C',
            self::GanXo => '#7A2BBE',
            self::Telegram => '#229ED9',
            self::Tinder => '#FF5864',
        };
    }

    /** Le réseau expose une API de publication : sinon, publication manuelle assistée (D4-bis). */
    public function supportsApi(): bool
    {
        return match ($this) {
            self::Facebook, self::Telegram, self::TikTok => true,
            self::Romeo, self::GanXo, self::Tinder => false,
        };
    }

    /**
     * Réseau à partir du libellé du prototype (`gateways.json` : « Roméo », « GanXo »…).
     */
    public static function fromPrototype(string $name): ?self
    {
        foreach (self::cases() as $case) {
            if (mb_strtolower($case->label()) === mb_strtolower(trim($name))) {
                return $case;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return array_combine(
            array_column(self::cases(), 'value'),
            array_map(static fn (self $case): string => $case->label(), self::cases()),
        );
    }
}
