<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Application\Content\ViolencesNumeriques\ComicShare;
use App\Enums\OutboundKind;
use App\Enums\SocialNetwork;
use App\Models\CccTool;
use App\Models\OutboundPost;

/**
 * Prépare le contenu d'une remise pour un réseau (P19-11) : pour une BD **publique** (`/bd/{key}`), le message
 * d'accompagnement enregistré, le lien absolu vers la page publique avec des paramètres UTM simples
 * (`utm_source=<réseau>&utm_medium=social&utm_campaign=bd`) et les images lues dans `public/` : visuel de partage
 * 1080 × 1080 puis les planches, chacune avec son texte alternatif. BD réservée aux membres, message clé ou texte
 * personnalisé : le message seul (aucun lien public à donner). Un fichier absent est simplement omis.
 */
final class OutboundContent
{
    public const UTM_MEDIUM = 'social';

    public const UTM_CAMPAIGN = 'bd';

    /** Images au plus par publication (album Telegram : 10 ; visuel + 4 planches attendus). */
    public const MAX_MEDIA = 10;

    public function __construct(private readonly ComicShare $share) {}

    public function for(OutboundPost $post, SocialNetwork $network): OutboundMessage
    {
        $text = trim((string) $post->message);
        $kind = OutboundKind::tryFrom((string) ($post->getAttributes()['kind'] ?? ''));

        if ($kind !== OutboundKind::Bd) {
            return new OutboundMessage($text);
        }

        $tool = $this->publicComic((string) $post->reference);

        if ($tool === null) {
            return new OutboundMessage($text);
        }

        return new OutboundMessage($text, self::comicLink($tool, $network), $this->media($tool));
    }

    /** Message proposé par défaut pour une BD : son texte de partage, sinon titre + résumé. */
    public function defaultComicMessage(CccTool $tool): string
    {
        return $this->share->for($tool)['text'];
    }

    public static function comicLink(CccTool $tool, SocialNetwork $network): string
    {
        return route('bd.show', ['key' => (string) $tool->key]).'?'.http_build_query([
            'utm_source' => $network->value,
            'utm_medium' => self::UTM_MEDIUM,
            'utm_campaign' => self::UTM_CAMPAIGN,
        ]);
    }

    public function publicComic(string $key): ?CccTool
    {
        return $key === '' ? null : CccTool::query()->publicComics()->where('key', $key)->first();
    }

    /**
     * Visuel de partage puis planches (fichiers existants de `public/assets/` seulement).
     *
     * @return list<array{path: string, alt: string}>
     */
    public function media(CccTool $tool): array
    {
        $share = $this->share->for($tool);
        $items = [];

        if ($share['image'] !== null) {
            $items[] = ['path' => $share['image'], 'alt' => $share['alt']];
        }

        $raw = $tool->getAttribute('pages');

        foreach (is_array($raw) ? $raw : [] as $page) {
            if (is_array($page)) {
                $items[] = ['path' => ltrim(trim((string) ($page['image'] ?? '')), '/'), 'alt' => trim((string) ($page['alt'] ?? ''))];
            }
        }

        $kept = [];

        foreach ($items as $item) {
            if (PublicImage::exists($item['path']) && ! in_array($item['path'], array_column($kept, 'path'), true)) {
                $kept[] = $item;
            }
        }

        return array_slice($kept, 0, self::MAX_MEDIA);
    }
}
