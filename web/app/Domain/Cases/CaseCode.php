<?php

declare(strict_types=1);

namespace App\Domain\Cases;

use App\Enums\CaseKind;
use InvalidArgumentException;
use Stringable;

/**
 * Code de registre d'un dossier (RG-06, règle `case_code` du catalogue) : préfixe selon la nature (`VBG-`, `VDH-`),
 * compteur sur 4 chiffres au moins (`VBG-0042`), un compteur par nature, jamais réutilisé (les dossiers ne se
 * suppriment pas). Pur : l'attribution sérialisée et l'unicité en base relèvent de CaseRecord::nextCode().
 *
 * `isValid()` est strict (format normalisé, celui du catalogue : « vbg42 » est refusé) ; `tryParse()` est tolérant
 * pour une saisie ou une adresse (casse, espaces, tiret manquant : « vbg 42 » → `VBG-0042`), jamais d'exception.
 */
final class CaseCode implements Stringable
{
    /** Largeur minimale du compteur (`VBG-0001`) ; au-delà de 9999 le compteur s'allonge (`VBG-10000`). */
    public const DIGITS = 4;

    /** Longueur maximale (colonne `cases.code string(10)`). */
    public const MAX_LENGTH = 10;

    public const PATTERN = '/^(VBG|VDH)-(\d{4,6})$/';

    private function __construct(
        public readonly CaseKind $kind,
        public readonly int $sequence,
    ) {}

    public static function of(CaseKind $kind, int $sequence): self
    {
        if ($sequence < 1) {
            throw new InvalidArgumentException('Case code sequence must be at least 1.');
        }

        $code = new self($kind, $sequence);

        if (strlen($code->value()) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Case code sequence overflows the code column.');
        }

        return $code;
    }

    /** Premier code d'une nature (`VBG-0001`). */
    public static function first(CaseKind $kind): self
    {
        return self::of($kind, 1);
    }

    /**
     * Code qui suit le dernier attribué pour cette nature (le premier si aucun) ; un dernier code illisible ou d'une
     * autre nature est refusé plutôt que de repartir de 1 (ce serait réutiliser un code).
     */
    public static function after(CaseKind $kind, ?string $last): self
    {
        if ($last === null || $last === '') {
            return self::first($kind);
        }

        // Lecture tolérante : un code importé hors format strict (`VBG-42`) garde son rang.
        $previous = self::tryParse($last);

        if ($previous === null || $previous->kind !== $kind) {
            throw new InvalidArgumentException('Last case code is unreadable or belongs to another kind.');
        }

        return $previous->next();
    }

    /** Format normalisé exact (règle `case_code`) : `VBG-0042`, jamais `vbg42` ni `VBG-42`. */
    public static function isValid(string $code): bool
    {
        return preg_match(self::PATTERN, $code) === 1;
    }

    public static function parse(string $code): self
    {
        if (preg_match(self::PATTERN, $code, $matches) !== 1) {
            throw new InvalidArgumentException('Invalid case code.');
        }

        return self::of(CaseKind::from($matches[1]), (int) $matches[2]);
    }

    /**
     * Saisie tolérante (adresse `pair/cases/{code}`, recherche) : majuscules, espaces retirés, tiret facultatif,
     * zéros complétés (« vbg 42 » → `VBG-0042`). Null si ce n'est pas un code de dossier.
     */
    public static function tryParse(string $input): ?self
    {
        $code = strtoupper(preg_replace('/\s+/u', '', trim($input)) ?? '');

        if (preg_match('/^(VBG|VDH)-?(\d{1,6})$/', $code, $matches) !== 1) {
            return null;
        }

        $sequence = (int) $matches[2];

        if ($sequence < 1) {
            return null;
        }

        $parsed = new self(CaseKind::from($matches[1]), $sequence);

        return strlen($parsed->value()) > self::MAX_LENGTH ? null : $parsed;
    }

    public function next(): self
    {
        return self::of($this->kind, $this->sequence + 1);
    }

    public function value(): string
    {
        return $this->kind->codePrefix().str_pad((string) $this->sequence, self::DIGITS, '0', STR_PAD_LEFT);
    }

    public function equals(self $other): bool
    {
        return $this->kind === $other->kind && $this->sequence === $other->sequence;
    }

    public function __toString(): string
    {
        return $this->value();
    }
}
