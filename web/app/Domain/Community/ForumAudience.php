<?php

declare(strict_types=1);

namespace App\Domain\Community;

/**
 * Qui lit un sujet ou une réponse de forum (P8-05), sans base de données : l'appelant fournit les faits. Même règle que
 * le fil (App\Domain\Feed\PostAudience, Q72) :
 *
 *  - l'auteur·rice voit toujours son contenu, même masqué par la modération ;
 *  - un contenu non publié (masqué, supprimé) ou d'un forum fermé n'est visible de personne d'autre ;
 *  - le blocage posé par l'auteur·rice envers la personne qui lit retire le contenu, anonyme ou non ;
 *  - le blocage posé par la personne qui lit ne retire qu'un contenu **signé** : pour un contenu anonyme, il ferait de
 *    « bloquer » un test qui révèle qui a écrit (RG-02, aucun oracle d'anonymat).
 */
final class ForumAudience
{
    private function __construct() {}

    public static function allows(
        bool $isAuthor,
        bool $isPublished,
        bool $forumActive = true,
        bool $blockedByAuthor = false,
        bool $blockedByViewer = false,
        bool $anonymous = false,
    ): bool {
        if ($isAuthor) {
            return true;
        }

        if (! $isPublished || ! $forumActive || $blockedByAuthor) {
            return false;
        }

        return $anonymous || ! $blockedByViewer;
    }
}
