<?php

declare(strict_types=1);

namespace App\Domain\Newsletter;

use InvalidArgumentException;

/**
 * Signature HMAC-SHA256 des liens de suivi d'une campagne (ADR-14, prototype `nl_sign()`) : pixel d'ouverture et
 * redirection de clic. La signature porte sur un usage (`o` ouverture, `c` clic) et ses parties (destinataire, URL
 * cible), jamais sur l'hôte : un lien reste valable quel que soit le domaine par lequel il est ouvert, et une URL
 * cible modifiée invalide le lien (pas de redirection ouverte). 32 caractères hexadécimaux (128 bits).
 */
final readonly class LinkSignature
{
    public const LENGTH = 32;

    public const OPEN = 'o';

    public const CLICK = 'c';

    public function __construct(private string $key)
    {
        if ($key === '') {
            throw new InvalidArgumentException('Clé de signature des liens de newsletter vide.');
        }
    }

    public function sign(string $usage, string|int ...$parts): string
    {
        return substr(hash_hmac('sha256', implode('|', [$usage, ...array_map('strval', $parts)]), $this->key), 0, self::LENGTH);
    }

    /** Comparaison en temps constant ; une signature de longueur ou d'alphabet inattendus est refusée. */
    public function verify(string $signature, string $usage, string|int ...$parts): bool
    {
        if (strlen($signature) !== self::LENGTH || ! ctype_xdigit($signature)) {
            return false;
        }

        return hash_equals($this->sign($usage, ...$parts), strtolower($signature));
    }
}
