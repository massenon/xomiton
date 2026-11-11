<?php

declare(strict_types=1);

namespace App\Application\Cases;

use App\Application\Cases\Data\CaseFormInput;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Enums\CaseStatus;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Enregistrement d'une fiche de référence VBG / VDH liée à un dossier existant (P11-02 ; prototype admin/fiches.php
 * l.19-34) : référence `FR-AAAA-nnnn` par compteur annuel (CaseForm::nextReference, dans la transaction ; nouvel essai
 * si deux saisies simultanées obtiennent la même), identification pseudonymisée reprise du dossier (jamais d'identité),
 * libellé d'orientation tiré de la fiche d'annuaire choisie. Autorisation : `CaseFormPolicy::create`
 * (`case_forms.manage`). Dossier archivé ou anonymisé : `CaseLocked`. Journal d'audit « case_forms.created » (référence
 * et code de dossier, jamais le récit).
 */
final class SaveCaseForm
{
    private const REFERENCE_ATTEMPTS = 3;

    public function __invoke(User $by, CaseRecord $case, CaseFormInput $input): CaseForm
    {
        Gate::forUser($by)->authorize('create', CaseForm::class);

        if ($case->isAnonymized() || $case->status === CaseStatus::Archived) {
            throw new CaseLocked;
        }

        $case->loadMissing(['zone:id,name', 'commune:id,name', 'population:id,label', 'violenceType:id,label']);
        $contact = $input->referralContactId === null ? null : DirectoryContact::query()->active()->find($input->referralContactId);

        $form = null;

        for ($attempt = 1; $form === null; $attempt++) {
            try {
                $form = DB::transaction(fn (): CaseForm => CaseForm::query()->create([
                    'case_id' => $case->getKey(),
                    'reference' => CaseForm::nextReference(),
                    'created_by' => $by->getKey(),
                    'identification' => [
                        'pseudo' => $input->pseudo,
                        'sexe' => $case->sex?->value,
                        'age' => $case->age_range?->value,
                        'population' => $case->population?->label,
                        'zone' => $case->zone?->name,
                        'commune' => $case->commune?->name,
                    ],
                    'facts_date' => $input->factsDate,
                    'facts_place' => $input->factsPlace,
                    'facts_type' => $case->violenceType === null ? null : Str::limit((string) $case->violenceType->label, 40, ''),
                    'facts_author' => $input->factsAuthor,
                    'facts_description' => $input->factsDescription,
                    'services' => CaseFormRules::services($input->services),
                    'services_note' => $input->servicesNote,
                    'referral_contact_id' => $contact?->getKey(),
                    'referral_label' => $contact === null ? null : Str::limit($contact->name.' — '.$contact->role->label(), 120, ''),
                    'referral_date' => $input->referralDate,
                    'referral_reason' => $input->referralReason,
                    'referral_urgency' => $input->referralUrgency,
                    'consent_given' => $input->consentGiven,
                    'consent_mode' => $input->consentMode,
                    'consent_sharing' => $input->consentSharing,
                    'consent_signatory' => $input->consentSignatory,
                ]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= self::REFERENCE_ATTEMPTS) {
                    throw $e;
                }
            }
        }

        Audit::log('case_forms.created', $form, sprintf('fiche=%s ; dossier=%s', $form->reference, $case->code), $by);

        return $form;
    }
}
