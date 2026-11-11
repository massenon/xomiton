<?php

declare(strict_types=1);

use App\Application\Cases\CaseRules;
use App\Application\Cases\CaseValidator;
use App\Application\Cases\ValidationCatalog;
use App\Domain\Cases\ValidationRuleKey;
use App\Models\Commune;
use App\Models\ValidationRule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Tests\Support\CaseFixtures as C;

/*
 * Catalogue de règles activables (P9-01, table `validation_rules`) appliqué aux dossiers : règle active → contrôle
 * appliqué, désactivée → relâchée, absente → active, `required` toujours active ; RG-07, RG-08 (Q25 a), `min_len_20`,
 * `commune_in_zone`. Mêmes règles pour les Form Requests de `pair/` et pour `sync/push` (CaseValidator).
 */

beforeEach(function (): void {
    C::seed();
});

function caseRulesErrors(array $payload): array
{
    return Validator::make($payload, app(CaseRules::class)->forCreate())->errors()->toArray();
}

it('lit l’interrupteur des règles : absente ou verrouillée = active', function (): void {
    ValidationRule::query()->where('key', 'email')->delete();
    ValidationRule::query()->where('key', 'required')->update(['is_active' => false]);
    C::disable('min_len_20');
    $catalog = new ValidationCatalog;

    expect($catalog->isActive(ValidationRuleKey::Email))->toBeTrue()
        ->and($catalog->isActive('required'))->toBeTrue()
        ->and($catalog->isActive(ValidationRuleKey::MinLen20))->toBeFalse()
        ->and($catalog->isActive('cle_inconnue'))->toBeTrue()
        ->and($catalog->active()->pluck('key'))->not->toContain('min_len_20')->toContain('required');
});

it('reprend les bornes de plausibilité et les champs de doublons du catalogue', function (): void {
    C::params('plausibility', ['age_min' => 15, 'max_past_years' => 2]);
    C::params('duplicates', ['fields' => ['date', 'type']]);
    $catalog = new ValidationCatalog;

    expect($catalog->plausibility()->ageMin)->toBe(15)
        ->and($catalog->plausibility()->maxPastYears)->toBe(2)
        ->and($catalog->duplicateCriteria()->columns())->toBe(['occurred_on', 'violence_type_id']);
});

it('accepte une collecte complète et exige les champs du prototype', function (): void {
    expect(caseRulesErrors(C::payload()))->toBe([]);

    $errors = caseRulesErrors(['client_uuid' => 'pas-un-uuid']);

    expect(array_keys($errors))->toEqualCanonicalizing([
        'client_uuid', 'kind', 'occurred_on', 'zone_id', 'population_id', 'sex', 'age_range', 'violence_type_id',
    ]);
});

it('refuse une date future (RG-07) tant que date_not_future est active', function (): void {
    $future = ['occurred_on' => now('Africa/Porto-Novo')->addDays(2)->format('Y-m-d')];

    expect(caseRulesErrors(C::payload($future)))->toHaveKey('occurred_on');

    C::disable('date_not_future');

    expect(caseRulesErrors(C::payload($future)))->toBe([]);
});

it('refuse une commune d’un autre département (RG-08) tant que commune_in_zone est active', function (): void {
    $other = Commune::query()->where('zone_id', '!=', C::zone()->id)->firstOrFail();

    expect(caseRulesErrors(C::payload(['commune_id' => $other->id])))->toHaveKey('commune_id');

    C::disable('commune_in_zone');

    expect(caseRulesErrors(C::payload(['commune_id' => $other->id])))->toBe([])
        ->and(caseRulesErrors(C::payload(['commune_id' => 999_999])))->toHaveKey('commune_id');
});

it('refuse des notes de moins de 20 caractères mais accepte des notes vides (min_len_20)', function (): void {
    expect(caseRulesErrors(C::payload(['notes' => 'Trop court'])))->toHaveKey('notes')
        ->and(caseRulesErrors(C::payload(['notes' => ''])))->toBe([])
        ->and(caseRulesErrors(C::payload(['notes' => null])))->toBe([]);

    C::disable('min_len_20');

    expect(caseRulesErrors(C::payload(['notes' => 'Trop court'])))->toBe([]);
});

it('exige une tranche d’âge de la liste, dans les bornes réglées (age_range, Q25 a)', function (): void {
    expect(caseRulesErrors(C::payload(['age_range' => '24'])))->toHaveKey('age_range');

    C::params('plausibility', ['age_min' => 15]);

    expect(caseRulesErrors(C::payload(['age_range' => '10-14'])))->toHaveKey('age_range')
        ->and(caseRulesErrors(C::payload(['age_range' => '15-17'])))->toBe([]);

    C::disable('age_range');

    expect(caseRulesErrors(C::payload(['age_range' => '10-14'])))->toBe([])
        ->and(caseRulesErrors(C::payload(['age_range' => '24'])))->toHaveKey('age_range');
});

it('refuse une orientation hors liste et un référentiel inactif', function (): void {
    expect(caseRulesErrors(C::payload(['referral' => 'Orientation sexuelle'])))->toHaveKey('referral');

    App\Models\Population::query()->whereKey(C::payload()['population_id'])->update(['is_active' => false]);

    expect(caseRulesErrors(C::payload()))->toHaveKey('population_id');
});

it('valide une charge utile de synchronisation avec les mêmes règles (CaseValidator)', function (): void {
    $input = app(CaseValidator::class)->newCase(C::payload(['notes' => '   ', 'referral' => '']));

    expect($input->notes)->toBeNull()
        ->and($input->referral)->toBeNull()
        ->and($input->kind->value)->toBe('VBG');

    app(CaseValidator::class)->newCase(C::payload(['occurred_on' => '2999-01-01']));
})->throws(ValidationException::class);

it('applique les règles unitaires du catalogue : case_code strict, date du jour au Bénin, min_len_20', function (): void {
    $check = static fn (array $data, array $rules): array => Validator::make($data, $rules)->errors()->toArray();

    expect($check(['code' => 'VBG-0042'], ['code' => [new App\Rules\CaseCodeFormat]]))->toBe([])
        ->and($check(['code' => 'vbg42'], ['code' => [new App\Rules\CaseCodeFormat]]))->toHaveKey('code')
        ->and($check(['notes' => 'Trop court'], ['notes' => [new App\Rules\MinimumLength]])['notes'][0])
        ->toBe('Le champ notes doit contenir au moins 20 caractères, ou rester vide.');

    // 23 h 30 UTC = 00 h 30 le lendemain à Cotonou : la date locale du jour n'est pas « future ».
    $this->travelTo(Carbon\CarbonImmutable::parse('2026-10-19 23:30:00', 'UTC'));

    expect($check(['d' => '2026-10-20'], ['d' => [new App\Rules\NotFutureDate]]))->toBe([])
        ->and($check(['d' => '2026-10-21'], ['d' => [new App\Rules\NotFutureDate]]))->toHaveKey('d');
});
