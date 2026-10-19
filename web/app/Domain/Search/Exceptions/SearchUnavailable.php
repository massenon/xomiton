<?php

declare(strict_types=1);

namespace App\Domain\Search\Exceptions;

use App\Domain\Shared\Exceptions\DomainException;
use PDOException;
use Throwable;

/**
 * Recherche membre en échec (P8-13) : remplace toute erreur levée pendant la recherche — une QueryException recopie la
 * requête SQL **et ses liaisons**, donc le terme cherché, dans son message et le journal. Celle-ci ne porte ni message
 * d'origine ni exception précédente : le journal ne garde que « recherche indisponible » et, pour l'exploitation, la
 * **classe** de l'erreur d'origine et son **SQLSTATE** (`context()`, joint par Laravel au signalement : page et API),
 * jamais son message ni ses liaisons. Rendu 503 `search_unavailable` par l'API.
 */
final class SearchUnavailable extends DomainException
{
    public function __construct(
        private readonly ?string $cause = null,
        private readonly ?string $sqlState = null,
    ) {
        parent::__construct('search.errors.unavailable');
    }

    /** Remplace `$e` en ne gardant que sa classe et son SQLSTATE. */
    public static function from(Throwable $e): self
    {
        $state = $e instanceof PDOException ? ($e->errorInfo[0] ?? null) : null;

        return new self($e::class, is_string($state) && $state !== '' ? $state : null);
    }

    /**
     * Contexte du journal (lu par le gestionnaire d'exceptions de Laravel) : indice sans le terme cherché.
     *
     * @return array{cause: string|null, sqlstate: string|null}
     */
    public function context(): array
    {
        return ['cause' => $this->cause, 'sqlstate' => $this->sqlState];
    }

    public function errorCode(): string
    {
        return 'search_unavailable';
    }

    public function status(): int
    {
        return 503;
    }
}
