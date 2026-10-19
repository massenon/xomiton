<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Application\Seo\StructuredData;
use App\Models\Event;
use App\Support\MediaUrl;
use Illuminate\Support\Str;

/**
 * Nœud JSON-LD `Event` d'un événement public (P8-04, Q35 a ; FRONT_END_SEO_PERFECT_AUDIT.md §2 « Événement public »),
 * ajouté au `@graph` de `<x-seo>`. Lieu : la ville seulement (`Place` + `addressLocality`), jamais l'adresse exacte ;
 * en ligne : `VirtualLocation` pointant vers la fiche publique, jamais le lien de connexion. Organisateur : l'organisation
 * (`#org`), comme le prototype title-meta.php.
 */
final class EventStructuredData
{
    private function __construct() {}

    /**
     * @return array<string, mixed>
     */
    public static function for(Event $event, string $canonical, string $description): array
    {
        $root = StructuredData::root();
        $place = $event->place !== null && trim($event->place) !== '' && mb_strtolower(trim($event->place)) !== 'en ligne' ? trim($event->place) : null;
        $locations = array_values(array_filter([
            $place !== null ? [
                '@type' => 'Place',
                'name' => $place,
                'address' => ['@type' => 'PostalAddress', 'addressLocality' => $place, 'addressCountry' => 'BJ'],
            ] : null,
            $event->is_online ? ['@type' => 'VirtualLocation', 'url' => $canonical] : null,
        ]));

        $mode = match (true) {
            $place !== null && $event->is_online => 'MixedEventAttendanceMode',
            (bool) $event->is_online => 'OnlineEventAttendanceMode',
            default => 'OfflineEventAttendanceMode',
        };

        return array_filter([
            '@type' => 'Event',
            '@id' => $canonical.'#event',
            'name' => Str::limit((string) $event->title, 110, '…'),
            'description' => $description,
            'startDate' => $event->starts_at->toIso8601String(),
            'endDate' => $event->ends_at?->toIso8601String(),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/'.$mode,
            'location' => $locations === [] ? null : (count($locations) === 1 ? $locations[0] : $locations),
            'image' => [MediaUrl::resolve($event->image_path, Event::DEFAULT_COVER)],
            'organizer' => ['@id' => $root.'#org'],
            'isAccessibleForFree' => true,
            'inLanguage' => __('seo.in_language'),
            'mainEntityOfPage' => ['@id' => $canonical.'#page'],
        ], static fn (mixed $value): bool => $value !== null);
    }
}
