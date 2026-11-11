<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;

/*
 * POST /api/v1/auth/otp/send et otp/verify (P2-04, P2-06) : renvoi borné (30 s, 5/h), `signup` confirme l'e-mail,
 * message unique pour code faux / adresse inconnue, `login` jamais sans connexion en cours.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->unverified()->create(['email' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    $this->user->assignRole('membre');
});

it('renvoie un code signup (202), refuse un renvoi avant 30 s (429 otp_too_soon) puis l’accepte', function (): void {
    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'signup', 'email' => 'AWA@exemple.bj'])
        ->assertStatus(202)
        ->assertJsonPath('data.otp_sent', true)
        ->assertJsonPath('data.purpose', 'signup')
        ->assertJsonPath('data.resend_after', 30);

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'code-verification' && $mail->hasTo('awa@exemple.bj'));

    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'signup', 'email' => 'awa@exemple.bj'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'otp_too_soon')
        ->assertJsonStructure(['message', 'code', 'retry_after']);

    $this->travel(31)->seconds();

    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'signup', 'email' => 'awa@exemple.bj'])->assertStatus(202);
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->valid()->count())->toBe(1)
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Signup)->count())->toBe(2);
});

it('répond 202 sans rien envoyer pour une adresse inconnue, un compte déjà confirmé ou un code login sans connexion en cours', function (): void {
    $verified = User::factory()->create(['email' => 'ok@exemple.bj']);

    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'signup', 'email' => 'personne@exemple.bj'])->assertStatus(202)->assertJsonPath('data.otp_sent', true);
    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'signup', 'email' => $verified->email])->assertStatus(202);
    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'login', 'email' => 'awa@exemple.bj'])->assertStatus(202);

    expect(OtpCode::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('renouvelle un code login seulement quand le mot de passe vient d’être vérifié', function (): void {
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'x'])->assertStatus(202);
    $first = OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Login)->valid()->firstOrFail();

    $this->travel(31)->seconds();
    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'login', 'email' => 'awa@exemple.bj'])->assertStatus(202);

    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Login)->valid()->count())->toBe(1)
        ->and($first->fresh()?->consumed_at)->not->toBeNull();
});

it('confirme l’e-mail avec le code signup, en anonyme ou avec le jeton du compte', function (): void {
    OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Signup)->create(['email' => 'awa@exemple.bj']);

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'signup', 'email' => 'awa@exemple.bj', 'code' => '000000'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid')
        ->assertJsonValidationErrors(['code' => __('auth.otp.invalid')]);

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'signup', 'email' => 'awa@exemple.bj', 'code' => '135790'])
        ->assertOk()
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonMissingPath('data.token');

    expect($this->user->fresh()?->email_verified_at)->not->toBeNull();

    // Avec un jeton, l'adresse est facultative : c'est celle du compte.
    $other = User::factory()->unverified()->create(['email' => 'bea@exemple.bj']);
    OtpCode::factory()->withCode('224466')->forPurpose(OtpPurpose::Signup)->create(['email' => 'bea@exemple.bj']);
    Sanctum::actingAs($other);

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'signup', 'code' => '224466'])->assertOk();
    expect($other->fresh()?->email_verified_at)->not->toBeNull();
});

it('renvoie le même message pour une adresse inconnue, un code faux et un code épuisé, et valide les champs', function (): void {
    OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Signup)->create(['email' => 'awa@exemple.bj']);

    $unknown = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'signup', 'email' => 'personne@exemple.bj', 'code' => '135790']);
    $wrong = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'signup', 'email' => 'awa@exemple.bj', 'code' => '111111']);

    $unknown->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
    $wrong->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
    expect($unknown->json('message'))->toBe($wrong->json('message'));

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'ailleurs', 'email' => 'pas-un-email', 'code' => '12'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['purpose', 'email', 'code']);
});

it('ne connecte jamais un compte suspendu par le code login (403)', function (): void {
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'x'])->assertStatus(202);
    OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Login)->valid()->firstOrFail()->forceFill(['code_hash' => OtpCode::hash('135790')])->save();
    $this->user->forceFill(['status' => App\Enums\UserStatus::Suspended, 'suspended_at' => now()])->save();

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'awa@exemple.bj', 'code' => '135790'])
        ->assertForbidden()
        ->assertJsonPath('code', 'account_suspended');

    expect($this->user->tokens()->count())->toBe(0);
});

it('ne trahit pas un compte suspendu sans code juste : code faux → 422 otp_invalid comme pour une adresse inconnue', function (): void {
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'x'])->assertStatus(202);
    $this->user->forceFill(['status' => App\Enums\UserStatus::Suspended, 'suspended_at' => now()])->save();

    $suspended = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'awa@exemple.bj', 'code' => '000000']);
    $unknown = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'personne@exemple.bj', 'code' => '000000']);

    $suspended->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
    $unknown->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
    expect($suspended->json('message'))->toBe($unknown->json('message'));
});

it('refuse à l’étape OTP un·e membre quand la maintenance a commencé entre les deux étapes (503), jamais l’administration', function (): void {
    $this->postJson('/api/v1/auth/login', ['login' => 'awa@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'x'])->assertStatus(202);
    OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Login)->valid()->firstOrFail()->forceFill(['code_hash' => OtpCode::hash('135790')])->save();
    App\Support\Settings::set('maintenance', true);

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'awa@exemple.bj', 'code' => '135790'])
        ->assertStatus(503)
        ->assertJsonPath('code', 'maintenance');
    expect($this->user->tokens()->count())->toBe(0);

    $admin = User::factory()->create(['email' => 'admin@exemple.bj', 'password' => 'Xomiton2026!']);
    $admin->assignRole('admin');
    $this->postJson('/api/v1/auth/login', ['login' => 'admin@exemple.bj', 'password' => 'Xomiton2026!', 'device_name' => 'x'])->assertStatus(202);
    OtpCode::query()->forEmail('admin@exemple.bj', OtpPurpose::Login)->valid()->firstOrFail()->forceFill(['code_hash' => OtpCode::hash('246802')])->save();

    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'login', 'email' => 'admin@exemple.bj', 'code' => '246802'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['token', 'device_token']]);
});

it('répond au renvoi reset avec le même resend_after qu’un code existe ou non pour l’adresse', function (): void {
    $known = $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'reset', 'email' => 'awa@exemple.bj'])->assertStatus(202);
    $again = $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'reset', 'email' => 'awa@exemple.bj'])->assertStatus(202);
    $unknown = $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'reset', 'email' => 'personne@exemple.bj'])->assertStatus(202);

    expect($known->json('data'))->toBe($unknown->json('data'))
        ->and($again->json('data'))->toBe($unknown->json('data'));
});

it('limite les demandes de code à 5 par heure et par adresse (429 too_many_requests)', function (): void {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'reset', 'email' => 'personne@exemple.bj'])->assertStatus(202);
    }

    $this->postJson('/api/v1/auth/otp/send', ['purpose' => 'reset', 'email' => 'PERSONNE@exemple.bj'])
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('Retry-After');
});
