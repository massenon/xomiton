<?php

declare(strict_types=1);

use App\Application\Cases\ValidationCatalog;
use App\Application\Validation\DataQualityReport;
use App\Application\Validation\ManageValidationRules;
use App\Application\Validation\RuleSandbox;
use App\Domain\Cases\ValidationRuleKey;
use App\Enums\CaseStatus;
use App\Livewire\Admin\Validation\Index as ValidationScreen;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\User;
use App\Models\ValidationRule;
use App\Rules\EmailAddress;
use App\Rules\PhoneBj;
use App\Rules\PositiveInteger;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Support\CaseFixtures;

/*
 * P11-05 : Règles de validation /admin/validation (Livewire) — accès `validation_rules.manage`, interrupteurs du catalogue
 * (`required` et `password_strong` verrouillées, demande forgée ignorée), plausibilité et doublons réglés puis lus par
 * ValidationCatalog, bac à sable avec le code des formulaires, rapport de qualité sans lecture du récit, et branchement
 * des règles `email`, `phone_bj`, `positive_int`, `password_strong` sur le catalogue (reste de P9-01).
 */

beforeEach(function (): void {
    CaseFixtures::seed();
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
});

function validationUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('sert le catalogue à l’admin et au superadmin, le refuse au modérateur, à la pair·e et au membre', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(validationUser($role))->get(route('admin.validation'))
            ->assertOk()->assertSeeLivewire(ValidationScreen::class)->assertSee('Règles de validation')->getContent();
        expect($html)->toMatch('/data-xm-nav="validation"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(validationUser($role))->get(route('admin.validation'))->assertForbidden();
    }

    Livewire::actingAs(validationUser('moderateur'))->test(ValidationScreen::class)->assertForbidden();
});

it('affiche les dix règles du catalogue avec leurs exemples, formulaires et verrous, plus les KPI', function (): void {
    $this->actingAs(validationUser('admin'));

    $component = Livewire::test(ValidationScreen::class)
        ->assertSee(['Champ obligatoire', 'Téléphone béninois', 'Adresse e-mail valide', 'Entier positif', 'Mot de passe fort'])
        ->assertSee(['Règles actives', 'Complétude des dossiers', 'Doublons suspects', 'Score qualité'])
        ->assertSeeHtml('id="xmRule-required"')
        ->assertSeeHtml('id="xmRule-password_strong"');

    expect($component->html())->toContain('12 / 12');
    expect(preg_match_all('/id="xmRule-(?:required|password_strong)"[^>]*disabled/', $component->html()))->toBe(2);
    expect(preg_match_all('/id="xmRule-[a-z_0-9]+" /', $component->html()))->toBe(10);
});

it('active et désactive des règles, journalise et rend l’état au catalogue', function (): void {
    $admin = validationUser('admin');
    $this->actingAs($admin);

    Livewire::test(ValidationScreen::class)
        ->set('active.phone_bj', false)->set('active.min_len_20', false)
        ->call('saveRules')
        ->assertDispatched('toast', type: 'success', message: __('admin_data.validation.toasts.rules_saved', ['active' => 10, 'total' => 10]));

    $catalog = new ValidationCatalog;
    expect($catalog->isActive(ValidationRuleKey::PhoneBj))->toBeFalse()
        ->and($catalog->isActive(ValidationRuleKey::MinLen20))->toBeFalse()
        ->and($catalog->isActive(ValidationRuleKey::Email))->toBeTrue();
    expect(AuditLog::query()->where('action', 'validation.rules_updated')->sole()->detail)->toContain('phone_bj=off', 'min_len_20=off');

    // Les clients mobiles reçoivent l'état à jour.
    expect($catalog->active()->pluck('key')->all())->not->toContain('phone_bj');
});

