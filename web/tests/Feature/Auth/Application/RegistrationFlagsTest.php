<?php

declare(strict_types=1);

use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Auth\RegisterData;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\Support\RegistrationFlags;
use App\Enums\UserStatus;
use App\Livewire\Admin\Registrations\Index as RegistrationsScreen;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * D-MOB-17 (tranché 2026-10-29) : seules les inscriptions SIGNALÉES (e-mail jetable, inscriptions répétées depuis la même
 * empreinte) attendent la validation de l'équipe ; les autres sont actives dès l'inscription (accès complet après
 * confirmation de l'e-mail). `reg_manual_validation = all` reste l'interrupteur « tout valider ».
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'app']);
});

function rfData(string $pseudo, string $email, string $ip = '10.0.0.1', string $agent = 'Xomiton/1.0 Pixel'): RegisterData
{
    return new RegisterData(
        pseudo: $pseudo, email: $email, password: 'Xomiton2026!', acceptsTerms: true, acceptsPrivacy: true,
        ip: $ip, userAgent: $agent, confirmsAge: true,
    );
}

it('laisse une inscription ordinaire active, sans signalement', function (): void {
    $user = app(RegisterUser::class)(rfData('Ordinaire', 'ordinaire@exemple.bj'));

    expect($user->status)->toBe(UserStatus::Active)->and($user->registration_flags)->toBeNull();
});

it('place en attente une inscription avec e-mail jetable (domaine ou sous-domaine de la liste configurable)', function (): void {
    $user = app(RegisterUser::class)(rfData('Jetable', 'jetable@mailinator.com'));
    expect($user->status)->toBe(UserStatus::Pending)->and($user->registration_flags)->toBe('disposable_email')
        ->and(AuditLog::query()->where('action', 'auth.registered')->first()->detail)->toContain('signalements=disposable_email');

    expect(RegistrationFlags::isDisposable('x@eu.mailinator.com'))->toBeTrue()
        ->and(RegistrationFlags::isDisposable('x@notmailinator.com'))->toBeFalse()
        ->and(RegistrationFlags::isDisposable('X@MAILINATOR.COM'))->toBeTrue();

    config(['xomiton.accounts.registration_flags.disposable_domains' => [], 'xomiton.accounts.registration_flags.extra_domains' => 'poubelle.test, autre.test']);
    expect(RegistrationFlags::isDisposable('x@poubelle.test'))->toBeTrue()->and(RegistrationFlags::isDisposable('x@mailinator.com'))->toBeFalse();
});

it('signale les inscriptions répétées avec la même empreinte en peu de temps, pas une autre empreinte ni après le délai', function (): void {
    $this->freezeSecond();
    config(['xomiton.accounts.registration_flags.burst_max' => 2, 'xomiton.accounts.registration_flags.burst_minutes' => 30]);

    $a = app(RegisterUser::class)(rfData('Un', 'un@exemple.bj'));
    $b = app(RegisterUser::class)(rfData('Deux', 'deux@exemple.bj'));
    $c = app(RegisterUser::class)(rfData('Trois', 'trois@exemple.bj'));
    $otherAgent = app(RegisterUser::class)(rfData('Autre', 'autre@exemple.bj', agent: 'Firefox'));
    $otherIp = app(RegisterUser::class)(rfData('AutreIp', 'autreip@exemple.bj', ip: '10.0.0.2'));

    expect($a->status)->toBe(UserStatus::Active)->and($b->status)->toBe(UserStatus::Active)
        ->and($c->status)->toBe(UserStatus::Pending)->and($c->registration_flags)->toBe('repeated_fingerprint')
        ->and($otherAgent->status)->toBe(UserStatus::Active)->and($otherIp->status)->toBe(UserStatus::Active);

    $this->travel(31)->minutes();
    expect(app(RegisterUser::class)(rfData('Plus tard', 'plustard@exemple.bj'))->status)->toBe(UserStatus::Active);
});

it('cumule les deux signaux et ne signale rien sans IP', function (): void {
    config(['xomiton.accounts.registration_flags.burst_max' => 1]);
    app(RegisterUser::class)(rfData('Premier', 'premier@exemple.bj'));
    $both = app(RegisterUser::class)(rfData('Cumul', 'cumul@yopmail.com'));
    expect($both->registration_flags)->toBe('disposable_email,repeated_fingerprint');

    $noIp = app(RegisterUser::class)(new RegisterData(pseudo: 'SansIp', email: 'sansip@exemple.bj', password: 'Xomiton2026!', acceptsTerms: true, acceptsPrivacy: true, confirmsAge: true));
    expect($noIp->status)->toBe(UserStatus::Active);
});

it('garde « tout valider » (reg_manual_validation = all) comme interrupteur : tout le monde attend, signalé ou non', function (): void {
    Settings::set('reg_manual_validation', 'all');

    $user = app(RegisterUser::class)(rfData('Tous', 'tous@exemple.bj'));

    expect($user->status)->toBe(UserStatus::Pending)->and($user->registration_flags)->toBeNull();
});

it('montre le compte signalé et ses motifs dans la file des inscriptions, que l’admin approuve ou rejette', function (): void {
    Notification::fake();
    $admin = F::member('admin');
    $flagged = app(RegisterUser::class)(rfData('Signale', 'signale@guerrillamail.com'));
    $clean = app(RegisterUser::class)(rfData('Propre', 'propre@exemple.bj'));

    Livewire::actingAs($admin)->test(RegistrationsScreen::class)
        ->assertSee('Signale')->assertSee('Adresse e-mail jetable');

    app(ReviewRegistration::class)->approve($flagged, $admin);
    expect($flagged->fresh()->status)->toBe(UserStatus::Active)->and($clean->fresh()->status)->toBe(UserStatus::Active);

    $second = app(RegisterUser::class)(rfData('Signale2', 'signale2@yopmail.com'));
    $queue = F::as($admin)->getJson('/api/v1/admin/registrations')->assertOk();
    expect($queue->json('data.0.flags'))->toBe(['disposable_email'])->and($queue->json('data.0.id'))->toBe($second->id);
    expect(User::query()->pending()->count())->toBe(1);
});
