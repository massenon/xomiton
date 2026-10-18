<?php

declare(strict_types=1);

namespace App\Application\Shared\Idempotency;

use App\Support\KeyedHash;

/**
 * Requête soumise au middleware `idempotent`, réduite à ce qui l'identifie (ADR-32) : la clé du client, l'acteur
 * (`user:<id>` sur une route authentifiée ; sur une route publique, `bearer:<HMAC du jeton>` ou `guest`, jamais le compte :
 * RG-01 / RG-02), la route concrète (méthode + chemin), le gabarit de route stocké et l'empreinte du corps.
 * Ni l'acteur ni le chemin concret (ULID, code de suivi) ne sont stockés : ils n'entrent que dans `scope()`, une HMAC
 * à clé d'application qu'on ne retrouve pas par force brute depuis la base seule.
 */
final readonly class IdempotentRequest
{
    public function __construct(
        public string $key,
        public string $actor,
        public string $method,
        public string $path,
        public string $route,
        public string $requestHash,
    ) {}

    /** Portée de la clé : une même clé sert une fois par acteur et par route concrète. */
    public function scope(): string
    {
        return KeyedHash::make($this->actor.'|'.$this->method.'|'.$this->path);
    }
}
