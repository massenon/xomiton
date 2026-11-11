<?php

declare(strict_types=1);

namespace App\Domain\Feed;

use App\Enums\PostVisibility;

/**
 * Règle de visibilité d'une publication (P7-01, FR-SOC-01 : communauté, abonné·es, moi), sans base de données : l'appelant
 * fournit les faits (auteur·rice ? abonné·e ? blocage ?). La même règle est traduite en requête par
 * App\Application\Feed\Queries\FeedQuery et appliquée à une publication isolée par App\Policies\PostPolicy.
 *
 *  - l'auteur·rice voit toujours sa publication, même masquée par la modération (avec la mention « en attente de relecture ») ;
 *  - une publication non publiée (masquée, supprimée) n'est visible de personne d'autre ;
 *  - un blocage, dans un sens ou dans l'autre, retire la publication (FR-SOC-04 : bloquer en deux gestes) ; pour un
 *    contenu anonyme, seul compte le blocage posé par l'auteur·rice (`blocked` fourni ainsi par l'appelant, Q72) : celui
 *    que pose la personne qui lit ferait de « bloquer » un test qui révèle qui a écrit (RG-02) ;
 *  - `public` (« Communauté ») : tout compte du réseau ; `followers` : les abonné·es de l'auteur·rice ; `me` : personne ;
 *  - anonyme + `followers` (refusé à la saisie, Q72) : traité comme `me`, pour la même raison.
 */
final class PostAudience
{
    private function __construct() {}

    public static function allows(
        PostVisibility $visibility,
        bool $isPublished,
        bool $isAuthor,
        bool $followsAuthor = false,
        bool $blocked = false,
        bool $anonymous = false,
    ): bool {
        if ($isAuthor) {
            return true;
        }

        if (! $isPublished || $blocked) {
            return false;
        }

        return match ($visibility) {
            PostVisibility::Public => true,
            PostVisibility::Followers => $followsAuthor && ! $anonymous,
            PostVisibility::Me => false,
        };
    }
}