it('verrouille `required` et `password_strong` : une demande de désactivation est ignorée et signalée', function (): void {
    $this->actingAs(validationUser('admin'));

    expect(ValidationRule::query()->withKey('password_strong')->value('is_locked'))->toBeTrue();

    $component = Livewire::test(ValidationScreen::class)
        ->set('active.required', false)->set('active.password_strong', false)
        ->call('saveRules');

    $warnings = collect($component->effects['dispatches'] ?? [])->filter(fn (array $e): bool => $e['name'] === 'toast' && $e['params']['type'] === 'warning');
    expect($warnings)->toHaveCount(2);
    expect(ValidationRule::query()->withKey('required')->value('is_active'))->toBeTrue()
        ->and(ValidationRule::query()->withKey('password_strong')->value('is_active'))->toBeTrue()
        ->and((new ValidationCatalog)->isActive(ValidationRuleKey::PasswordStrong))->toBeTrue();
    expect(AuditLog::query()->where('action', 'validation.rules_updated')->count())->toBe(0);

    // Même sans l'écran : le cas d'usage ne désactive jamais une règle verrouillée, et laisse les autres.
    $result = app(ManageValidationRules::class)->setActivation(validationUser('admin'), ['password_strong' => false, 'email' => false]);
    expect($result)->toBe(['changed' => ['email'], 'locked' => ['password_strong']]);

    // Une base déjà semée avec `password_strong` déverrouillée est rattrapée par la migration.
    ValidationRule::query()->withKey('password_strong')->update(['is_locked' => false, 'is_active' => false]);
    $migration = require database_path('migrations/2026_11_16_090000_lock_password_strong_validation_rule.php');
    $migration->up();
    expect(ValidationRule::query()->withKey('password_strong')->first())->is_locked->toBeTrue()->is_active->toBeTrue();
});

it('refuse l’écriture sans `validation_rules.manage`', function (): void {
    expect(fn () => app(ManageValidationRules::class)->setActivation(validationUser('moderateur'), ['email' => false]))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
    expect(fn () => app(ManageValidationRules::class)->savePlausibility(validationUser('pair'), 10, 99, 5, true))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
});

it('règle la plausibilité (tranche d’âge, ancienneté) lue ensuite par le catalogue, avec ses bornes de saisie', function (): void {
    $this->actingAs(validationUser('admin'));

    $component = Livewire::test(ValidationScreen::class)
        ->set('plausibility.age_min', 15)->set('plausibility.age_max', 60)->set('plausibility.max_past_years', 3)
        ->call('savePlausibility')->assertHasNoErrors();

    $bounds = (new ValidationCatalog)->plausibility();
    expect([$bounds->ageMin, $bounds->ageMax, $bounds->maxPastYears])->toBe([15, 60, 3]);
    // Les bornes GPS et les formulaires concernés sont conservés.
    expect(ValidationRule::query()->withKey('plausibility')->first()->params)->toHaveKeys(['lat', 'lng', 'forms'])
        ->and(AuditLog::query()->where('action', 'validation.plausibility_updated')->sole()->detail)->toBe('âge=15-60 ; ancienneté=3 ans ; active=oui');

    $component->set('plausibility.age_min', 50)->set('plausibility.age_max', 40)->call('savePlausibility')->assertHasErrors('plausibility.age_max');
    $component->set('plausibility.age_min', -3)->set('plausibility.age_max', 99)->call('savePlausibility')->assertHasErrors('plausibility.age_min');
    $component->set('plausibility.age_min', 10)->set('plausibility.age_max', 200)->call('savePlausibility')->assertHasErrors('plausibility.age_max');
    $component->set('plausibility.age_min', 10)->set('plausibility.age_max', 99)->set('plausibility.max_past_years', 80)->call('savePlausibility')->assertHasErrors('plausibility.max_past_years');
    $component->set('plausibility.max_past_years', 'abc')->call('savePlausibility')->assertHasErrors('plausibility.max_past_years');
    // Aucune tranche ne tient dans [60, 61] : refusé.
    $component->set('plausibility.age_min', 60)->set('plausibility.age_max', 61)->set('plausibility.max_past_years', 5)->call('savePlausibility')->assertHasErrors('plausibility.age_max');
    expect((new ValidationCatalog)->plausibility()->ageMin)->toBe(15);
});

it('règle la détection des doublons : deux champs au moins, champs inconnus ignorés', function (): void {
    $this->actingAs(validationUser('admin'));

    $component = Livewire::test(ValidationScreen::class)
        ->set('duplicates.fields', ['date'])->call('saveDuplicates')->assertHasErrors('duplicates.fields');

    $component->set('duplicates.fields', ['date', 'sexe', 'inconnu'])->set('duplicates.is_active', false)->call('saveDuplicates')->assertHasNoErrors();

    $catalog = new ValidationCatalog;
    expect($catalog->duplicateCriteria()->fields)->toBe(['date', 'sexe'])->and($catalog->isActive(ValidationRuleKey::Duplicates))->toBeFalse();
    expect(AuditLog::query()->where('action', 'validation.duplicates_updated')->sole()->detail)->toBe('champs=date+sexe ; active=non');
});

