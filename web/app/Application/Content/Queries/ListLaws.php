<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Application\Content\Results\LawListing;
use App\Models\Law;

/**
 * Textes de loi de la page publique `/lois` (P4-04, prototype lois-benin.php) : textes publiés, dans l'ordre
 * `position` puis titre, avec les catégories distinctes dans leur ordre d'apparition (liste du filtre). Recherche et
 * filtre se font côté client sur ce balisage (aucune requête par frappe) ; la version simple retombe sur le résumé
 * quand elle est vide (prototype l.47). Lecture seule (ADR-29).
 */
final class ListLaws
{
    public function __invoke(): LawListing
    {
        $laws = Law::query()->published()->ordered()
            ->get(['id', 'slug', 'title', 'subtitle', 'summary', 'simple_summary', 'category', 'official_url', 'updated_at']);

        return new LawListing(
            laws: $laws->map(static fn (Law $law): array => [
                'slug' => (string) $law->slug,
                'title' => (string) $law->title,
                'subtitle' => $law->subtitle !== null && trim($law->subtitle) !== '' ? (string) $law->subtitle : null,
                'summary' => (string) $law->summary,
                'simple' => trim((string) $law->simple_summary) !== '' ? (string) $law->simple_summary : (string) $law->summary,
                'category' => (string) $law->category,
                'official_url' => self::safeUrl($law->official_url),
            ])->values()->all(),
            categories: $laws->pluck('category')->map(static fn (mixed $category): string => (string) $category)
                ->filter(static fn (string $category): bool => $category !== '')->unique()->values()->all(),
            updatedAt: $laws->max('updated_at')?->toDateString(),
        );
    }

    /** Lien officiel affiché seulement s'il est en http(s) (jamais `javascript:` ni relatif). */
    private static function safeUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        return $url !== '' && preg_match('#^https?://[^\s]+$#i', $url) === 1 && filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
