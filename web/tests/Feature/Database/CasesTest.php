<?php

declare(strict_types=1);

use App\Enums\CaseKind;
use App\Enums\CaseStatus;
use App\Enums\FollowupOutcome;
use App\Models\CaseFollowup;
use App\Models\CaseForm;
use App\Models\CaseRecord;
use App\Models\ValidationRule;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lot 7 « dossiers » : une création par factory et une règle métier par table
 * (DATABASE_DICTIONARY.md §6). Tourne avec RefreshDatabase (tests/Pest.php).
 */

it('crée une règle de validation avec une clé unique et des paramètres json', function (): void {
    $rule = ValidationRule::factory()->plausibility()->create();

    expect($rule->param('age_min'))->toBe(10)
        ->and($rule->param('lat.1'))->toBe(12.5)
        ->and(ValidationRule::isEnabled(ValidationRule::KEY_PLAUSIBILITY))->toBeTrue()
        ->and(ValidationRule::isEnabled('regle_inconnue'))->toBeTrue();

    expect(fn () => ValidationRule::factory()->create(['key' => ValidationRule::KEY_PLAUSIBILITY]))
        ->toThrow(QueryException::class);
});

it('seed les 12 règles du dictionnaire sans doublon, verrouille « required » et conserve les réglages de l’administration', function (): void {
    $this->seed(ValidationRulesSeeder::class);
    ValidationRule::query()->whereIn('key', ['duplicates', ValidationRule::KEY_REQUIRED])->update(['is_active' => false]);

    $this->seed(ValidationRulesSeeder::class);

    $required = ValidationRule::query()->withKey(ValidationRule::KEY_REQUIRED)->firstOrFail();

    expect(ValidationRule::query()->count())->toBe(12)
        ->and(ValidationRule::query()->active()->count())->toBe(11)
        ->and(ValidationRule::query()->locked()->count())->toBe(1)
        ->and($required->is_locked)->toBeTrue()
        ->and($required->is_active)->toBeTrue()
        ->and($required->canBeDisabled())->toBeFalse()
        ->and(ValidationRule::query()->ordered()->value('key'))->toBe(ValidationRule::KEY_REQUIRED)
        ->and(ValidationRule::query()->withKey('plausibility')->first()?->param('lng'))->toBe([0.7, 3.9])
        ->and(ValidationRule::query()->withKey('duplicates')->first()?->param('fields'))->toBe(['date', 'commune', 'population', 'age'])
        ->and(ValidationRule::query()->withKey('duplicates')->first()?->is_active)->toBeFalse();
});

it('considère une règle verrouillée comme active même désactivée en base', function (): void {
    $rule = ValidationRule::factory()->locked()->create(['is_active' => false]);

    expect($rule->isActive())->toBeTrue()
        ->and(ValidationRule::isEnabled(ValidationRule::KEY_REQUIRED))->toBeTrue();
});

it('crée un dossier avec un code VBG-0000 par nature et des compteurs séparés', function (): void {
    $vbg = CaseRecord::factory()->vbg()->create();
    $vdh = CaseRecord::factory()->vdh()->create();
    $second = CaseRecord::factory()->vbg()->create();

    expect($vbg->code)->toBe('VBG-0001')
        ->and($vdh->code)->toBe('VDH-0001')
        ->and($second->code)->toBe('VBG-0002')
        ->and(CaseRecord::nextCode(CaseKind::Vbg))->toBe('VBG-0003')
        ->and(CaseRecord::nextCode(CaseKind::Vdh))->toBe('VDH-0002')
        ->and($vbg->fresh()->kind)->toBe(CaseKind::Vbg)
        ->and($vbg->fresh()->status)->toBe(CaseStatus::Open);

    expect(fn () => CaseRecord::factory()->create(['code' => 'VBG-0001']))->toThrow(QueryException::class);
});

it('chiffre les notes du dossier et filtre les dossiers actifs', function (): void {
    $case = CaseRecord::factory()->create(['notes' => 'Récit confidentiel.']);
    CaseRecord::factory()->closed()->create();
    CaseRecord::factory()->archived()->create();

    $raw = DB::table('cases')->where('id', $case->id)->value('notes');

    expect($raw)->not->toBe('Récit confidentiel.')
        ->and($case->fresh()->notes)->toBe('Récit confidentiel.')
        ->and($case->toArray())->not->toHaveKey('notes')
        ->and(CaseRecord::query()->active()->count())->toBe(1)
        ->and(CaseRecord::query()->withStatus(CaseStatus::Archived)->count())->toBe(1);
});

it('empêche la suppression d’une pair·e ou d’un département qui a des dossiers', function (): void {
    $case = CaseRecord::factory()->create();

    expect(fn () => $case->collector->forceDelete())->toThrow(QueryException::class)
        ->and(fn () => $case->zone->delete())->toThrow(QueryException::class);
});

it('crée une fiche de référence FR-YYYY-0001 chiffrée et rattachée à son dossier', function (): void {
    $form = CaseForm::factory()->shareable()->create(['facts_description' => 'Description des faits.']);
    $year = now()->format('Y');

    expect($form->reference)->toBe("FR-{$year}-0001")
        ->and(CaseForm::nextReference())->toBe("FR-{$year}-0002")
        ->and(DB::table('case_forms')->where('id', $form->id)->value('facts_description'))->not->toBe('Description des faits.')
        ->and($form->fresh()->facts_description)->toBe('Description des faits.')
        ->and($form->case)->toBeInstanceOf(CaseRecord::class)
        ->and($form->case->forms()->count())->toBe(1)
        ->and(count($form->requestedServices()))->toBe(2)
        ->and(CaseForm::SERVICE_KEYS)->toContain('dignite')
        ->and(array_keys($form->services))->toBe(CaseForm::SERVICE_KEYS)
        ->and(CaseForm::query()->shareable()->count())->toBe(1);

    expect(fn () => CaseForm::factory()->create(['reference' => $form->reference]))->toThrow(QueryException::class);
});

it('crée une étape de suivi chiffrée et repère les étapes en retard', function (): void {
    $case = CaseRecord::factory()->create();
    $overdue = CaseFollowup::factory()->forCase($case)->overdue()->create(['comment' => 'Rappeler la personne.']);
    CaseFollowup::factory()->forCase($case)->done()->create();

    expect($overdue->fresh()->outcome)->toBe(FollowupOutcome::Planned)
        ->and($overdue->isPending())->toBeTrue()
        ->and(DB::table('case_followups')->where('id', $overdue->id)->value('comment'))->not->toBe('Rappeler la personne.')
        ->and($overdue->fresh()->comment)->toBe('Rappeler la personne.')
        ->and($case->followups()->count())->toBe(2)
        ->and(CaseFollowup::query()->forCase($case)->pending()->count())->toBe(1)
        ->and(CaseFollowup::query()->overdue()->count())->toBe(1)
        ->and(FollowupOutcome::fromPrototype('En attente'))->toBe(FollowupOutcome::Planned);

    expect(fn () => $case->delete())->toThrow(QueryException::class);
});
