<?php

declare(strict_types=1);

namespace App\Application\Member\Results;

use App\Application\Content\Results\ThematiqueListing;
use App\Models\Alert;
use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Résultat de MemberDashboard (P6-08, `GET /api/v1/dashboard`, simulateur `dashboard`).
 *
 * @phpstan-type Tile array{key: string, route: string, count: int|null}
 * @phpstan-type PeerKpi array{collected: int, this_month: int, in_progress: int, closed: int}
 * @phpstan-type PeerSection array{kpi: PeerKpi, routed_alerts: array{open: int, new: int, items: Collection<int, Alert>}, tasks: list<array{key: string, count: int}>}
 */
final readonly class Dashboard
{
    /**
     * @param  list<Tile>  $tiles  raccourcis du haut de page, dans l'ordre d'affichage
     * @param  array{notifications: int, messages: int}  $counters  mêmes compteurs que l'en-tête de l'espace membre (MemberShell)
     * @param  ThematiqueListing  $featured  fiches « À la une » (les plus récentes) et nombre de fiches publiées
     * @param  PeerSection|null  $peer  espace pair (permission `peer_space.access`, compte validé) ; null sinon
     */
    public function __construct(
        public User $user,
        public array $tiles,
        public array $counters,
        public int $completion,
        public ThematiqueListing $featured,
        public ?Event $nextEvent,
        public ?array $peer,
    ) {}
}
