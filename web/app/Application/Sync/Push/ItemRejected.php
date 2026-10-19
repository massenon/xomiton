<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Domain\Shared\Exceptions\DomainException;
use RuntimeException;

/**
 * Refus d'un élément de `sync/push` (P9-06) : le lot continue, l'élément reçoit `{result: rejected, status, code,
 * message, errors}`. `status` 4xx : refus définitif (l'appareil retire l'entrée) ; 5xx : nouvel essai dans un prochain
 * lot. `storable` : un reçu `rejected` est gardé (jamais pour un élément anonyme, un uuid réutilisé ou un type
 * inconnu : rien ne doit relier ces envois à l'appareil).
 */
final class ItemRejected extends RuntimeException
{
    /**
     * @param  array<string, list<string>>  $errors
     */
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $message,
        public readonly array $errors = [],
        public readonly bool $storable = true,
    ) {
        parent::__construct($message);
    }

    /**
     * @param  array<string, list<string>>  $errors
     */
    public static function invalid(array $errors): self
    {
        return new self(422, 'validation_failed', (string) __('errors.api.validation'), $errors);
    }

    public static function anonymous(): self
    {
        return new self(422, 'anonymous_not_allowed', (string) __('sync.items.anonymous'), storable: false);
    }

    public static function unknownType(): self
    {
        return new self(422, 'unsupported_type', (string) __('sync.items.unknown_type'), storable: false);
    }

    public static function uuidReused(): self
    {
        return new self(422, 'uuid_reused', (string) __('sync.items.uuid_reused'), storable: false);
    }

    /** Introuvable ou hors de portée : même réponse neutre (jamais l'existence d'un dossier d'une autre personne). */
    public static function notFound(): self
    {
        return new self(404, 'not_found', (string) __('sync.items.not_found'));
    }

    public static function forbidden(): self
    {
        return new self(403, 'forbidden', (string) __('sync.items.forbidden'));
    }

    public static function fromDomain(DomainException $exception): self
    {
        return new self($exception->status(), $exception->errorCode(), $exception->userMessage(), $exception->errors());
    }
}
