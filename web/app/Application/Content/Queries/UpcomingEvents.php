<?php

declare(strict_types=1);

namespace App\Application\Content\Queries;

use App\Models\Event;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * « Prochains événements » de la colonne de `/actualites` (prototype blog.php l.43-52) : événements publiés à venir ou
 * en cours, les plus proches d'abord. Réservé aux membres connecté·es tant que les événements publics ne sont pas
 * tranchés (Q35 : drapeau `events.is_public` absent ; le prototype renvoyait les visiteur·ses vers la connexion).
 * Confidentialité : seulement les événements sans groupe ou d'un groupe public actif, et sans page ou d'une page
 * active vérifiée par l'administration ; jamais `place` (adresse possible, réservée aux participant·es confirmé·es,
 * P1-04 / P8-04) : titre, date et « En ligne » seulement. Lecture seule (ADR-29).
 */
final class UpcomingEvents
{
    /** @return Collection<int, Event> */
    public function __invoke(int $limit = 3): Collection
    {
        return Event::query()->published()->upcoming()
            ->where(function (Builder $q): void {
                $q->whereNull('group_id')->orWhereHas('group', fn (Builder $group) => $group->public()->active());
            })
            ->where(function (Builder $q): void {
                $q->whereNull('page_id')->orWhereHas('page', fn (Builder $page) => $page->active()->verified());
            })
            ->limit(max(0, $limit))
            ->get(['id', 'ulid', 'title', 'starts_at', 'ends_at', 'is_online']);
    }
}
