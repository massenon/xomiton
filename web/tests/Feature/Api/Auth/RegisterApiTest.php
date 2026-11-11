<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\PeerPending;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FeedFixtures as F;
use Tests\Support\RegistrationFixtures as R;

/*
 * POST /api/v1/auth/register (inscription en 3 étapes, décision client du 11/11/2026) : un seul envoi, compte créé en
 * attente de validation et jeton délivré, aucun code OTP ni e-mail ; 422 champ par champ ; 403 inscriptions fermées ; 429.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

function registerPayload(array $overrides = []): array
{
    return R::payload(array_merge(['device_name' => 'Pixel 7 de Awa'], $overrides));
}

it('crée le compte en attente, délivre un jeton immédiatement, sans code ni e-mail (201)', function (): void {
    Notification::fake();
    $admin = F::member('admin');

    $response = $this->postJson('/api/v1/auth/register', registerPayload());

    $response->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.email_verification_pending', false)
        ->assertJsonPath('data.user.pseudo', 'Awa_S')
        ->assertJsonPath('data.user.email', 'awa@exemple.bj')
        ->assertJsonPath('data.user.email_verified', false)
        ->assertJsonPath('data.user.role', 'membre')
        ->assertJsonPath('data.user.status', UserStatus::Pending->value)
        ->assertJsonPath('data.user.pending_validation', true)
        ->assertJsonPath('data.user.profile.member_type', MemberType::Member->value)
        ->assertJsonMissingPath('data.user.name');

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBe('');

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->first_name)->toBe('Awa')
        ->and($user->name)->toBe('Awa Sossou')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->consents()->count())->toBe(4)
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()?->name)->toBe('Pixel 7 de Awa')
        ->and($user->profile?->age)->toBe(24)
        ->and($user->profile?->country_code)->toBe('BJ')
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->count())->toBe(0)
        ->and(AuditLog::query()->ofAction('auth.token_issued')->forSubject($user)->exists())->toBeTrue();

    Mail::assertNothingSent();
    // Les validateurs sont prévenus, API comme web.
    Notification::assertSentTo($admin, PeerPending::class, fn (PeerPending $n): bool => $n->userId === $user->id);

    // Le jeton ouvre tout de suite GET me.
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.pending_validation', true);
});

it('inscrit une allié·e avec son statut, sans identité de genre ni orientation (201)', function (): void {
    $this->postJson('/api/v1/auth/register', R::allyPayload(['ally_role' => 'autre', 'ally_role_other' => 'Formatrice']))
        ->assertCreated()
        ->assertJsonPath('data.user.profile.member_type', MemberType::Ally->value);

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->profile?->ally_role)->toBe('autre')
        ->and($user->profile?->ally_role_other)->toBe('Formatrice')
        ->and($user->consents()->ofKind(ConsentKind::SensitiveData)->exists())->toBeFalse()
        ->and(DB::table('profiles')->where('user_id', $user->id)->value('ally_role'))->not->toContain('autre');
});

it('garde zone et commune au Bénin seulement, et le téléphone selon le pays', function (): void {
    $zone = Zone::factory()->create(['is_active' => true]);
    $commune = Commune::factory()->create(['zone_id' => $zone->id, 'is_active' => true]);

    $this->postJson('/api/v1/auth/register', registerPayload(['zone_id' => $zone->id, 'commune_id' => $commune->id, 'phone' => '97 38 88 47', 'city' => 'Cotonou']))
        ->assertCreated()
        ->assertJsonPath('data.user.profile.zone.id', $zone->id);
    $benin = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($benin->phone)->toBe('+229 01 97 38 88 47')->and($benin->commune_id)->toBe($commune->id);

    $this->postJson('/api/v1/auth/register', registerPayload(['pseudo' => 'Awa_FR', 'email' => 'fr@exemple.fr', 'country_code' => 'FR', 'zone_id' => $zone->id, 'phone' => '+33 6 12 34 56 78']))
        ->assertCreated();
    $france = User::query()->where('email', 'fr@exemple.fr')->firstOrFail();
    expect($france->zone_id)->toBeNull()->and($france->phone)->toBe('+33612345678');
});

it('renvoie 422 champ par champ : pseudonyme, e-mail déjà pris (casse ignorée), mot de passe faible, cases non cochées', function (): void {
    User::factory()->create(['email' => 'prise@exemple.bj', 'pseudo' => 'Deja_La']);

    $this->postJson('/api/v1/auth/register', registerPayload([
        'pseudo' => 'ab',
        'email' => 'PRISE@exemple.bj',
        'password' => 'faible',
        'password_confirmation' => 'faible',
        'cgu' => false,
        'privacy' => false,
    ]))
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['pseudo', 'email', 'password', 'cgu', 'privacy'])
        ->assertJsonPath('errors.email.0', __('auth.api.email_taken'))
        ->assertJsonPath('errors.cgu.0', __('validation.custom.cgu.accepted'));

    $this->postJson('/api/v1/auth/register', registerPayload(['pseudo' => 'deja_la', 'password' => 'Sansmajuscule2026', 'password_confirmation' => 'Sansmajuscule2026']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pseudo' => __('validation.pseudo.taken')]);

    $this->postJson('/api/v1/auth/register', registerPayload(['password_confirmation' => 'Autre2026!x']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => __('validation.custom.password.confirmed')]);

    expect(User::query()->count())->toBe(1);
});

it('applique les règles conditionnelles : membre → identité, orientation, consentement ; allié·e → statut ; « autre » → précision', function (): void {
    // Une dizaine d'envois refusés d'affilée : le limiteur `login` (5 par minute) est vidé avant chacun.
    $post = function (array $payload) {
        Illuminate\Support\Facades\Cache::flush();

        return $this->postJson('/api/v1/auth/register', $payload);
    };
    $payload = registerPayload();
    unset($payload['gender_identity'], $payload['orientation'], $payload['sensitive_data']);

    $post($payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['gender_identity', 'orientation', 'sensitive_data']);

    $post(registerPayload(['sensitive_data' => false]))
        ->assertUnprocessable()->assertJsonValidationErrors(['sensitive_data'])
        ->assertJsonPath('errors.sensitive_data.0', __('auth.register.errors.sensitive_data_required'));

    $post(registerPayload(['gender_identity' => 'autre', 'orientation' => 'autre']))
        ->assertUnprocessable()->assertJsonValidationErrors(['gender_identity_other', 'orientation_other']);

    $post(registerPayload(['gender_identity' => 'inconnue']))
        ->assertUnprocessable()->assertJsonValidationErrors(['gender_identity']);

    $ally = R::allyPayload();
    unset($ally['ally_role']);
    $post($ally)->assertUnprocessable()->assertJsonValidationErrors(['ally_role']);
    $post(R::allyPayload(['ally_role' => 'autre']))->assertUnprocessable()->assertJsonValidationErrors(['ally_role_other']);
    $post(R::allyPayload(['ally_role' => 'sorcier']))->assertUnprocessable()->assertJsonValidationErrors(['ally_role']);

    $post(registerPayload(['member_type' => 'pair']))->assertUnprocessable()->assertJsonValidationErrors(['member_type']);

    expect(User::query()->count())->toBe(0);
});

it('refuse un âge inférieur au minimum de la configuration, un âge non numérique, un pays inconnu et des pronoms hors liste', function (): void {
    // Une dizaine d'envois refusés d'affilée : le limiteur `login` (5 par minute) est vidé avant chacun.
    $post = function (array $payload) {
        Illuminate\Support\Facades\Cache::flush();

        return $this->postJson('/api/v1/auth/register', $payload);
    };
    $post(registerPayload(['age' => 14]))
        ->assertUnprocessable()->assertJsonValidationErrors(['age'])
        ->assertJsonPath('errors.age.0', __('auth.register.errors.age_min', ['min_age' => 18]));

    $post(registerPayload(['age' => 17]))
        ->assertUnprocessable()
        ->assertJsonPath('errors.age.0', 'Tu dois avoir au moins 18 ans pour créer un compte.');
    expect(User::query()->count())->toBe(0);

    $post(registerPayload(['age' => 'vingt']))->assertUnprocessable()->assertJsonValidationErrors(['age']);
    $post(registerPayload(['country_code' => 'ZZ']))->assertUnprocessable()->assertJsonValidationErrors(['country_code']);
    $post(registerPayload(['pronouns' => 'ael']))->assertUnprocessable()->assertJsonValidationErrors(['pronouns']);
    $post(registerPayload(['phone' => '12']))->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    $post(registerPayload(['country_code' => 'FR', 'phone' => '0612345678']))->assertUnprocessable()->assertJsonValidationErrors(['phone']);
    $post(registerPayload(['first_name' => '', 'last_name' => '123']))->assertUnprocessable()->assertJsonValidationErrors(['first_name', 'last_name']);

    expect(User::query()->count())->toBe(0);
});

it('refuse l’inscription fermée par l’administration avec le message configuré (403, registrations_closed)', function (): void {
    Settings::set('registrations_open', false);
    Settings::set('reg_closed_msg', 'Fermé pour les fêtes, retour le 3 janvier.');

    $this->postJson('/api/v1/auth/register', registerPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'registrations_closed')
        ->assertJsonPath('message', 'Fermé pour les fêtes, retour le 3 janvier.');

    expect(User::query()->count())->toBe(0);
});

it('limite les tentatives d’inscription à 5 par minute et par adresse (429)', function (): void {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/register', registerPayload(['pseudo' => 'ab']))->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/register', registerPayload(['pseudo' => 'ab']))
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_requests')
        ->assertJsonStructure(['message', 'code', 'retry_after']);
});

it('enregistre la confirmation d’âge (horodatée) quand l’âge saisi atteint le minimum', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload(['age' => 18]))->assertCreated();
    $user = User::query()->firstOrFail();

    expect($user->consents()->where('kind', 'age_confirmed')->where('granted', true)->exists())->toBeTrue()
        ->and($user->profile?->age)->toBe(18);
});

it('sert GET /referentials/registration : public, au contrat exact, avec ETag et cache', function (): void {
    $response = $this->getJson('/api/v1/referentials/registration')->assertOk();

    $response->assertJsonStructure(['data' => ['min_age', 'pronouns' => [['value', 'label']], 'member_types' => [['value', 'label']], 'countries' => [['value', 'label']], 'ally_roles' => [['value', 'label']], 'gender_identities' => [['value', 'label']], 'orientations' => [['value', 'label']]]])
        ->assertJsonPath('data.min_age', 18)
        ->assertJsonPath('data.member_types', [['value' => 'membre', 'label' => 'Membre de la communauté'], ['value' => 'allie', 'label' => 'Personne ressource / Allié·e']])
        ->assertJsonPath('data.countries.0', ['value' => 'BJ', 'label' => 'Bénin'])
        ->assertJsonPath('data.pronouns.0.value', 'elle');

    $countries = $response->json('data.countries');
    expect(count($countries))->toBe(249)
        ->and(array_column($countries, 'value'))->toContain('FR', 'CI', 'TG', 'NG')
        ->and(collect($countries)->firstWhere('value', 'FR')['label'])->toBe('France')
        ->and(collect($countries)->firstWhere('value', 'CI')['label'])->toBe('Côte d’Ivoire');

    $roles = array_column($response->json('data.ally_roles'), 'value');
    expect($roles)->toBe(['professionnel_sante', 'avocat', 'psychologue', 'leader_religieux', 'activiste', 'autre']);

    $genders = array_column($response->json('data.gender_identities'), 'value');
    $orientations = array_column($response->json('data.orientations'), 'value');
    expect($genders)->toContain('autre', 'prefer_not')->and($orientations)->toContain('autre', 'prefer_not');

    $etag = $response->headers->get('ETag');
    expect($etag)->not->toBeNull()
        ->and((string) $response->headers->get('Cache-Control'))->toContain('public');

    $this->getJson('/api/v1/referentials/registration', ['If-None-Match' => $etag])->assertStatus(304);

    // Les départements et communes viennent des référentiels existants.
    $this->getJson('/api/v1/zones')->assertOk();
});

it('lit l’âge minimal du référentiel dans la configuration, pas dans le code', function (): void {
    config(['xomiton.accounts.min_age' => 21]);

    $this->getJson('/api/v1/referentials/registration')->assertOk()->assertJsonPath('data.min_age', 21);
});
