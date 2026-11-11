<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\DoGlegbeContent;

/**
 * Contenu de la page publique `/do-glegbe` (P4-04, prototype do-glegbe.php) : « fiches » = thématiques publiées de la
 * catégorie `xomiton.content.do_glegbe_category` (ListThematiques, au plus `xomiton.content.do_glegbe_sheets` ; aucune
 * si la catégorie est vide, inactive ou absente) et affirmations du quiz vrai / faux (lang/fr/pages/do_glegbe.php),
 * gardées seulement si elles sont complètes (énoncé, réponse booléenne, corrigé). Lecture seule.
 */
final readonly class DoGlegbePage
{
    public function __construct(private ListThematiques $thematiques) {}

    public function __invoke(): DoGlegbeContent
    {
        $slug = (string) config('xomiton.content.do_glegbe_category', 'regles');
        $limit = max(1, (int) config('xomiton.content.do_glegbe_sheets', 4));
        $listing = $slug !== '' ? ($this->thematiques)($slug, '', $limit) : null;

        return new DoGlegbeContent(
            sheets: $listing?->thematiques ?? collect(),
            sheetsTotal: $listing->total ?? 0,
            sheetsCategory: $listing !== null ? $slug : null,
            quiz: self::quiz((array) trans('pages/do_glegbe.quiz.items')),
        );
    }

    /**
     * @param  array<mixed>  $items
     * @return list<array{statement: string, answer: bool, explanation: string}>
     */
    private static function quiz(array $items): array
    {
        $valid = array_filter($items, static fn (mixed $item): bool => is_array($item)
            && is_string($item['statement'] ?? null) && is_bool($item['answer'] ?? null) && is_string($item['explanation'] ?? null));

        return array_values(array_map(static fn (array $item): array => [
            'statement' => $item['statement'],
            'answer' => $item['answer'],
            'explanation' => $item['explanation'],
        ], $valid));
    }
}
