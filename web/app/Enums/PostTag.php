<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Étiquette d'une publication du fil (DATABASE_DICTIONARY.md §4 `posts.tag`). Les libellés du
 * prototype (« Témoignage », « Événement »…) sont convertis par fromLabel() à l'import.
 */
enum PostTag: string
{
    case Testimony = 'temoignage';
    case Question = 'question';
    case Resource = 'ressource';
    case Event = 'evenement';
    case Violence = 'violences';
    case Poll = 'sondage';

    public function label(): string
    {
        return (string) __('enums/feed.post_tag.'.$this->value);
    }

    public function badge(): string
    {
        return match ($this) {
            self::Testimony => 'primary',
            self::Question => 'info',
            self::Resource => 'success',
            self::Event => 'violet',
            self::Violence => 'danger',
            self::Poll => 'warning',
        };
    }

    /**
     * Retrouve le cas depuis un libellé du prototype (insensible à la casse et aux accents).
     */
    public static function fromLabel(string $label): self
    {
        $normalized = mb_strtolower(trim($label));

        foreach (self::cases() as $case) {
            if (mb_strtolower($case->label()) === $normalized || $case->value === $normalized) {
                return $case;
            }
        }

        throw new \ValueError(sprintf('Étiquette de publication inconnue : « %s ».', $label));
    }
}
