<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\OtpCode;
use App\Models\User;
use App\Models\Zone;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * POST /api/v1/auth/register (P2-12, Q12 c) : compte créé et jeton délivré dès la phase 1, code `signup` envoyé,
 * vérification différée ; `profile` et `consents` facultatifs ; 422 par champ ; 403 inscriptions fermées ; 429.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

function registerPayload(array $overrides = []): array
{
    return array_merge([
        'pseudo' => 'Awa_S',
        'email' => 'Awa@Exemple.bj',
        'password' => 'Xomiton2026!',
        'cgu' => true,
        'privacy' => true,
        'age_confirmed' => true,
        'device_name' => 'Pixel 7 de Awa',
    ], $overrides);
}

it('crée le compte, délivre un jeton immédiatement et envoie le code de confirmation (201)', function (): void {
    $response = $this->postJson('/api/v1/auth/register', registerPayload());

    $response->assertCreated()
        ->assertJsonPath('data.email_verification_pending', true)
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.pseudo', 'Awa_S')
        ->assertJsonPath('data.user.email', 'awa@exemple.bj')
        ->assertJsonPath('data.user.email_verified', false)
        ->assertJsonPath('data.user.role', 'membre')
        ->assertJsonPath('data.user.status', UserStatus::Active->value)
        ->assertJsonPath('data.user.profile.member_type', MemberType::Member->value)
        ->assertJsonMissingPath('data.user.name');

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBe('');

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->email_verified_at)->toBeNull()
        ->and($user->consents()->count())->toBe(3)
        ->and($user->tokens()->count())->toBe(1)
        ->and($user->tokens()->first()?->name)->toBe('Pixel 7 de Awa')
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->count())->toBe(1)
        ->and(AuditLog::query()->ofAction('auth.token_issued')->forSubject($user)->exists())->toBeTrue();

    $slugs = [];
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$slugs): bool {
        $slugs[] = $mail->templateSlug;

        return true;
    });
    expect($slugs)->toBe(['code-verification', 'bienvenue']);

    // Le jeton ouvre tout de suite GET me (accès limité tant que l'e-mail n'est pas confirmé).
    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email_verified', false);
});

it('accepte les phases 2-3 (profile) et 4 (consents) : un·e pair passe en attente, chaque consentement est horodaté', function (): void {
    $zone = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id]);

    $response = $this->postJson('/api/v1/auth/register', registerPayload([
        'profile' => [
            'pronouns' => 'elle',
            'zone_id' => $zone->id,
            'commune_id' => $commune->id,
            'member_type' => 'pair',
            'trusted_contact_phone' => '97 38 88 47',
            'discreet_mode' => true,
        ],
        'consents' => ['sensitive_data' => true, 'newsletter' => false],
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.user.status', UserStatus::Pending->value)
        ->assertJsonPath('data.user.pending_validation', true)
        ->assertJsonPath('data.user.role', 'pair')
        ->assertJsonPath('data.user.profile.pronouns', 'elle')
        ->assertJsonPath('data.user.profile.zone.id', $zone->id)
        ->assertJsonPath('data.user.profile.discreet_mode', true)
        ->assertJsonPath('data.user.profile.trusted_contact_phone', '+229 01 97 38 88 47');

    $user = User::query()->where('email', 'awa@exemple.bj')->firstOrFail();
    expect($user->consents()->count())->toBe(5)
        ->and($user->consents()->ofKind(ConsentKind::SensitiveData)->granted()->exists())->toBeTrue()
        ->and($user->consents()->ofKind(ConsentKind::Newsletter)->where('granted', false)->exists())->toBeTrue();
});

it('renvoie 422 champ par champ : pseudonyme, e-mail déjà pris (casse ignorée), mot de passe faible, cases non cochées', function (): void {
    User::factory()->create(['email' => 'prise@exemple.bj', 'pseudo' => 'Deja_La']);

    $this->postJson('/api/v1/auth/register', registerPayload([
        'pseudo' => 'ab',
        'email' => 'PRISE@exemple.bj',
        'password' => 'faible',
        'cgu' => false,
        'privacy' => false,
    ]))
        ->assertUnprocessable()
        ->assertJsonStructure(['message', 'errors'])
        ->assertJsonValidationErrors(['pseudo', 'email', 'password', 'cgu', 'privacy'])
        ->assertJsonPath('errors.email.0', __('auth.api.email_taken'))
        ->assertJsonPath('errors.cgu.0', __('validation.custom.cgu.accepted'));

    $this->postJson('/api/v1/auth/register', registerPayload(['pseudo' => 'deja_la', 'password' => 'sansmajuscule2026']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pseudo' => __('validation.pseudo.taken'), 'password' => __('validation.strong_password.uppercase')]);

    $this->postJson('/api/v1/auth/register', registerPayload(['profile' => ['member_type' => 'allie', 'trusted_contact_phone' => '12']]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['profile.member_type', 'profile.trusted_contact_phone']);

    expect(User::query()->count())->toBe(1);
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

it('Q68 : refuse sans la case d’âge (422 age_confirmation_required, aucun compte) et ne stocke que son horodatage', function (): void {
    $this->postJson('/api/v1/auth/register', registerPayload(['age_confirmed' => false]))
        ->assertUnprocessable()
        ->assertJsonPath('code', 'age_confirmation_required')
        ->assertJsonPath('errors.age_confirmed.0', __('auth.age_confirmation_required', ['min_age' => 15]));
    expect(User::query()->count())->toBe(0);

    $this->postJson('/api/v1/auth/register', registerPayload())->assertCreated();
    $user = User::query()->firstOrFail();
    expect($user->consents()->where('kind', 'age_confirmed')->where('granted', true)->exists())->toBeTrue();
});
