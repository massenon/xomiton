<?php

declare(strict_types=1);

use App\Application\Retention\AnonymizeClosedCases;
use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseFollowup;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * `cases:anonymize` (P1-14) : dossiers clos ou archivés depuis plus de 5 ans anonymisés une seule fois,
 * colonnes statistiques conservées.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3));
    config(['xomiton.retention.sensitive_years_after_case' => 5]);
});

function closedCaseWithDetails(array $attributes): CaseRecord
{
    $case = CaseRecord::factory()->create(array_merge(['notes' => 'Récit détaillé de la prise en charge.'], $attributes));

    CaseForm::factory()->shareable()->create([
        'case_id' => $case->id,
        'facts_author' => 'Conjoint',
        'facts_description' => 'Description des faits.',
        'facts_place' => 'Cotonou, quartier X',
        'services_note' => 'Note de services.',
        'consent_signatory' => 'Awa S.',
        'identification' => ['pseudo' => 'Awa', 'sexe' => 'femme', 'age' => '18-24', 'zone' => 'Littoral'],
    ]);
    CaseFollowup::factory()->forCase($case)->create(['comment' => 'Rappeler la personne.']);
    CaseFollowup::factory()->forCase($case)->create(['comment' => null]);

    return $case;
}

it('anonymise les dossiers clos ou archivés depuis plus de 5 ans et préserve les statistiques', function (): void {
    $oldClosed = closedCaseWithDetails(['status' => CaseStatus::Closed, 'closed_at' => now()->subYears(5)->subDay()]);
    $oldArchived = closedCaseWithDetails(['status' => CaseStatus::Archived, 'closed_at' => now()->subYears(7), 'archived_at' => now()->subYears(6)]);
    $archivedRecently = closedCaseWithDetails(['status' => CaseStatus::Archived, 'closed_at' => now()->subYears(6), 'archived_at' => now()->subYears(2)]);
    $recentClosed = closedCaseWithDetails(['status' => CaseStatus::Closed, 'closed_at' => now()->subYears(4)]);
    $openOld = closedCaseWithDetails(['status' => CaseStatus::Open, 'created_at' => now()->subYears(8)]);

    $this->artisan('cases:anonymize')
        ->expectsOutputToContain('2 dossiers anonymisés (clos ou archivés depuis plus de 5 ans)')
        ->assertSuccessful();

    $case = $oldClosed->fresh();
    $form = $case->forms()->firstOrFail();
    $comments = $case->followups()->orderBy('id')->get()->pluck('comment')->all();

    expect($case->anonymized_at)->not->toBeNull()
        ->and($case->notes)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and(DB::table('cases')->where('id', $case->id)->value('notes'))->not->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($case->status)->toBe(CaseStatus::Closed)
        ->and($case->kind)->toBe($oldClosed->kind)
        ->and($case->zone_id)->toBe($oldClosed->zone_id)
        ->and($case->sex)->toBe($oldClosed->sex)
        ->and($case->age_range)->toBe($oldClosed->age_range)
        ->and($case->violence_type_id)->toBe($oldClosed->violence_type_id)
        ->and($case->occurred_on->toDateString())->toBe($oldClosed->occurred_on->toDateString())
        ->and($case->referral)->toBe($oldClosed->referral)
        ->and($form->facts_author)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->facts_description)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->facts_place)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->services_note)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->consent_signatory)->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->identification['pseudo'])->toBe(AnonymizeClosedCases::PLACEHOLDER)
        ->and($form->identification['zone'])->toBe('Littoral')
        ->and($form->services)->toBe(CaseForm::query()->whereKey($form->id)->firstOrFail()->services)
        ->and($form->consent_given)->toBeTrue()
        ->and($form->consent_sharing)->toBeTrue()
        ->and($comments)->toBe([AnonymizeClosedCases::PLACEHOLDER, null])
        ->and($oldArchived->fresh()->anonymized_at)->not->toBeNull()
        ->and($oldArchived->fresh()->status)->toBe(CaseStatus::Archived);

    foreach ([$archivedRecently, $recentClosed, $openOld] as $kept) {
        $kept = $kept->fresh();

        expect($kept->anonymized_at)->toBeNull()
            ->and($kept->notes)->toBe('Récit détaillé de la prise en charge.')
            ->and($kept->forms()->firstOrFail()->facts_description)->toBe('Description des faits.')
            ->and($kept->followups()->whereNotNull('comment')->firstOrFail()->comment)->toBe('Rappeler la personne.');
    }

    expect(CaseRecord::query()->count())->toBe(5)
        ->and(AuditLog::query()->ofAction('case.anonymize')->count())->toBe(2)
        ->and(AuditLog::query()->ofAction('case.anonymize')->forSubject($oldClosed)->firstOrFail()->detail)->toContain($oldClosed->code);
});

it('n’anonymise jamais deux fois : anonymized_at est posé une seule fois', function (): void {
    $case = closedCaseWithDetails(['status' => CaseStatus::Closed, 'closed_at' => now()->subYears(6)]);

    $this->artisan('cases:anonymize')->expectsOutputToContain('1 dossier anonymisé')->assertSuccessful();

    $firstRun = $case->fresh()->anonymized_at;
    $updatedAt = $case->fresh()->updated_at;

    Carbon::setTestNow(now()->addYear());

    $this->artisan('cases:anonymize')->expectsOutputToContain('0 dossier anonymisé')->assertSuccessful();

    expect($case->fresh()->anonymized_at->equalTo($firstRun))->toBeTrue()
        ->and($case->fresh()->updated_at->equalTo($updatedAt))->toBeTrue()
        ->and(CaseRecord::query()->whereNotNull('anonymized_at')->count())->toBe(1)
        ->and(AuditLog::query()->ofAction('case.anonymize')->count())->toBe(1);
});

it('compte sans modifier en simulation', function (): void {
    closedCaseWithDetails(['status' => CaseStatus::Closed, 'closed_at' => now()->subYears(6)]);

    $this->artisan('cases:anonymize', ['--dry-run' => true])
        ->expectsOutputToContain('Simulation : 1 dossier à anonymiser')
        ->assertSuccessful();

    expect(CaseRecord::query()->whereNotNull('anonymized_at')->count())->toBe(0);
});
