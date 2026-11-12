<?php

declare(strict_types=1);

namespace App\Application\Content\ViolencesNumeriques;

use App\Models\CccTool;
use App\Models\Thematique;
use Throwable;

/**
 * Contenu de partage d'une BD pour les passerelles (P19-11) : texte de partage (`share.text` du `<slug>.json` du sujet),
 * visuel carré 1080 × 1080 (`share.image`) et son texte alternatif (`share.alt`). La BD est retrouvée dans les fichiers
 * par sa clé (`key`). Sans fichier (BD SSR du kit, sujet non produit) : titre + résumé de la BD, et le visuel de partage
 * de la fiche liée (`og_image_path`) s'il existe. Lecture tolérante : un fichier absent ou mal formé ne bloque jamais une
 * publication, le repli s'applique.
 */
final class ComicShare
{
    /** @var array<string, array<string, mixed>>|null sujets produits, par clé de BD */
    private ?array $topics = null;

    public function __construct(private readonly ViolencesNumeriquesSource $source) {}

    /**
     * @return array{text: string, image: string|null, alt: string}
     */
    public function for(CccTool $tool): array
    {
        $topic = $this->topic((string) $tool->key);
        $share = is_array($topic['share'] ?? null) ? $topic['share'] : [];
        $text = trim((string) ($share['text'] ?? ''));
        $image = trim((string) ($share['image'] ?? ''));

        if ($text === '') {
            $text = trim((string) $tool->title);
            $summary = trim((string) $tool->description);
            $text = $summary === '' ? $text : $text."\n\n".$summary;
        }

        if ($image === '' && $tool->thematique_id !== null) {
            $image = (string) Thematique::query()->whereKey($tool->thematique_id)->value('og_image_path');
        }

        return [
            'text' => $text,
            'image' => $image === '' ? null : ltrim($image, '/'),
            'alt' => trim((string) ($share['alt'] ?? '')),
        ];
    }

    /** @return array<string, mixed> */
    private function topic(string $key): array
    {
        if ($this->topics === null) {
            $this->topics = [];

            try {
                $categories = $this->source->categories();
            } catch (Throwable) {
                $categories = [];
            }

            foreach ($categories as $category) {
                foreach ($category['topics'] as $topic) {
                    if ($topic['status'] !== ViolencesNumeriquesSource::STATUS_PILOT) {
                        continue;
                    }

                    try {
                        $data = $this->source->topic($topic['slug']);
                    } catch (Throwable) {
                        continue; // fichier absent ou mal formé : repli titre + résumé pour cette BD
                    }

                    $this->topics[(string) $data['key']] = $data;
                }
            }
        }

        return $this->topics[$key] ?? [];
    }
}
