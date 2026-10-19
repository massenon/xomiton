<?php

declare(strict_types=1);

namespace App\Application\Cases\Data;

/**
 * Modification partielle d'une étape de suivi, validée par CaseRules::forFollowupUpdate() (champs envoyés seulement).
 */
final readonly class FollowupChanges
{
    public const FIELDS = ['date', 'step', 'outcome', 'next_on', 'comment'];

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(public array $attributes) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        $attributes = array_intersect_key($validated, array_flip(self::FIELDS));

        if (array_key_exists('comment', $attributes)) {
            $comment = trim((string) $attributes['comment']);
            $attributes['comment'] = $comment === '' ? null : $comment;
        }

        return new self($attributes);
    }
}
