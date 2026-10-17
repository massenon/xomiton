<?php

declare(strict_types=1);

namespace App\Domain\Auth;

/**
 * Jauge de solidité du mot de passe (LOGIN §4, Q30) : « Fort » exige 10 caractères, majuscule, minuscule et chiffre ;
 * un symbole donne « Excellent ». La jauge est indicative à l'écran (le bouton reste actif) ; la règle serveur
 * App\Rules\StrongPassword refuse tout ce qui est en dessous de « Fort ». Mêmes règles côté client dans
 * resources/js/public/password-meter.js : toute modification ici doit y être reportée.
 */
enum PasswordStrength: int
{
    case TooShort = 0;
    case Weak = 1;
    case Medium = 2;
    case Strong = 3;
    case Excellent = 4;

    public const MIN_LENGTH = 10;

    /** Nombre de segments de la jauge (4 segments + texte, couleur jamais seule). */
    public const SEGMENTS = 4;

    public static function score(string $password): self
    {
        $checks = self::checks($password);

        if (! $checks['length']) {
            return self::TooShort;
        }

        $kinds = (int) $checks['lowercase'] + (int) $checks['uppercase'] + (int) $checks['digit'];

        return match (true) {
            $kinds === 3 && $checks['symbol'] => self::Excellent,
            $kinds === 3 => self::Strong,
            $kinds === 2 => self::Medium,
            default => self::Weak,
        };
    }

    /**
     * Règles cochées en direct sous le champ (ordre d'affichage).
     *
     * @return array{length: bool, uppercase: bool, lowercase: bool, digit: bool, symbol: bool}
     */
    public static function checks(string $password): array
    {
        return [
            'length' => mb_strlen($password) >= self::MIN_LENGTH,
            'uppercase' => preg_match('/\p{Lu}/u', $password) === 1,
            'lowercase' => preg_match('/\p{Ll}/u', $password) === 1,
            'digit' => preg_match('/\d/', $password) === 1,
            'symbol' => preg_match('/[^\p{L}\p{N}\s]/u', $password) === 1,
        ];
    }

    /** Clé de traduction et identifiant côté client. */
    public function key(): string
    {
        return match ($this) {
            self::TooShort => 'too_short',
            self::Weak => 'weak',
            self::Medium => 'medium',
            self::Strong => 'strong',
            self::Excellent => 'excellent',
        };
    }

    public function label(): string
    {
        return (string) __('auth.password_strength.'.$this->key());
    }

    /** Segments allumés sur les quatre de la jauge. */
    public function segments(): int
    {
        return $this->value;
    }

    /** Variante Bootstrap du segment (le texte porte toujours l'information, D5 : rouge réservé à l'attention). */
    public function tone(): string
    {
        return match ($this) {
            self::TooShort, self::Weak => 'danger',
            self::Medium => 'warning',
            self::Strong, self::Excellent => 'success',
        };
    }

    /** Niveau minimal accepté par le serveur (Q30 a). */
    public function isAcceptable(): bool
    {
        return $this->value >= self::Strong->value;
    }
}
