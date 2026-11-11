<?php

declare(strict_types=1);

use App\Enums\OtpPurpose;
use App\Mail\TemplateMail;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * POST /api/v1/auth/password/forgot et password/reset (P2-05, Q8 c) : 202 identique que le compte existe ou non ;
 * otp/verify reset → reset_token signé ; réinitialisation révoque les jetons ; 422 par champ.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->create(['email' => 'awa@exemple.bj', 'password' => 'Ancien2026!']);
    $this->user->assignRole('membre');
});

it('répond 202 avec le même corps pour une adresse connue ou inconnue et n’envoie qu’un e-mail', function (): void {
    $known = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'AWA@exemple.bj']);
    $unknown = $this->postJson('/api/v1/auth/password/forgot', ['email' => 'personne@exemple.bj']);

    $known->assertStatus(202)->assertJsonPath('data.sent', true)->assertJsonPath('data.message', __('auth.reset_requested'));
    expect($unknown->status())->toBe(202)->and($unknown->json())->toBe($known->json());

    Mail::assertSent(TemplateMail::class, 1);
    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'reinitialisation-mot-de-passe' && $mail->hasTo('awa@exemple.bj'));
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->count())->toBe(1);

    $this->postJson('/api/v1/auth/password/forgot', ['email' => 'pas-un-email'])->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('échange le code reset contre un reset_token puis réinitialise : jetons révoqués, e-mail de confirmation, jeton à usage unique', function (): void {
    OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);
    $oldToken = $this->user->createToken('ancien')->plainTextToken;

    $exchange = $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'reset', 'email' => 'awa@exemple.bj', 'code' => '135790']);
    $exchange->assertOk()->assertJsonStructure(['data' => ['reset_token', 'expires_at']]);
    $resetToken = $exchange->json('data.reset_token');

    // Le code est consommé : le rejouer ne donne rien, ni ici ni comme preuve directe ; la preuve réutilise la ligne
    // du code (aucune ligne otp_codes supplémentaire, les plafonds d'envoi ne bougent pas).
    $this->postJson('/api/v1/auth/otp/verify', ['purpose' => 'reset', 'email' => 'awa@exemple.bj', 'code' => '135790'])->assertUnprocessable();
    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'awa@exemple.bj', 'reset_token' => '135790', 'password' => 'Nouveau2026!!', 'password_confirmation' => 'Nouveau2026!!',
    ])->assertUnprocessable()->assertJsonPath('code', 'otp_invalid');
    expect(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->count())->toBe(1);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'awa@exemple.bj',
        'reset_token' => $resetToken,
        'password' => 'Nouveau2026!!',
        'password_confirmation' => 'Nouveau2026!!',
    ])->assertOk()->assertJsonPath('data.reset', true);

    expect(Hash::check('Nouveau2026!!', $this->user->fresh()->password))->toBeTrue()
        ->and($this->user->tokens()->count())->toBe(0);
    $this->withToken($oldToken)->getJson('/api/v1/me')->assertUnauthorized();

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'mot-de-passe-modifie');

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'awa@exemple.bj',
        'reset_token' => $resetToken,
        'password' => 'Encore2026!!',
        'password_confirmation' => 'Encore2026!!',
    ])->assertUnprocessable()->assertJsonPath('code', 'otp_invalid')->assertJsonValidationErrors(['reset_token']);
});

it('accepte aussi le code à 6 chiffres directement comme reset_token', function (): void {
    OtpCode::factory()->withCode('246810')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);

    $this->postJson('/api/v1/auth/password/reset', [
        'email' => 'awa@exemple.bj',
        'reset_token' => '246810',
        'password' => 'Nouveau2026!!',
        'password_confirmation' => 'Nouveau2026!!',
    ])->assertOk();

    expect(Hash::check('Nouveau2026!!', $this->user->fresh()->password))->toBeTrue();
});

it('renvoie 422 par champ : preuve absente, mot de passe faible, confirmation différente, adresse inconnue même message', function (): void {
    $this->postJson('/api/v1/auth/password/reset', ['email' => 'awa@exemple.bj', 'password' => 'faible', 'password_confirmation' => 'autre'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['reset_token', 'password']);

    $this->postJson('/api/v1/auth/password/reset', ['email' => 'awa@exemple.bj', 'reset_token' => '000000', 'password' => 'Nouveau2026!!', 'password_confirmation' => 'Nouveau2026!!'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'otp_invalid');

    $unknown = $this->postJson('/api/v1/auth/password/reset', ['email' => 'personne@exemple.bj', 'reset_token' => '000000', 'password' => 'Nouveau2026!!', 'password_confirmation' => 'Nouveau2026!!']);
    $unknown->assertUnprocessable()->assertJsonPath('code', 'otp_invalid')->assertJsonPath('message', __('auth.otp.invalid'));

    expect(Hash::check('Ancien2026!', $this->user->fresh()->password))->toBeTrue();
});
