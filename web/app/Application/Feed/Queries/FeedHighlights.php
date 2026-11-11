<?php

declare(strict_types=1);

namespace App\Application\Feed\Queries;

use App\Application\Content\Queries\UpcomingEvents;
use App\Models\Event;
use App\Models\Thematique;
use App\Support\LocaleFormatter;

/**
 * Encarts de la page `/fil` (P7-03, prototype fil.php l.56-68 et l.89-100) : « À la une – Thématiques » (six premières
 * fiches publiées, comme l'accueil) et « Événements » de la colonne droite (UpcomingEvents : jamais l'adresse, titre,
 * date et « En ligne » seulement ; réservé aux membres connecté·es, Q35). Lectures Eloquent directes (ADR-29), rendues
 * en tableaux prêts pour les props Inertia (XmRightColumn `events`).
 */
final class FeedHighlights
{
    /** Fiches « À la une » (prototype : array_slice(x_thematiques(), 0, 6)). */
    public const THEMATIQUES_LIMIT = 6;

    /** Événements de la colonne droite (prototype : array_slice(store_all('events'), 0, 3)). */
    public const EVENTS_LIMIT = 3;

    public function __construct(private readonly UpcomingEvents $upcoming) {}

    /**
     * @return list<array{slug: string, title: string, href: string, cover_url: string, category: string|null}>
     */
    public function thematiques(): array
    {
        return Thematique::query()
            ->published()
            ->with('category:id,label')
            ->oldest('id')
            ->limit(self::THEMATIQUES_LIMIT)
            ->get(['id', 'slug', 'title', 'cover_path', 'image_path', 'category_id'])
            ->map(static fn (Thematique $thematique): array => [
                'slug' => (string) $thematique->slug,
                'title' => (string) $thematique->title,
                'href' => route('thematiques.show', ['slug' => $thematique->slug]),
                'cover_url' => $thematique->cover_url,
                'category' => $thematique->category?->label,
            ])
            ->values()->all();
    }

    /**
     * @return list<array{id: string, title: string, href: string, image: null, meta: string}>
     */
    public function events(): array
    {
        $fmt = LocaleFormatter::make();
        $separator = ' '.__('common.separator').' ';

        return ($this->upcoming)(self::EVENTS_LIMIT)
            ->map(static fn (Event $event): array => [
                'id' => (string) $event->ulid,
                'title' => (string) $event->title,
                'href' => route('evenements.show', ['event' => $event->ulid]),
                'image' => null,
                'meta' => implode($separator, array_filter([
                    $event->starts_at ? $fmt->dayMonthShort($event->starts_at) : null,
                    $event->is_online ? (string) __('feed.sidebar.event_online') : null,
                ])),
            ])
            ->values()->all();
    }
}
