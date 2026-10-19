<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull;

use App\Domain\Sync\Exceptions\PullCursorInvalid;

/**
 * Curseur de `GET sync/pull?since=` (P9-06) : jeton opaque rendu dans `meta.cursor` et renvoyé tel quel au tirage
 * suivant — JSON `{v, c: {collection: [at, id, tombAt, tombId]}}` encodé en base64url. Jamais signé : il ne donne accès
 * qu'aux lignes déjà visibles par le compte (la portée est recalculée à chaque tirage). Un jeton illisible → 422
 * `cursor_invalid` (l'appareil relance un tirage complet).
 */
final class PullCursor
{
    private const VERSION = 1;

    /** Longueur maximale acceptée du jeton (sept collections, quatre entiers chacune). */
    public const MAX_LENGTH = 2048;

    /**
     * @param  array<string, PullPosition>  $positions
     */
    private function __construct(private array $positions) {}

    public static function start(): self
    {
        return new self([]);
    }

    /**
     * @throws PullCursorInvalid
     */
    public static function decode(?string $token): self
    {
        if ($token === null || trim($token) === '') {
            return self::start();
        }

        if (strlen($token) > self::MAX_LENGTH) {
            throw new PullCursorInvalid;
        }

        $json = base64_decode(strtr($token, '-_', '+/'), true);
        $data = is_string($json) ? json_decode($json, true) : null;

        if (! is_array($data) || ($data['v'] ?? null) !== self::VERSION || ! is_array($data['c'] ?? null)) {
            throw new PullCursorInvalid;
        }

        $positions = [];

        foreach ($data['c'] as $collection => $values) {
            if (! is_string($collection) || ! is_array($values) || count($values) !== 4 || ! array_is_list($values)) {
                throw new PullCursorInvalid;
            }

            foreach ($values as $value) {
                if (! is_int($value) || $value < 0) {
                    throw new PullCursorInvalid;
                }
            }

            $positions[$collection] = new PullPosition(...$values);
        }

        return new self($positions);
    }

    public function position(string $collection): PullPosition
    {
        return $this->positions[$collection] ?? new PullPosition;
    }

    public function with(string $collection, PullPosition $position): self
    {
        return new self([$collection => $position] + $this->positions);
    }

    public function encode(): string
    {
        $positions = $this->positions;
        ksort($positions);

        $json = json_encode(['v' => self::VERSION, 'c' => array_map(static fn (PullPosition $p): array => $p->toArray(), $positions)], JSON_THROW_ON_ERROR);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }
}
