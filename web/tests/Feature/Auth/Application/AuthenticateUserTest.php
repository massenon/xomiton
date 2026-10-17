<?php

declare(strict_types=1);

use App\Application\Auth\AuthenticateUser;
use App\Application\Auth\DeviceTrust;
use App\Application\Auth\LoginData;
use App\Application\Auth\VerifyLoginOtp;
use App\Domain\Auth\Exceptions\AccountSuspended;
use App\Domain\Auth\Exceptions\AuthFailed;
use App\Domain\Auth\Exceptions\MaintenanceActive;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\MaintenanceWindow;
use App\Models\OtpCode;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Mail;

/*
 * P2-02 : identifiant e-mail ou pseudo (casse ignorée), échec indifférencié, états signalés après le mot de passe,
 * OTP `login` seulement sur appareil non reconnu ou pour admin / superadmin (Q13 b).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'password' => 'Xomiton2026!']);
    $this->user->assignRole('membre');
    $this->authenticate = app(AuthenticateUser::class);
    $this->devices = app(DeviceTrust::class);
});

function login(string $identifier, string $password, ?string $cookie = null): LoginData
{
    return new LoginData(identifier: $identifier, password: $password, ip: '41.79.1.1', deviceCookie: $cookie);
}

it('renvoie le même message pour un identifiant inconnu et pour un mauvais mot de passe', function (): void {
    $unknown = null;
    $wrong = null;

    try {
        ($this->authenticate)(login('inconnue@exemple.bj', 'Xomiton2026!'));
    } catch (AuthFailed $e) {
        $unknown = $e;
    }

    try {
        ($this->authenticate)(login('awa@exemple.bj', 'mauvais'));
    } catch (AuthFailed $e) {
        $wrong = $e;
    }

    expect($unknown)->toBeInstanceOf(AuthFailed::class)
        ->and($wrong)->toBeInstanceOf(AuthFailed::class)
        ->and($unknown->userMessage())->toBe($wrong->userMessage())
        ->and($wrong->userMessage())->toBe('Identifiants incorrects. Vérifie ton e-mail et ton mot de passe.')
        ->and(AuditLog::query()->ofAction('auth.failed')->count())->toBe(2);
});

it('refuse un compte supprimé (corbeille) avec le même message, par e-mail comme par pseudonyme', function (): void {
    $this->user->delete();

    expect(fn () => ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!')))->toThrow(AuthFailed::class)
        ->and(fn () => ($this->authenticate)(login('AWA_S', 'Xomiton2026!')))->toThrow(AuthFailed::class)
        ->and(OtpCode::query()->count())->toBe(0);
});

it('accepte l’e-mail ou le pseudonyme sans tenir compte de la casse et exige un OTP sur un appareil inconnu', function (): void {
    $byPseudo = ($this->authenticate)(login('AWA_s', 'Xomiton2026!'));
    $byEmail = ($this->authenticate)(login('AWA@EXEMPLE.BJ', 'Xomiton2026!'));

    expect($byPseudo->user->is($this->user))->toBeTrue()
        ->and($byEmail->user->is($this->user))->toBeTrue()
        ->and($byPseudo->requiresOtp)->toBeTrue()
        ->and($byPseudo->pendingValidation)->toBeFalse()
        ->and($byPseudo->otp)->toBeInstanceOf(OtpCode::class)
        ->and($byEmail->otp?->is($byPseudo->otp))->toBeTrue() // renvoi trop tôt : le code précédent reste valable
        ->and($this->user->fresh()?->last_login_at)->toBeNull()
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Login)->valid()->count())->toBe(1);

    Mail::assertSent(TemplateMail::class, 1);
});

it('ouvre la session sans OTP sur un appareil reconnu, et le code de connexion met à jour last_login_at', function (): void {
    $response = $this->devices->trustDevice($this->user, new Response);
    $cookie = collect($response->headers->getCookies())->firstWhere(fn ($c) => $c->getName() === DeviceTrust::COOKIE);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp());

    $request = Request::create('/connexion', 'POST', [], [DeviceTrust::COOKIE => $cookie->getValue()]);
    expect($this->devices->deviceIsTrusted($this->user, $request))->toBeTrue()
        ->and($this->devices->deviceIsTrusted(User::factory()->create(), $request))->toBeFalse();

    $result = ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!', $cookie->getValue()));

    expect($result->requiresOtp)->toBeFalse()
        ->and($result->otp)->toBeNull()
        ->and($this->user->fresh()?->last_login_at)->not->toBeNull();
    Mail::assertNothingSent();

    // Le cookie ne survit pas à un changement de mot de passe.
    $this->user->forceFill(['password' => 'Nouveau2026!'])->save();
    expect($this->devices->cookieMatches($this->user->fresh(), $cookie->getValue()))->toBeFalse()
        ->and($this->devices->cookieMatches($this->user, 'valeur-altérée'))->toBeFalse();
});

it('vérifie le code OTP de connexion', function (): void {
    $result = ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!'));
    OtpCode::query()->whereKey($result->otp?->getKey())->update(['code_hash' => OtpCode::hash('246810')]);

    expect(fn () => app(VerifyLoginOtp::class)($this->user, '111111'))->toThrow(OtpInvalid::class);

    app(VerifyLoginOtp::class)($this->user, '246810');

    expect($this->user->fresh()?->last_login_at)->not->toBeNull()
        ->and(AuditLog::query()->ofAction('auth.otp_verified')->forSubject($this->user)->exists())->toBeTrue();
});

it('impose l’OTP aux rôles admin et superadmin même sur un appareil reconnu', function (): void {
    $admin = User::factory()->create(['email' => 'admin@exemple.bj', 'password' => 'Admin2026!!']);
    $admin->assignRole('admin');
    $cookie = $this->devices->makeCookie($admin)->getValue();

    $result = ($this->authenticate)(login('admin@exemple.bj', 'Admin2026!!', $cookie));

    expect($result->requiresOtp)->toBeTrue()
        ->and($result->otp)->not->toBeNull();
});

it('signale un compte suspendu seulement après vérification du mot de passe', function (): void {
    $this->user->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now()])->save();

    expect(fn () => ($this->authenticate)(login('awa@exemple.bj', 'mauvais')))->toThrow(AuthFailed::class)
        ->and(fn () => ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!')))->toThrow(AccountSuspended::class, 'contact@xomiton.bj');
});

it('laisse se connecter un·e pair en attente avec le bandeau, et refuse les membres en maintenance mais pas l’administration', function (): void {
    $this->user->forceFill(['status' => UserStatus::Pending])->save();

    $pending = ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!'));
    expect($pending->pendingValidation)->toBeTrue();

    Settings::set('maintenance', true);
    expect(fn () => ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!')))->toThrow(MaintenanceActive::class);

    Settings::set('maintenance', false);
    MaintenanceWindow::factory()->create(['starts_at' => now()->subMinute(), 'ends_at' => now()->addHour(), 'is_active' => true]);
    expect(fn () => ($this->authenticate)(login('awa@exemple.bj', 'Xomiton2026!')))->toThrow(MaintenanceActive::class);

    $admin = User::factory()->create(['email' => 'super@exemple.bj', 'password' => 'Super2026!!']);
    $admin->assignRole('superadmin');
    expect(($this->authenticate)(login('super@exemple.bj', 'Super2026!!'))->requiresOtp)->toBeTrue();
});
