<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * Description d'une requête HTTP de remise (push FCM v1 / VAPID, SMS) qu'un pilote « poolable » confie au runner pour
 * qu'elle parte avec les autres dans un seul `Http::pool` (D7 : appels en parallèle, 3 s au plus par fournisseur).
 * Le pilote décrit la requête et interprète la réponse ; il ne l'envoie pas lui-même.
 */
final readonly class HttpRequestSpec
{
    /**
     * @param  array<string, string>  $headers
     * @param  array<string, mixed>|null  $json
     */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public ?string $body = null,
        public ?array $json = null,
    ) {}
}
