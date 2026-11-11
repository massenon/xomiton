<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Enums\AgeRange;
use App\Enums\Sex;
use App\Models\CaseForm;

/**
 * Fiche de référence lue en cinq sections (P11-02 ; manuel Module 4) pour l'écran d'administration et le PDF A4
 * (ADR-23) : 1. Identification pseudonymisée · 2. Faits · 3. Services reçus · 4. Orientation · 5. Consentement. Réservée à
 * l'administration (`case_forms.manage`, CaseFormPolicy::view / print) : contrairement à CaseSheet (espace pair), elle
 * rend l'auteur·e présumé·e des faits et le signataire du consentement. Valeurs brutes ou libellés, jamais de texte
 * d'interface (les titres de section sont dans lang/fr/admin_data.php).
 */
final class CaseFormSheet
{
    /**
     * @return array{reference: string, kind: string, case_code: string, created_at: \Illuminate\Support\Carbon|null, author: string|null, identification: array<string, string|null>, facts: array<string, string|null>, services: array{items: list<string>, note: string|null}, referral: array<string, string|null>, consent: array{given: bool, mode: string, sharing: bool, signatory: string|null}}
     */
    public function __invoke(CaseForm $form): array
    {
        $form->loadMissing(['case:id,code,kind', 'author:id,pseudo', 'referralContact:id,name']);
        $identification = is_array($form->identification) ? $form->identification : [];

        return [
            'reference' => $form->reference,
            'kind' => $form->case?->kind->value ?? '',
            'case_code' => $form->case?->code ?? '',
            'created_at' => $form->created_at,
            'author' => $form->author?->pseudo,
            'identification' => [
                'pseudo' => self::text($identification['pseudo'] ?? null),
                'sex' => self::label(Sex::class, $identification['sexe'] ?? null),
                'age' => self::label(AgeRange::class, $identification['age'] ?? null),
                'population' => self::text($identification['population'] ?? null),
                'zone' => self::text($identification['zone'] ?? null),
                'commune' => self::text($identification['commune'] ?? null),
            ],
            'facts' => [
                'date' => $form->facts_date?->format('Y-m-d'),
                'place' => $form->facts_place,
                'type' => $form->facts_type,
                'author' => $form->facts_author,
                'description' => $form->facts_description,
            ],
            'services' => [
                'items' => $form->requestedServiceLabels(),
                'note' => $form->services_note,
            ],
            'referral' => [
                'label' => $form->referral_label ?? $form->referralContact?->name,
                'date' => $form->referral_date?->format('Y-m-d'),
                'urgency' => $form->referral_urgency,
                'reason' => $form->referral_reason,
            ],
            'consent' => [
                'given' => $form->consent_given,
                'mode' => $form->consent_mode,
                'sharing' => $form->consent_sharing,
                'signatory' => $form->consent_signatory,
            ],
        ];
    }

    /**
     * @param  class-string<Sex|AgeRange>  $enum
     */
    private static function label(string $enum, mixed $value): ?string
    {
        $text = self::text($value);

        if ($text === null) {
            return null;
        }

        $case = $enum::tryFrom($text);

        return $case === null ? $text : $case->label();
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
