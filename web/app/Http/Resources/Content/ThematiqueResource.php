<?php

declare(strict_types=1);

namespace App\Http\Resources\Content;

use App\Application\Content\Results\ThematiqueSheet;
use App\Models\Faq;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Fiche thématique complète (P6-08, `GET thematiques/{slug}`, simulateur `thematique`) depuis ShowThematique : corps et
 * sections `[{title, body}]` (HTML nettoyé à l'écriture, profil purifier `content` : le client l'affiche sans
 * l'interpréter davantage), **avertissement de contenu** (`content_warning`, null si la fiche n'en porte pas), contacts
 * d'annuaire actifs et publics (Q19), fiches liées, ressource principale, FAQ de la catégorie (3 à 5 questions, sinon
 * aucune). La catégorie n'est renvoyée que si elle est un filtre de la liste.
 *
 * @property ThematiqueSheet $resource
 */
final class ThematiqueResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $sheet = $this->resource;
        $thematique = $sheet->thematique;

        return [
            'id' => (int) $thematique->getKey(),
            'slug' => (string) $thematique->slug,
            'title' => (string) $thematique->title,
            'summary' => $thematique->summary,
            'cover_url' => $thematique->cover_url,
            'reading_minutes' => (int) $thematique->reading_minutes,
            'category' => $sheet->category === null ? null : ThematiqueCardResource::category($sheet->category),
            'content_warning' => filled($thematique->content_warning) ? (string) $thematique->content_warning : null,
            'body' => filled($thematique->body) ? (string) $thematique->body : null,
            'sections' => array_values(array_map(static fn (array $section): array => [
                'title' => (string) ($section['title'] ?? ''),
                'body' => (string) ($section['body'] ?? ''),
            ], array_filter($thematique->sections ?? [], 'is_array'))),
            'contacts' => DirectoryContactResource::collection($sheet->contacts)->resolve($request),
            'related' => ThematiqueCardResource::collection($sheet->related)->resolve($request),
            'primary_resource' => $sheet->primaryResource === null ? null : ResourceCardResource::make($sheet->primaryResource)->resolve($request),
            'faqs' => $sheet->faqs->map(static fn (Faq $faq): array => [
                'id' => (int) $faq->getKey(),
                'question' => (string) $faq->question,
                'answer' => (string) $faq->answer,
            ])->values()->all(),
            'published_at' => $thematique->published_at?->toIso8601String(),
            'updated_at' => $thematique->updated_at?->toIso8601String(),
            'url' => route('thematiques.show', $thematique),
        ];
    }
}
