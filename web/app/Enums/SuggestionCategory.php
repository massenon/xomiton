<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Catégorie d'une suggestion de la boîte à idées (P18-14 ; `suggestions.category`).
 */
enum SuggestionCategory: string
{
    case Feature = 'fonctionnalite';
    case Content = 'contenu';
    case Safety = 'securite';
    case Other = 'autre';

    public function label(): string
    {
        return (string) __('enums/community.suggestion_category.'.$this->value);
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(static fn (self $category): array => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
