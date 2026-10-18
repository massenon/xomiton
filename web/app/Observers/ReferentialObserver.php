<?php

declare(strict_types=1);

namespace App\Observers;

use App\Application\Shared\Referentials;
use Illuminate\Database\Eloquent\Model;

/**
 * Invalidation du cache des référentiels (P3-06) : toute écriture sur Zone, Commune, ViolenceType, Need, Population ou
 * Category (création, modification, suppression, restauration) incrémente la version du cache. Enregistré pour chaque
 * modèle par AppServiceProvider::REFERENTIAL_MODELS. Les seeders qui coupent les événements de modèle appellent
 * eux-mêmes `Referentials::invalidate()`.
 */
final class ReferentialObserver
{
    public function __construct(private readonly Referentials $referentials) {}

    public function saved(Model $model): void
    {
        $this->referentials->invalidate();
    }

    public function deleted(Model $model): void
    {
        $this->referentials->invalidate();
    }

    public function restored(Model $model): void
    {
        $this->referentials->invalidate();
    }

    public function forceDeleted(Model $model): void
    {
        $this->referentials->invalidate();
    }
}
