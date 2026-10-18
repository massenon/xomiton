<?php

declare(strict_types=1);

use App\Application\Auth\RequestPasswordReset;
use App\Application\Auth\ResetPassword;
use App\Application\Auth\Support\SignedResetToken;
use App\Application\Auth\ValidatePeerAccount;
use App\Domain\Auth\Events\PeerValidated;
use App\Domain\Auth\Exceptions\OtpInvalid;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Events\Auth\PasswordResetCompleted;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\MailLog;
use App\Models\OtpCode;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

/*
 * P2-05 (Q8 c) : un seul e-mail avec code et lien magique, réponse identique pour une adresse inconnue, révocation des
 * sessions et jetons, e-mail de confirmation `mot-de-passe-modifie`. P2-10 : validation d'un compte pair.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
    Carbon::setTestNow('2026-10-17 10:00:00');
    $this->user = User::factory()->create(['email' => 'awa@exemple.bj', 'name' => 'Awa Sossou', 'password' => 'Ancien2026!']);
});

afterEach(fn () => Carbon::setTestNow());

it('envoie un seul e-mail portant le code et le lien magique, et rien pour une adresse inconnue', function (): void {
    app(RequestPasswordReset::class)('AWA@exemple.bj', '41.79.1.1');
    app(RequestPasswordReset::class)('personne@exemple.bj');

    $otp = OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->firstOrFail();

    Mail::assertSent(TemplateMail::class, 1);
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use ($otp): bool {
        preg_match('/<h1>(\d{6})<\/h1>/', $mail->htmlBody, $m);
        preg_match('/href="([^"]+nouveau-mot-de-passe[^"]*)"/', $mail->htmlBody, $link);

        return $mail->templateSlug === 'reinitialisation-mot-de-passe'
            && $mail->hasTo('awa@exemple.bj')
            && isset($m[1]) && $otp->matches($m[1])
            && isset($link[1]) && str_contains(html_entity_decode($link[1]), 'token=')
            && str_contains($mail->htmlBody, 'valables 10 minutes')
            && str_contains($mail->htmlBody, 'Bonjour Awa,')
            && ! str_contains($mail->htmlBody, '60 minutes');
    });

    expect(OtpCode::query()->where('email', 'personne@exemple.bj')->count())->toBe(0)
        ->and(MailLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->ofAction('auth.password_reset_requested')->forSubject($this->user)->exists())->toBeTrue();
});

it('réinitialise avec le code : sessions et jetons révoqués, appareils oubliés, e-mail de confirmation', function (): void {
    Event::fake([PasswordResetCompleted::class]);
    OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);
    DB::table('sessions')->insert(['id' => 'sess-1', 'user_id' => $this->user->id, 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'sess-autre', 'user_id' => User::factory()->create()->id, 'payload' => '', 'last_activity' => time()]);
    $this->user->createToken('mobile');
    $oldRemember = $this->user->remember_token;

    app(ResetPassword::class)('awa@exemple.bj', '135790', 'Nouveau2026!!');

    $fresh = $this->user->fresh();

    expect(Hash::check('Nouveau2026!!', $fresh->password))->toBeTrue()
        ->and($fresh->remember_token)->not->toBe($oldRemember)
        ->and(DB::table('sessions')->where('user_id', $this->user->id)->count())->toBe(0)
        ->and(DB::table('sessions')->count())->toBe(1)
        ->and($this->user->tokens()->count())->toBe(0)
        ->and(OtpCode::query()->forEmail('awa@exemple.bj', OtpPurpose::Reset)->valid()->count())->toBe(0)
        ->and(AuditLog::query()->ofAction('auth.password_reset')->forSubject($this->user)->exists())->toBeTrue();

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'mot-de-passe-modifie'
        && $mail->hasTo('awa@exemple.bj')
        && str_contains($mail->htmlBody, 'toutes tes sessions ont été fermées'));
    Event::assertDispatched(PasswordResetCompleted::class);
});

it('réinitialise avec le lien magique signé, valable le temps du code et une seule fois', function (): void {
    $otp = OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);
    $token = SignedResetToken::make($otp);

    expect(SignedResetToken::looksLikeToken($token))->toBeTrue()
        ->and(SignedResetToken::looksLikeToken('135790'))->toBeFalse()
        ->and(SignedResetToken::verify($token, 'autre@exemple.bj'))->toBeNull()
        ->and(SignedResetToken::verify(substr($token, 0, -1).'0', 'awa@exemple.bj'))->toBeNull()
        ->and(SignedResetToken::url($token, 'awa@exemple.bj'))->toContain('nouveau-mot-de-passe', 'token=', 'email=awa%40exemple.bj');

    app(ResetPassword::class)('awa@exemple.bj', $token, 'Nouveau2026!!');

    expect(Hash::check('Nouveau2026!!', $this->user->fresh()->password))->toBeTrue()
        ->and(fn () => app(ResetPassword::class)('awa@exemple.bj', $token, 'Encore2026!!'))->toThrow(OtpInvalid::class);

    $expiring = OtpCode::factory()->withCode('000000')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);
    $lateToken = SignedResetToken::make($expiring);
    Carbon::setTestNow('2026-10-17 10:10:01');

    expect(fn () => app(ResetPassword::class)('awa@exemple.bj', $lateToken, 'Tard2026!!!'))->toThrow(OtpInvalid::class);
});

it('refuse un code faux, une adresse inconnue et un jeton forgé avec le même message', function (): void {
    OtpCode::factory()->withCode('135790')->forPurpose(OtpPurpose::Reset)->create(['email' => 'awa@exemple.bj']);

    expect(fn () => app(ResetPassword::class)('awa@exemple.bj', '000000', 'Nouveau2026!!'))->toThrow(OtpInvalid::class)
        ->and(fn () => app(ResetPassword::class)('personne@exemple.bj', '135790', 'Nouveau2026!!'))->toThrow(OtpInvalid::class)
        ->and(fn () => app(ResetPassword::class)('awa@exemple.bj', 'abc.'.str_repeat('0', 64), 'Nouveau2026!!'))->toThrow(OtpInvalid::class)
        ->and(Hash::check('Ancien2026!', $this->user->fresh()->password))->toBeTrue();

    Mail::assertNothingSent();
});

it('valide un compte pair : actif, rôle pair, e-mail compte-valide, cloche et audit au nom de l’admin', function (): void {
    Event::fake([PeerValidated::class]);
    $peer = User::factory()->pending()->withProfile(['member_type' => MemberType::Peer])->create(['email' => 'pair@exemple.bj', 'name' => 'Chantal H']);
    $peer->assignRole('pair');
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    app(ValidatePeerAccount::class)($peer, $admin);

    $peer->refresh();

    expect($peer->status)->toBe(UserStatus::Active)
        ->and($peer->hasRole('pair'))->toBeTrue()
        ->and($peer->notifications()->count())->toBe(1)
        ->and($peer->notifications()->first()?->data['category'])->toBe('securite')
        ->and(DB::table('notifications')->where('notifiable_id', $peer->id)->value('category'))->toBe('securite')
        ->and((bool) DB::table('notifications')->where('notifiable_id', $peer->id)->value('is_sensitive'))->toBeFalse();

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->templateSlug === 'compte-valide'
        && $mail->hasTo('pair@exemple.bj')
        && str_contains($mail->htmlBody, 'Bonjour Chantal,'));

    $audit = AuditLog::query()->ofAction('users.validate_peer')->forSubject($peer)->firstOrFail();
    expect($audit->user_id)->toBe($admin->id)
        ->and($audit->actor)->toBe($admin->email);
    Event::assertDispatched(PeerValidated::class);
});
