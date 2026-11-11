<?php

declare(strict_types=1);

namespace App\Application\Cases\Data;

/**
 * Fiche de référence à enregistrer, validée par CaseFormRules (écran `/admin/fiches`, P11-02) : cinq sections du
 * Module 4 du manuel (identification pseudonymisée · faits · services reçus · orientation · consentement).
 */
final readonly class CaseFormInput
{
    /**
     * @param  array<string, bool>  $services  clé du catalogue (CaseForm::SERVICES) => reçu
     */
    public function __construct(
        public ?string $pseudo,
        public string $factsDate,
        public ?string $factsPlace,
        public ?string $factsAuthor,
        public string $factsDescription,
        public array $services,
        public ?string $servicesNote,
        public ?int $referralContactId,
        public ?string $referralDate,
        public ?string $referralReason,
        public ?string $referralUrgency,
        public bool $consentGiven,
        public string $consentMode,
        public bool $consentSharing,
        public ?string $consentSignatory,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated): self
    {
        return new self(
            pseudo: self::text($validated['pseudo'] ?? null),
            factsDate: (string) $validated['facts_date'],
            factsPlace: self::text($validated['facts_place'] ?? null),
            factsAuthor: self::text($validated['facts_author'] ?? null),
            factsDescription: trim((string) $validated['facts_description']),
            services: array_map(static fn (mixed $value): bool => (bool) $value, (array) ($validated['services'] ?? [])),
            servicesNote: self::text($validated['services_note'] ?? null),
            referralContactId: isset($validated['referral_contact_id']) && $validated['referral_contact_id'] !== '' ? (int) $validated['referral_contact_id'] : null,
            referralDate: self::text($validated['referral_date'] ?? null),
            referralReason: self::text($validated['referral_reason'] ?? null),
            referralUrgency: self::text($validated['referral_urgency'] ?? null),
            consentGiven: (bool) ($validated['consent_given'] ?? false),
            consentMode: (string) $validated['consent_mode'],
            consentSharing: (bool) ($validated['consent_sharing'] ?? false),
            consentSignatory: self::text($validated['consent_signatory'] ?? null),
        );
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }
}
