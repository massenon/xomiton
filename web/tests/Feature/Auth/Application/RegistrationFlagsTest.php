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
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;
use Tests\Support\RegistrationFixtures;

/*
 * D-MOB-17 (2026-10-29), précisé par la décision du 11/11/2026 : les inscriptions SIGNALÉES (e-mail jetable, inscriptions
 * répétées depuis la même empreinte) sont notées dans `registration_flags` pour éclairer la validation, mais TOUS les
 * nouveaux comptes attendent l'équipe, signalés ou non : le signalement n'a aucun effet sur le statut.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'app']);
});

function rfData(string $pseudo, string $email, string $ip = '10.0.0.1', string $agent = 'Xomiton/1.0 Pixel'): RegisterData
{
    return RegistrationFixtures::data(['pseudo' => $pseudo, 'email' => $email], $ip, $agent);
}

it('met une inscription ordinaire en attente, sans signalement', function (): void {
    $user = app(RegisterUser::class)(rfData('Ordinaire', 'ordinaire@exemple.bj'));

    expect($user->status)->toBe(UserStatus::Pending)->and($user->registration_flags)->toBeNull();
});

it('enregistre le signal et met en attente une inscription avec e-mail jetable (domaine ou sous-domaine de la liste configurable)', function (): void {
    $user = app(RegisterUser::class)(rfData('Jetable', 'jetable@mailinator.com'));
    expect($user->status)->toBe(UserStatus::Pending)->and($user->registration_flags)->toBe('disposable_email')
        ->and(AuditLog::query()->where('action', 'auth.registered')->first()->detail)->toContain('signalements=disposable_email');

    expect(RegistrationFlags::isDisposable('x@eu.mailinator.com'))->toBeTrue()
        ->and(RegistrationFlags::isDisposable('x@notmailinator.com'))->toBeFalse()
        ->and(RegistrationFlags::isDisposable('X@MAILINATOR.COM'))->toBeTrue();

    config(['xomiton.accounts.registration_flags.disposable_domains' => [], 'xomiton.accounts.registration_flags.extra_domains' => 'poubelle.test, autre.test']);
    expect(RegistrationFlags::isDisposable('x@poubelle.test'))->toBeTrue()->and(RegistrationFlags::isDisposable('x@mailinator.com'))->toBeFalse();
});

it('signale les inscriptions répétées avec la même empreinte en peu de temps, pas une autre empreinte ni après le délai ; tout le monde reste en attente', function (): void {
    $this->freezeSecond();
    config(['xomiton.accounts.registration_flags.burst_max' => 2, 'xomiton.accounts.registration_flags.burst_minutes' => 30]);

    $a = app(RegisterUser::class)(rfData('Un', 'un@exemple.bj'));
    $b = app(RegisterUser::class)(rfData('Deux', 'deux@exemple.bj'));
    $c = app(RegisterUser::class)(rfData('Trois', 'trois@exemple.bj'));
    $otherAgent = app(RegisterUser::class)(rfData('Autre', 'autre@exemple.bj', agent: 'Firefox'));
    $otherIp = app(RegisterUser::class)(rfData('AutreIp', 'autreip@exemple.bj', ip: '10.0.0.2'));

    expect($a->registration_flags)->toBeNull()->and($b->registration_flags)->toBeNull()
        ->and($c->registration_flags)->toBe('repeated_fingerprint')
        ->and($otherAgent->registration_flags)->toBeNull()->and($otherIp->registration_flags)->toBeNull();
    foreach ([$a, $b, $c, $otherAgent, $otherIp] as $user) {
        expect($user->status)->toBe(UserStatus::Pending);
    }

    $this->travel(31)->minutes();
    $later = app(RegisterUser::class)(rfData('Plus tard', 'plustard@exemple.bj'));
    expect($later->registration_flags)->toBeNull()->and($later->status)->toBe(UserStatus::Pending);
});

it('cumule les deux signaux et ne signale rien sans IP', function (): void {
    config(['xomiton.accounts.registration_flags.burst_max' => 1]);
    app(RegisterUser::class)(rfData('Premier', 'premier@exemple.bj'));
    $both = app(RegisterUser::class)(rfData('Cumul', 'cumul@yopmail.com'));
    expect($both->registration_flags)->toBe('disposable_email,repeated_fingerprint');

    $noIp = app(RegisterUser::class)(RegistrationFixtures::data(['pseudo' => 'SansIp', 'email' => 'sansip@exemple.bj'], ip: ''));
    expect($noIp->registration_flags)->toBeNull()->and($noIp->status)->toBe(UserStatus::Pending)
        ->and($both->status)->toBe(UserStatus::Pending);
});

it('montre le compte signalé et ses motifs dans la file des inscriptions, que l’admin approuve ou rejette', function (): void {
    Notification::fake();
    $admin = F::member('admin');
    $flagged = app(RegisterUser::class)(rfData('Signale', 'signale@guerrillamail.com'));
    $clean = app(RegisterUser::class)(rfData('Propre', 'propre@exemple.bj'));

    Livewire::actingAs($admin)->test(RegistrationsScreen::class)
        ->assertSee('Signale')->assertSee('Adresse e-mail jetable');

    app(ReviewRegistration::class)->approve($flagged, $admin);
    expect($flagged->fresh()->status)->toBe(UserStatus::Active)->and($clean->fresh()->status)->toBe(UserStatus::Pending);

    $second = app(RegisterUser::class)(rfData('Signale2', 'signale2@yopmail.com'));
    $queue = F::as($admin)->getJson('/api/v1/admin/registrations')->assertOk();
    $rows = collect($queue->json('data'))->keyBy('id');
    expect($rows[$second->id]['flags'])->toBe(['disposable_email'])->and($rows[$clean->id]['flags'])->toBe([]);
    expect(User::query()->pending()->count())->toBe(2);
});
