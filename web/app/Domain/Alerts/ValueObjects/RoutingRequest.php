<?php

declare(strict_types=1);

namespace App\Domain\Alerts\ValueObjects;

/**
 * Ce que le routage RG-04 lit d'une alerte : son département (null pour un SOS localisé par GPS seul) et les slugs de
 * besoins **dans l'ordre fourni** (prototype `route_alert()`, api.php l.16).
 */
final readonly class RoutingRequest
{
    /** @var list<string> */
    public array $needs;

    /**
     * @param  iterable<mixed>  $needs
     */
    public function __construct(public ?int $zoneId, iterable $needs = [])
    {
        $slugs = [];

        foreach ($needs as $need) {
            if (is_string($need) && trim($need) !== '') {
                $slugs[] = trim($need);
            }
        }

        $this->needs = $slugs;
    }
}
