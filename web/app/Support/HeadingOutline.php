<?php

declare(strict_types=1);

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Hiérarchie de titres stricte pour le corps éditorial (SEO : H1 → H4 sans niveau sauté).
 *
 * Le corps vient de l'administration (profil Purifier `content` : h2, h3, h4 seulement) ; rien n'empêche d'y écrire un `h4`
 * juste après le H1 de la page ou un `h2` suivi d'un `h4`. À l'affichage, chaque intertitre est ramené à « au plus un niveau
 * sous le précédent », en partant du H1 de la page : le premier devient au plus un h2, un h4 sans h3 devient un h3.
 * Remonter d'un niveau (h4 → h2) reste libre. Le contenu stocké n'est pas modifié.
 */
final class HeadingOutline
{
    /** Niveau du titre qui précède le fragment : le H1 de la page. */
    private const PAGE_LEVEL = 1;

    public static function normalize(?string $html): string
    {
        $html = (string) $html;
        if ($html === '' || ! preg_match('/<h[2-6]\b/i', $html)) {
            return $html;
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8"?><div id="xm-outline">'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $doc->getElementById('xm-outline');
        if (! $root instanceof DOMElement) {
            return $html;
        }

        $nodes = (new DOMXPath($doc))->query('.//h2|.//h3|.//h4|.//h5|.//h6', $root);
        if ($nodes === false) {
            return $html;
        }

        $last = self::PAGE_LEVEL;
        // Liste figée avant de renommer : le renommage remplace le nœud dans l'arbre.
        foreach (iterator_to_array($nodes) as $heading) {
            if (! $heading instanceof DOMElement) {
                continue;
            }
            $level = (int) substr($heading->nodeName, 1);
            $allowed = min($level, $last + 1);
            if ($allowed !== $level) {
                $renamed = $doc->createElement('h'.$allowed);
                foreach ($heading->attributes as $attribute) {
                    $renamed->setAttribute($attribute->nodeName, (string) $attribute->nodeValue);
                }
                while ($heading->firstChild !== null) {
                    $renamed->appendChild($heading->firstChild);
                }
                $heading->parentNode?->replaceChild($renamed, $heading);
            }
            $last = $allowed;
        }

        $out = '';
        foreach ($root->childNodes as $child) {
            $out .= (string) $doc->saveHTML($child);
        }

        return $out;
    }
}
