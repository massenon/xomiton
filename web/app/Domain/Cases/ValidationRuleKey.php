<?php

declare(strict_types=1);

namespace App\Domain\Cases;

/**
 * Clés du catalogue des règles de validation (DATABASE_DICTIONARY.md §6 `validation_rules.key`, Administration ›
 * Règles de validation). La table porte l'interrupteur (`is_active`) et les paramètres ; cette énumération fixe
 * les clés que le code sait appliquer. `required` est verrouillée (toujours active, `is_locked`).
 */
enum ValidationRuleKey: string
{
    case Required = 'required';
    case Email = 'email';
    case PhoneBj = 'phone_bj';
    case DateNotFuture = 'date_not_future';
    case AgeRange = 'age_range';
    case MinLen20 = 'min_len_20';
    case PositiveInt = 'positive_int';
    case CommuneInZone = 'commune_in_zone';
    case CaseCode = 'case_code';
    case PasswordStrong = 'password_strong';
    case Plausibility = 'plausibility';
    case Duplicates = 'duplicates';

    /** Règle jamais désactivable (le seeder pose `is_locked`). */
    public function isLocked(): bool
    {
        return $this === self::Required;
    }

    /**
     * Règles que la collecte et le suivi des dossiers appliquent (P9-01) ; les autres servent l'inscription,
     * l'annuaire, les indicateurs et les paramètres.
     *
     * @return list<self>
     */
    public static function forCases(): array
    {
        return [
            self::Required,
            self::DateNotFuture,
            self::AgeRange,
            self::MinLen20,
            self::CommuneInZone,
            self::CaseCode,
            self::Plausibility,
            self::Duplicates,
        ];
    }
}
