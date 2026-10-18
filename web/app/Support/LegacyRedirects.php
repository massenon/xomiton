<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Thematique;

/**
 * Résolution des anciennes adresses du prototype (P0-09). `thematique.php?id=<n>` et `ressource.php?id=<n>` (qui
 * redirigeait vers la première) visent la fiche publiée dont la couverture semée porte cet id (`covers/th-<n>.jpg`,
 * ContentSeeder). Utilisé par LegacyRessourceRedirectController (P4-03) ; LegacyThematiqueRedirectController (P4-02)
 * garde sa copie jusqu'à sa migration.
 */
final class LegacyRedirects
{
    private function __construct() {}

    /** Adresse de la fiche publiée de l'ancien id (entier ≥ 1), sinon null : l'appelant choisit la liste de repli. */
    public static function thematique(mixed $id): ?string
    {
        $id = is_string($id) || is_int($id) ? filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        if ($id === false) {
            return null;
        }

        $thematique = Thematique::query()
            ->published()
            ->where('cover_path', 'like', '%/covers/th-'.$id.'.jpg')
            ->first(['id', 'slug']);

        return $thematique === null ? null : route('thematiques.show', $thematique);
    }
}