it('essaie une valeur dans le bac à sable avec le code des formulaires et l’état réel du catalogue', function (): void {
    $this->actingAs(validationUser('admin'));
    $sandbox = app(RuleSandbox::class);

    expect($sandbox->check('phone_bj', '+229 01 97 38 88 47')['status'])->toBe('valid')
        ->and($sandbox->check('phone_bj', '12-AB')['status'])->toBe('invalid')
        ->and($sandbox->check('email', 'awa@')['status'])->toBe('invalid')
        ->and($sandbox->check('email', 'awa@xomiton.bj')['status'])->toBe('valid')
        ->and($sandbox->check('positive_int', '-3')['status'])->toBe('invalid')
        ->and($sandbox->check('positive_int', '12')['status'])->toBe('valid')
        ->and($sandbox->check('date_not_future', '2099-01-01')['status'])->toBe('invalid')
        ->and($sandbox->check('date_not_future', '2026-01-15')['status'])->toBe('valid')
        ->and($sandbox->check('min_len_20', 'Menaces')['status'])->toBe('invalid')
        ->and($sandbox->check('case_code', 'vbg42')['status'])->toBe('invalid')
        ->and($sandbox->check('case_code', 'VBG-0042')['status'])->toBe('valid')
        ->and($sandbox->check('age_range', '18-24')['status'])->toBe('valid')
        ->and($sandbox->check('required', '   ')['status'])->toBe('invalid')
        ->and($sandbox->check('phone_bj', '')['status'])->toBe('empty')
        ->and($sandbox->check('password_strong', 'Xomiton2026')['status'])->toBe('unsupported')
        ->and($sandbox->check('commune_in_zone', 'Cotonou')['status'])->toBe('unsupported');

    CaseFixtures::disable('phone_bj');
    // Le catalogue est lu une fois par instance (pas de cache partagé) : le service suivant relit l'état réel.
    expect(app(RuleSandbox::class)->check('phone_bj', '12-AB')['status'])->toBe('disabled');

    Livewire::test(ValidationScreen::class)
        ->call('tryRule', 'case_code')->assertSet('sandboxRule', 'case_code')->assertSet('sandboxValue', 'vbg42')
        ->assertSee('Le code doit respecter le format VBG-0000 ou VDH-0000.')
        ->call('tryRule', 'password_strong')->assertSet('sandboxRule', 'case_code');
});

it('calcule le rapport de qualité sans lire le récit : complétude, doublons, dates douteuses, score', function (): void {
    $admin = validationUser('admin');
    $this->actingAs($admin);
    $zone = CaseFixtures::zone();
    $base = [
        'zone_id' => $zone->id, 'commune_id' => CaseFixtures::commune($zone)->id,
        'population_id' => App\Models\Population::query()->orderBy('position')->value('id'),
        'age_range' => '18-24', 'sex' => 'femme', 'notes' => 'Récit confidentiel de la voisine du quartier',
    ];

    // Deux dossiers doublons (date, commune, population, tranche d'âge), un dossier sans notes ni orientation, un très ancien.
    CaseRecord::factory()->create($base + ['code' => 'VBG-0001', 'occurred_on' => '2026-10-10', 'referral' => 'Juriste']);
    CaseRecord::factory()->create($base + ['code' => 'VBG-0002', 'occurred_on' => '2026-10-10', 'referral' => 'Juriste']);
    CaseRecord::factory()->create(array_replace($base, ['notes' => null]) + ['code' => 'VBG-0003', 'occurred_on' => '2026-10-11', 'referral' => null]);
    CaseRecord::factory()->create($base + ['code' => 'VBG-0004', 'occurred_on' => '2015-01-01', 'referral' => 'Juriste']);
    CaseRecord::factory()->create($base + ['code' => 'VBG-0005', 'occurred_on' => '2026-10-10', 'status' => CaseStatus::Archived, 'archived_at' => now()]);
    CaseRecord::factory()->create(array_replace($base, ['notes' => null]) + ['code' => 'VBG-0006', 'occurred_on' => '2026-10-12', 'anonymized_at' => now(), 'status' => CaseStatus::Closed]);

    $report = app(DataQualityReport::class)($admin);

    expect($report['cases'])->toBe(4)
        ->and($report['missing']['notes'])->toBe(1)->and($report['missing']['referral'])->toBe(1)->and($report['missing']['zone'])->toBe(0)
        ->and($report['duplicate_groups'])->toBe(1)->and($report['duplicate_cases'])->toBe(2)
        ->and($report['duplicates'][0]['codes'])->toBe(['VBG-0001', 'VBG-0002'])
        ->and($report['duplicates'][0]['values']['commune_id'])->toBe(CaseFixtures::commune($zone)->name)
        ->and($report['future'])->toBe(0)->and($report['old'])->toBe(1)
        ->and($report['completeness'])->toBe(93.8)
        ->and($report['score'])->toBe(76);

    Livewire::test(ValidationScreen::class)
        ->assertSee(['Rapport de qualité des données (4 dossiers)', 'VBG-0001, VBG-0002', 'Champs manquants'])
        ->assertDontSee('Récit confidentiel');

    // Le rapport ne lit que des colonnes en clair : jamais `notes` autrement que par IS NULL.
    expect(json_encode($report))->not->toContain('confidentiel')->not->toContain('voisine');
});

