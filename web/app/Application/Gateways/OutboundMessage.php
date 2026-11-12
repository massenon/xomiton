<?php

declare(strict_types=1);

namespace App\Application\Gateways;

/**
 * Contenu d'une remise tel que l'envoie un connecteur (P14-04, P19-11) : le message d'accompagnement, le lien absolu vers
 * la page publique (BD : `/bd/{key}` avec paramètres UTM du réseau) et les images, dans l'ordre (visuel de partage puis
 * planches), chacune avec son texte alternatif. Aucune donnée de membre.
 */
final readonly class OutboundMessage
{
    /**
     * @param  list<array{path: string, alt: string}>  $media  chemins relatifs à `public/` (`assets/…`), fichiers existants
     */
    public function __construct(
        public string $text,
        public ?string $link = null,
        public array $media = [],
    ) {}

    /** Message puis lien, séparés par une ligne vide. */
    public function fullText(): string
    {
        return $this->link === null ? $this->text : trim($this->text."\n\n".$this->link);
    }

    /**
     * Message raccourci pour tenir dans `$max` caractères **avec** le lien (légende Telegram ≤ 1024) : le lien n'est
     * jamais coupé, le texte l'est d'abord, avec une ellipse.
     */
    public function fitted(int $max): string
    {
        $full = $this->fullText();

        if (mb_strlen($full) <= $max) {
            return $full;
        }

        $suffix = $this->link === null ? '' : "\n\n".$this->link;
        $room = max(0, $max - mb_strlen($suffix) - 1);

        return rtrim(mb_substr($this->text, 0, $room)).'…'.$suffix;
    }

    public function hasMedia(): bool
    {
        return $this->media !== [];
    }
}
