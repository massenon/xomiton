<?php

declare(strict_types=1);

namespace App\Domain\Search;

use LogicException;

/**
 * Terme de la recherche membre (P8-13) : texte déjà assaini par la requête HTTP (App\Support\ListingQuery::search). Le
 * terme dit ce que la personne cherche (« violence conjugale », « lesbienne », le pseudo d'une personne…) : il ne doit
 * laisser **aucune trace côté serveur** — ni base, ni journal, ni session, ni file. D'où un objet plutôt qu'une
 * chaîne : `dump()`, `var_dump()` et les traces d'exception n'en montrent que la longueur (`__debugInfo`), et toute
 * sérialisation (cache, file, session) échoue (`__serialize`). La valeur ne sort que par `value()`, pour la requête SQL.
 */
final class SearchTerm
{
    private function __construct(private readonly string $value) {}

    public static function of(string $clean): self
    {
        return new self($clean);
    }

    public static function none(): self
    {
        return new self('');
    }

    public function value(): string
    {
        return $this->value;
    }

    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    public function length(): int
    {
        return mb_strlen($this->value);
    }

    /** Assez long pour lancer la recherche (en deçà, aucune requête : résultats vides). */
    public function isSearchable(int $minLength): bool
    {
        return $this->length() >= max(1, $minLength);
    }

    /**
     * @return array{value: string, length: int}
     */
    public function __debugInfo(): array
    {
        return ['value' => '[masqué]', 'length' => $this->length()];
    }

    /**
     * @return array<string, mixed>
     */
    public function __serialize(): array
    {
        throw new LogicException('Un terme de recherche ne se sérialise pas (aucune trace côté serveur, P8-13).');
    }
}
