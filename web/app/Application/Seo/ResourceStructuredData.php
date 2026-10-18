<?php

declare(strict_types=1);

namespace App\Application\Seo;

use App\Enums\ResourceKind;
use App\Models\Resource;
use Illuminate\Support\Str;

/**
 * Nœud JSON-LD de la fiche `/ressources/{slug}` selon son type (P4-03, décision par défaut, plan §7 : l'audit SEO ne
 * définit pas la fiche ressource) : `Article` pour une fiche pratique ou un guide (StructuredData::article),
 * `DigitalDocument` pour un PDF, `VideoObject` / `AudioObject` pour un média, rien pour un lien externe (le `WebPage` de
 * `<x-seo>` suffit). Rattaché au nœud de page (`mainEntityOfPage` → `#page`) et à l'organisation (`#org`).
 */
final class ResourceStructuredData
{
    /**
     * @param  array{description: string, image: string, published: string|null, modified: string|null, downloadUrl: string|null, streamUrl?: string|null, fileSize: string|null}  $data
     * @return array<string, mixed>|null
     */
    public static function for(Resource $resource, string $canonical, array $data): ?array
    {
        $type = match ($resource->kind) {
            ResourceKind::Sheet, ResourceKind::Guide => 'Article',
            ResourceKind::Pdf => 'DigitalDocument',
            ResourceKind::Video => 'VideoObject',
            ResourceKind::Audio => 'AudioObject',
            default => null,
        };

        if ($type === 'Article') {
            return StructuredData::article($canonical, [
                'headline' => (string) $resource->title,
                'description' => $data['description'],
                'section' => $resource->category?->label,
                'minutes' => $resource->duration_minutes,
                'published' => $data['published'],
                'modified' => $data['modified'],
                'image' => $data['image'],
            ]);
        }

        if ($type === null) {
            return null;
        }

        $root = StructuredData::root();
        $minutes = (int) $resource->duration_minutes;
        // Média hébergé : la source du lecteur (fichier direct, plages Range), sinon le téléchargement ou le lien externe.
        $contentUrl = ($data['streamUrl'] ?? null) ?? $data['downloadUrl'] ?? ($resource->isExternal() ? (string) $resource->external_url : null);
        $isMedia = in_array($type, ['VideoObject', 'AudioObject'], true);

        return array_filter([
            '@type' => $type,
            '@id' => $canonical.'#work',
            'name' => Str::limit((string) $resource->title, 110, '…'),
            'headline' => $type === 'DigitalDocument' ? Str::limit((string) $resource->title, 110, '…') : null,
            'description' => $data['description'],
            'inLanguage' => __('seo.in_language'),
            'about' => $resource->category?->label,
            'image' => [$data['image']],
            'thumbnailUrl' => $isMedia ? $data['image'] : null,
            'author' => ['@id' => $root.'#org'],
            'publisher' => ['@id' => $root.'#org'],
            'datePublished' => $data['published'],
            'uploadDate' => $isMedia ? $data['published'] : null,
            'dateModified' => $data['modified'],
            'duration' => $isMedia && $minutes > 0 ? 'PT'.$minutes.'M' : null,
            'encodingFormat' => $type === 'DigitalDocument' ? 'application/pdf' : null,
            'contentSize' => $data['fileSize'],
            'url' => $type === 'DigitalDocument' ? $data['downloadUrl'] : null,
            'contentUrl' => $isMedia ? $contentUrl : null,
            'mainEntityOfPage' => ['@id' => $canonical.'#page'],
            'isAccessibleForFree' => true,
        ], static fn (mixed $value): bool => $value !== null);
    }
}
