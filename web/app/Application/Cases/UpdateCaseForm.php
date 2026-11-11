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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Modification d'une fiche de référence VBG / VDH (P11-02, à côté de SaveCaseForm, mêmes règles : CaseFormRules). Le
 * dossier, la référence, l'auteur·e de la fiche et le type de violence ne changent jamais ; le pseudonyme (seule partie
 * modifiable de l'identification), les faits, les services, l'orientation et le consentement se modifient.
 *
 * Les colonnes chiffrées ne sont jamais pré-remplies dans l'écran : « vide = inchangé ». Description des faits : vide =
 * inchangée (elle est obligatoire, elle ne s'efface pas). Auteur·e des faits, précisions sur les services et signataire :
 * vides = inchangés, sauf si leur nom figure dans `$clear` (case « effacer »). Autorisation : `CaseFormPolicy::update`
 * (`case_forms.manage`). Dossier archivé ou anonymisé : `CaseLocked` (relu sous verrou). Journal d'audit
 * « case_forms.updated » : référence, code de dossier et **noms** des champs modifiés, jamais leur contenu.
 */
final class UpdateCaseForm
{
    /** Champs facultatifs chiffrés qu'une modification peut effacer. */
    public const CLEARABLE = ['facts_author', 'services_note', 'consent_signatory'];

    /**
     * @param  list<string>  $clear  champs de CLEARABLE à effacer (les autres noms sont ignorés)
     */
    public function __invoke(User $by, CaseForm $form, CaseFormInput $input, array $clear = []): CaseForm
    {
        Gate::forUser($by)->authorize('update', $form);

        /** @var array{0: CaseForm, 1: CaseRecord, 2: list<string>} $outcome */
        $outcome = DB::transaction(function () use ($form, $input, $clear): array {
            $fresh = CaseForm::query()->whereKey($form->getKey())->lockForUpdate()->firstOrFail();
            $case = CaseRecord::query()->whereKey($fresh->case_id)->lockForUpdate()->firstOrFail();

            if ($case->isAnonymized() || $case->status === CaseStatus::Archived) {
                throw new CaseLocked;
            }

            $identification = is_array($fresh->identification) ? $fresh->identification : [];
            $identification['pseudo'] = $input->pseudo;
            $changes = [
                'identification' => $identification,
                'facts_date' => $input->factsDate,
                'facts_place' => $input->factsPlace,
                'services' => CaseFormRules::services($input->services),
                'referral_date' => $input->referralDate,
                'referral_reason' => $input->referralReason,
                'referral_urgency' => $input->referralUrgency,
                'consent_given' => $input->consentGiven,
                'consent_mode' => $input->consentMode,
                'consent_sharing' => $input->consentSharing,
            ];

            // Colonnes chiffrées : vide = inchangé ; seul un nom listé dans `$clear` efface.
            $encrypted = ['facts_author' => $input->factsAuthor, 'services_note' => $input->servicesNote, 'consent_signatory' => $input->consentSignatory];

            foreach ($encrypted as $column => $value) {
                if ($value !== null) {
                    $changes[$column] = $value;
                } elseif (in_array($column, self::CLEARABLE, true) && in_array($column, $clear, true)) {
                    $changes[$column] = null;
                }
            }

            if ($input->factsDescription !== '') {
                $changes['facts_description'] = $input->factsDescription;
            }

            $changes += $this->referral($fresh, $input->referralContactId);
            $fresh->fill($changes);
            $dirty = array_keys($fresh->getDirty());
            $fresh->save();

            return [$fresh, $case, $dirty];
        });

        [$saved, $case, $dirty] = $outcome;
        $fields = array_values(array_unique(array_map(
            static fn (string $column): string => $column === 'identification' ? 'pseudo' : $column,
            array_values(array_diff($dirty, ['referral_label'])),
        )));

        if ($fields !== []) {
            Audit::log('case_forms.updated', $saved, sprintf('fiche=%s ; dossier=%s ; champs=%s', $saved->reference, $case->code, implode(',', $fields)), $by);
        }

        return $saved;
    }

    /**
     * Orientation : le contact déjà enregistré se garde tel quel (même désactivé depuis), un autre doit être actif.
     *
     * @return array{referral_contact_id: int|null, referral_label: string|null}
     */
    private function referral(CaseForm $form, ?int $contactId): array
    {
        if ($contactId === null) {
            return ['referral_contact_id' => null, 'referral_label' => null];
        }

        if ($form->referral_contact_id !== null && (int) $form->referral_contact_id === $contactId) {
            return ['referral_contact_id' => $contactId, 'referral_label' => $form->referral_label];
        }

        $contact = DirectoryContact::query()->active()->find($contactId);

        return [
            'referral_contact_id' => $contact?->getKey(),
            'referral_label' => $contact === null ? null : Str::limit($contact->name.' — '.$contact->role->label(), 120, ''),
        ];
    }
}