it('branche `phone_bj` sur le catalogue : numéro canonique si la règle est active, texte gardé sinon', function (): void {
    $rules = ['phone' => ['nullable', 'string', new PhoneBj]];

    expect(Validator::make(['phone' => 'poste 14'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['phone' => '01 97 38 88 47'], $rules)->passes())->toBeTrue();

    CaseFixtures::disable('phone_bj');
    expect(Validator::make(['phone' => 'poste 14'], $rules)->passes())->toBeTrue()
        ->and(PhoneBj::format('poste 14'))->toBe('poste 14')
        ->and(PhoneBj::format('01 97 38 88 47'))->toBe('+229 01 97 38 88 47');
});

it('branche `email` sur le catalogue (inscription) et garde la structure par défaut', function (): void {
    $rules = ['email' => ['required', 'string', new EmailAddress]];

    expect(Validator::make(['email' => 'awa@'], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['email' => 'awa@xomiton.bj'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['email' => ['x']], $rules)->fails())->toBeTrue();

    CaseFixtures::disable('email');
    expect(Validator::make(['email' => 'awa@'], $rules)->passes())->toBeTrue();

    foreach ([App\Http\Requests\Auth\RegisterRequest::class, App\Http\Requests\Api\Auth\RegisterRequest::class] as $request) {
        expect(collect((new $request)->rules()['email'])->contains(fn ($rule): bool => $rule instanceof EmailAddress))->toBeTrue();
    }
});

it('branche `positive_int` sur le catalogue (indicateurs personnalisés) : entier de nombre, décimales de pourcentage conservées', function (): void {
    expect(PositiveInteger::isValid('12'))->toBeTrue()->and(PositiveInteger::isValid('0'))->toBeTrue()
        ->and(PositiveInteger::isValid('-3'))->toBeFalse()->and(PositiveInteger::isValid('1.5'))->toBeFalse()->and(PositiveInteger::isValid('abc'))->toBeFalse();

    $rules = ['n' => [new PositiveInteger]];
    expect(Validator::make(['n' => '-3'], $rules)->fails())->toBeTrue();
    CaseFixtures::disable('positive_int');
    expect(Validator::make(['n' => '-3'], $rules)->passes())->toBeTrue();
});

it('garde `password_strong` active à l’inscription même quand on tente de la désactiver', function (): void {
    $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    // Tentative de désactivation directe en base : le verrou la garde active pour le catalogue.
    ValidationRule::query()->withKey('password_strong')->update(['is_active' => false]);
    expect((new ValidationCatalog)->isActive(ValidationRuleKey::PasswordStrong))->toBeTrue();

    $this->postJson('/api/v1/auth/register', [
        'pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'password' => 'faible', 'cgu' => true, 'privacy' => true, 'age_confirmed' => true, 'device_name' => 'Pixel',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('enregistre le numéro tel que saisi à l’inscription quand `phone_bj` est désactivée, canonique sinon', function (): void {
    $this->seed([EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
    $payload = ['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'cgu' => true, 'privacy' => true, 'age_confirmed' => true, 'device_name' => 'Pixel', 'phone' => '01 97 38 88 47'];

    $this->postJson('/api/v1/auth/register', $payload)->assertCreated();
    expect(User::query()->where('email', 'awa@exemple.bj')->value('phone'))->toBe('+229 01 97 38 88 47');

    CaseFixtures::disable('phone_bj');
    $this->postJson('/api/v1/auth/register', ['pseudo' => 'Koffi_S', 'email' => 'koffi@exemple.bj', 'phone' => 'poste 14'] + $payload)->assertCreated();
    expect(User::query()->where('email', 'koffi@exemple.bj')->value('phone'))->toBe('poste 14');
});
