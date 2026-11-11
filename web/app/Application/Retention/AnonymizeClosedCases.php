<?php

declare(strict_types=1);

namespace App\Application\Retention;

use App\Models\CaseFollowup;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Anonymisation des dossiers clos ou archivés depuis plus de `xomiton.retention.sensitive_years_after_case`
 * ans (DATABASE_ENGINEERING.md §6 : « durée du dossier + 5 ans, puis anonymisation »). Irréversible et unique :
 * `cases.anonymized_at` est posé et sert de verrou.
 *
 * Remplacé par « [anonymisé] » : les notes du dossier, le récit, l'auteur·e des faits, le lieu, la note de
 * services et le·la signataire du consentement de chaque fiche, le pseudo de l'identification, le commentaire
 * de chaque étape de suivi. Conservé : toutes les colonnes statistiques (nature, zone, commune, population,
 * sexe, tranche d'âge, type de violence, statut, dates, services demandés, orientation) — IND-01 à IND-16 inchangés.
 * Chaque anonymisation laisse une entrée `case.anonymize` dans le journal d'audit (acteur « système »).
 */
final class AnonymizeClosedCases
{
    public const PLACEHOLDER = '[anonymisé]';

    private const CHUNK_SIZE = 100;

    public function years(): int
    {
        return (int) config('xomiton.retention.sensitive_years_after_case', 5);
    }

    /**
     * @return Builder<CaseRecord>
     */
    public function pending(): Builder
    {
        return CaseRecord::query()->dueForAnonymization($this->years());
    }

    /**
     * @return int Nombre de dossiers anonymisés.
     */
    public function run(): int
    {
        $count = 0;

        $this->pending()->chunkById(self::CHUNK_SIZE, function (Collection $cases) use (&$count): void {
            foreach ($cases as $case) {
                $this->anonymize($case);
                $count++;
            }
        });

        Log::info('Rétention : dossiers anonymisés.', ['count' => $count, 'years' => $this->years()]);

        return $count;
    }

    public function anonymize(CaseRecord $case): void
    {
        if ($case->isAnonymized()) {
            return;
        }

        DB::transaction(function () use ($case): void {
            CaseForm::query()->forCase($case)->get()->each(function (CaseForm $form): void {
                $identification = $form->identification ?? [];

                if (array_key_exists('pseudo', $identification)) {
                    $identification['pseudo'] = self::PLACEHOLDER;
                }

                $form->forceFill([
                    'identification' => $identification,
                    'facts_place' => $form->facts_place === null ? null : self::PLACEHOLDER,
                    'facts_author' => $form->facts_author === null ? null : self::PLACEHOLDER,
                    'facts_description' => self::PLACEHOLDER,
                    'services_note' => $form->services_note === null ? null : self::PLACEHOLDER,
                    'consent_signatory' => $form->consent_signatory === null ? null : self::PLACEHOLDER,
                ])->saveQuietly();
            });

            CaseFollowup::query()->forCase($case)->whereNotNull('comment')->get()->each(function (CaseFollowup $followup): void {
                $followup->forceFill(['comment' => self::PLACEHOLDER])->saveQuietly();
            });

            $case->forceFill([
                'notes' => $case->notes === null ? null : self::PLACEHOLDER,
                'anonymized_at' => now(),
            ])->saveQuietly();

            Audit::log('case.anonymize', $case, sprintf(
                'Dossier %s anonymisé : clos ou archivé depuis plus de %d ans.',
                $case->code,
                $this->years(),
            ));
        });
    }
}
