<?php

declare(strict_types=1);

namespace App\Application\Seo;

use Illuminate\Support\Str;

/**
 * Nœuds JSON-LD propres à une page, ajoutés au `@graph` de `<x-seo>` par son prop `schema` (FRONT_END_SEO_PERFECT_AUDIT.md,
 * SEO_CONTENT_GUIDE_INSTRUCTION.md §3). `<x-seo>` émet déjà Organization (`#org`), WebSite (`#site`), WebPage (`#page`)
 * et BreadcrumbList : les nœuds d'ici s'y rattachent par `@id`. Les URL sont absolues sur `app.url`.
 */
final class StructuredData
{
    /** Racine canonique du site, comme `<x-seo>` (`#org`, `#site`). */
    public static function root(): string
    {
        return rtrim((string) config('app.url'), '/').'/';
    }

    /**
     * `CollectionPage` + `ItemList` d'une liste de contenus.
     *
     * @param  list<array{url: string, name: string}>  $items  éléments affichés, dans l'ordre
     * @return array<string, mixed>
     */
    public static function collectionPage(string $canonical, string $name, int $total, array $items): array
    {
        return [
            '@type' => 'CollectionPage',
            '@id' => $canonical.'#collection',
            'url' => $canonical,
            'name' => $name,
            'inLanguage' => __('seo.in_language'),
            'isPartOf' => ['@id' => self::root().'#site'],
            'mainEntity' => [
                '@type' => 'ItemList',
                'numberOfItems' => $total,
                'itemListElement' => array_map(static fn (array $item, int $index): array => [
                    '@type' => 'ListItem',
                    'position' => $index + 1,
                    'url' => $item['url'],
                    'name' => $item['name'],
                ], $items, array_keys($items)),
            ],
        ];
    }

    /**
     * `Article` éditorial rattaché au nœud de page (`mainEntityOfPage` → `#page`), publié par l'organisation.
     *
     * @param  array{headline: string, description: string, section?: string|null, minutes?: int|null, published?: string|null, modified?: string|null, image?: string|null}  $article
     * @return array<string, mixed>
     */
    public static function article(string $canonical, array $article): array
    {
        $root = self::root();

        return array_filter([
            '@type' => 'Article',
            '@id' => $canonical.'#article',
            'headline' => Str::limit($article['headline'], 110, '…'),
            'description' => $article['description'],
            'inLanguage' => __('seo.in_language'),
            'articleSection' => $article['section'] ?? null,
            'timeRequired' => ! empty($article['minutes']) ? 'PT'.(int) $article['minutes'].'M' : null,
            'author' => ['@id' => $root.'#org'],
            'publisher' => ['@id' => $root.'#org'],
            'datePublished' => $article['published'] ?? null,
            'dateModified' => $article['modified'] ?? null,
            'image' => ! empty($article['image']) ? [$article['image']] : null,
            'mainEntityOfPage' => ['@id' => $canonical.'#page'],
            'isAccessibleForFree' => true,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * `FAQPage` : mêmes questions et réponses (texte brut) que l'accordéon visible, ou rien.
     *
     * @param  iterable<array{question: string, answer: string}>  $faqs
     * @return array<string, mixed>|null
     */
    public static function faqPage(string $canonical, iterable $faqs): ?array
    {
        $questions = [];

        foreach ($faqs as $faq) {
            $questions[] = [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']],
            ];
        }

        return $questions === [] ? null : [
            '@type' => 'FAQPage',
            '@id' => $canonical.'#faq',
            'inLanguage' => __('seo.in_language'),
            'mainEntity' => $questions,
        ];
    }
}
