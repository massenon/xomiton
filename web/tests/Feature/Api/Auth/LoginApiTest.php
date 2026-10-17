<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;

/*
 * POST /api/v1/auth/login (P2-06, Q13 b) : 202 { otp_sent } sur appareil inconnu ou pour admin / superadmin, sinon 200
 * { token, user, device_token } ; échec indifférencié 422 ; suspendu 403 ; maintenance 503 ; 429.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    $this->user->assignRole('membre');
});

function loginPayload(array $overrides = []): array
{
    return array_merge(['login' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'Pixel 7'], $overrides);
}

/** Dernier code `login` encore valable, en clair (le hachage est remplacé par un code connu). */
function forceLoginCode(string $email, string $code): void
{
    OtpCode::query()->forEmail($email, OtpPurpose::Login)->valid()->latest('id')->firstOrFail()
        ->forceFill(['code_hash' => OtpCode::hash($code)])->save();
}

it('demande un code sur un appareil inconnu (202) puis délivre jeton et device_token ; l’appareil reconnu se connecte sans OTP', function (): void {
    $pending = $this->postJson('/api/v1/auth/login', loginPayload(['login' => 'AWA_s']));

    $pending->assertStatus(202)
        ->assertJsonPath('data.otp_sent', true)
        ->assertJsonPath('data.purpose', 'login')
        ->assertJsonPath('data.email', 'awa@exemple.bj')
        ->assertJsonStructure(['data' => ['otp_sent', 'resend_after', 'expires_in', 'email']])
        ->assertJsonMissingPath('data.token');

    expect($this->user->tokens()->count())->toBe(0);

    forceLoginCode('awa@exemple.bj', '246810');

    $verified = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'awa@exemple.bj', 'code' => '24 68 10', 'device_name' => 'Pixel 7']);

    $verified->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.pseudo', 'Awa_S')
        ->assertJsonStructure(['data' => ['token', 'user', 'device_token', 'device_token_expires_at']]);

    $token = $verified->json('data.token');
    $deviceToken = $verified->json('data.device_token');

    $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.email', 'awa@exemple.bj');
    expect($this->user->fresh()?->last_login_at)->not->toBeNull();

    // Appareil reconnu : connexion directe, nouveau jeton, nouveau device_token.
    $direct = $this->postJson('/api/v1/auth/login', loginPayload(['device_token' => $deviceToken]));
    $direct->assertOk()->assertJsonStructure(['data' => ['token', 'user', 'device_token']]);
    expect($this->user->tokens()->count())->toBe(2);

    // Un device_token altéré ou d'un autre compte ramène à l'OTP.
    $other = User::factory()->create(['password' => 'Autre2026!!']);
    $this->postJson('/api/v1/auth/login', loginPayload(['device_token' => 'valeur-alteree']))->assertStatus(202);
    $this->postJson('/api/v1/auth/login', ['login' => $other->email, 'password' => 'Autre2026!!', 'device_name' => 'x', 'device_token' => $deviceToken])->assertStatus(202);
});

it('renvoie le même message 422 pour un identifiant inconnu et un mauvais mot de passe', function (): void {
    $unknown = $this->postJson('/api/v1/auth/login', loginPayload(['login' => 'inconnue@exemple.bj']));
    $wrong = $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'mauvais']));

    $unknown->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials')->assertJsonValidationErrors(['login']);
    $wrong->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');

    expect($unknown->json('message'))->toBe($wrong->json('message'))
        ->and($wrong->json('errors.login.0'))->toBe(__('auth.failed'));
});

it('valide les champs : login, password et device_name obligatoires', function (): void {
    $this->postJson('/api/v1/auth/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['login', 'password', 'device_name']);
});

it('signale un compte suspendu (403, account_suspended) seulement après le mot de passe', function (): void {
    $this->user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now()])->save();

    $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'mauvais']))->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');

    $this->postJson('/api/v1/auth/login', loginPayload())
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended')
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'contact@xomiton.bj'));
});

it('refuse les membres en maintenance (503, maintenance) mais laisse passer l’administration, toujours avec OTP', function (): void {
    Settings::set('maintenance', true);

    $this->postJson('/api/v1/auth/login', loginPayload())
        ->assertStatus(503)
        ->assertJsonPath('code', 'maintenance');

    $admin = User::factory()->create(['email' => 'admin@exemple.bj', 'password' => 'Admin2026!!']);
    $admin->assignRole('admin');

    $this->postJson('/api/v1/auth/login', ['login' => 'admin@exemple.bj', 'password' => 'Admin2026!!', 'device_name' => 'Bureau'])
        ->assertStatus(202)
        ->assertJsonPath('data.otp_sent', true);
});

it('impose l’OTP aux rôles admin et superadmin même avec un device_token valable', function (): void {
    $admin = User::factory()->create(['email' => 'super@exemple.bj', 'password' => 'Super2026!!']);
    $admin->assignRole('superadmin');
    $deviceToken = app(App\Application\Auth\DeviceTrust::class)->makeCookie($admin)->getValue();

    $this->postJson('/api/v1/auth/login', ['login' => 'super@exemple.bj', 'password' => 'Super2026!!', 'device_name' => 'Bureau', 'device_token' => $deviceToken])
        ->assertStatus(202)
        ->assertJsonMissingPath('data.token');
});

it('connecte un·e pair en attente de validation avec l’indicateur pending_validation', function (): void {
    $this->user->forceFill(['status' => UserStatus::Pending])->save();

    $this->postJson('/api/v1/auth/login', loginPayload())->assertStatus(202);
    forceLoginCode('awa@exemple.bj', '135790');

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'awa@exemple.bj', 'code' => '135790'])
        ->assertOk()
        ->assertJsonPath('data.user.pending_validation', true)
        ->assertJsonPath('data.user.status', 'en_attente');
});

it('limite la connexion à 5 tentatives par minute et par identifiant + IP (429)', function (): void {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', loginPayload(['password' => 'mauvais']))->assertUnprocessable();
    }

    $this->postJson('/api/v1/auth/login', loginPayload())
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');

    // Un autre identifiant garde son propre compteur.
    $this->postJson('/api/v1/auth/login', loginPayload(['login' => 'autre@exemple.bj']))->assertUnprocessable();
});
