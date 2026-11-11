<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\PublicContentCache;
use Illuminate\Database\Eloquent\Model;

/**
 * Vide le cache de données des pages publiques (App\Support\PublicContentCache) quand un contenu qui y figure change :
 * thématiques (publication, dépublication, titre, couverture), FAQ d'accueil, catégories (libellé, icône, couleur).
 * Enregistré par AppServiceProvider::registerPublicContentObservers().
 */
final class PublicContentObserver
{
    public function saved(Model $model): void
    {
        PublicContentCache::invalidate();
    }

    public function deleted(Model $model): void
    {
        PublicContentCache::invalidate();
    }

    public function restored(Model $model): void
    {
        PublicContentCache::invalidate();
    }

    public function forceDeleted(Model $model): void
    {
        PublicContentCache::invalidate();
    }
}
