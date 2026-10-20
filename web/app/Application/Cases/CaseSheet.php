<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\User;

/**
 * Fiche de référence d'un dossier en lecture, en cinq sections (P9-08 ; manuel Module 4, MODULE_SPECIFICATIONS.md :
 * 1. Identification pseudonymisée · 2. Faits · 3. Services reçus · 4. Orientation · 5. Consentement) pour la pair·e qui
 * a collecté le dossier (`case_forms.view_own`, « Fiches VBG / VDH 👁 (ses dossiers) », ROLE_ET_PERMISSIONS.md §3).
 *
 * La fiche (table `case_forms`, remplie en administration, P11-02) prime ; sans fiche, les sections 1, 2 et 4 sont
 * déduites du dossier de collecte (champs saisis à la collecte) et les sections 3 et 5 restent vides — `filled` dit
 * laquelle des deux sources est servie. **Jamais** servis : l'auteur·e présumé·e des faits (`facts_author`) et le nom du
 * signataire du consentement (`consent_signatory`), colonnes chiffrées sans usage dans l'espace pair. Un dossier
 * anonymisé n'a plus ni récit ni notes. Les textes (libellés de section) sont ceux du client ; ici, seulement des
 * valeurs.
 */
final class CaseSheet
{
    /**
     * @return array{filled: bool, reference: string|null, identification: array<string, mixed>, facts: array<string, mixed>, services: array<string, mixed>, orientation: array<string, mixed>, consent: array<string, mixed>}
     */
    public function __invoke(User $viewer, CaseRecord $case): array
    {
        $form = $this->form($viewer, $case);
        $anonymized = $case->isAnonymized();

        $case->loadMissing(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label']);

        return [
            'filled' => $form !== null,
            'reference' => $form?->reference,
            'identification' => $this->identification($case, $form),
            'facts' => [
                'date' => ($form?->facts_date ?? $case->occurred_on)?->format('Y-m-d'),
                'place' => $form?->facts_place,
                'type' => $form?->facts_type ?? $case->violenceType?->label,
                'description' => $anonymized ? null : ($form?->facts_description ?? $case->notes),
            ],
            'services' => [
                'items' => $form === null ? [] : $form->requestedServiceLabels(),
                'note' => $anonymized ? null : $form?->services_note,
            ],
            'orientation' => [
                'referral' => $form?->referral_label ?? $case->referral,
                'contact' => $form?->referralContact?->name,
                'date' => $form?->referral_date?->format('Y-m-d'),
                'reason' => $form?->referral_reason,
                'urgency' => $form?->referral_urgency,
            ],
            'consent' => [
                'given' => $form?->consent_given,
                'mode' => $form?->consent_mode,
                'sharing' => $form?->consent_sharing,
            ],
        ];
    }

    private function form(User $viewer, CaseRecord $case): ?CaseForm
    {
        if (! $viewer->can('case_forms.view_own') && ! $viewer->can('case_forms.manage')) {
            return null;
        }

        return $case->forms()->with('referralContact:id,name')->orderByDesc('id')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function identification(CaseRecord $case, ?CaseForm $form): array
    {
        $identification = $form?->identification ?? [];

        return [
            'pseudo' => is_string($identification['pseudo'] ?? null) ? $identification['pseudo'] : null,
            'sex' => $case->sex?->label(),
            'age_range' => $case->age_range?->label(),
            'population' => $case->population?->label,
            'zone' => $case->zone?->name,
            'commune' => $case->commune?->name,
        ];
    }
}
