<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use RuntimeException;

/**
 * Exception métier rendue telle quelle par l'API (P5-09, App\Exceptions\ApiExceptionRenderer) :
 * `{ message, code, errors?, ...extra }` avec le statut HTTP de l'exception (422 par défaut).
 * Le message vient de lang/fr ; `errorCode()` est stable et lu par Flutter et Vue (docs/API.md §1).
 * Les exceptions du tunnel d'authentification (App\Domain\Auth\Exceptions\AuthException) gardent leur rendu propre.
 *
 * Homonyme de la classe SPL `\DomainException` (sans lien d'héritage) : toujours importer
 * `use App\Domain\Shared\Exceptions\DomainException;` ; dans un espace de noms sans ce `use`, `catch (DomainException)`
 * vise une classe inexistante du même espace et `catch (\DomainException)` la classe SPL, et aucun des deux n'attrape
 * une exception métier. Renommage (ex. `BusinessException`) écarté : AlertException (lot P5-02) en hérite déjà.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(
        private readonly string $langKey,
        private readonly array $replace = [],
    ) {
        parent::__construct($this->userMessage());
    }

    /** Code stable lu par les clients (`snake_case`). */
    abstract public function errorCode(): string;

    public function userMessage(): string
    {
        return (string) __($this->langKey, $this->replace);
    }

    public function status(): int
    {
        return 422;
    }

    /**
     * Erreurs par champ (`{ champ: [messages] }`), vide par défaut.
     *
     * @return array<string, list<string>>
     */
    public function errors(): array
    {
        return [];
    }

    /**
     * Clés ajoutées au corps de la réponse (`retry_after`…).
     *
     * @return array<string, mixed>
     */
    public function extra(): array
    {
        return [];
    }

    /**
     * En-têtes HTTP de la réponse (`Retry-After`…).
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        return [];
    }
}
