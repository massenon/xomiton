<?php

declare(strict_types=1);

use App\Application\Admin\Registrations\RegistrationSettings;
use App\Application\Auth\RegisterData;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\Support\RegistrationGate;
use App\Domain\Auth\Exceptions\RegistrationsClosed;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Livewire\Admin\Registrations\Index as RegistrationsScreen;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * P12-02 : Inscriptions /admin/inscriptions (Livewire) — `registrations.manage` (admin, superadmin ; 403 pour le
 * modérateur, le pair et le membre), interrupteur, options (validation manuelle Q26, message ≤ 300 caractères lu par la page
 * des inscriptions fermées, réouverture, plage horaire à l'heure du Bénin), inscriptions par semaine, demandes en attente.
 * Done-when du plan : inscription refusée hors plage avec le message configuré.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array', 'xomiton.display_timezone' => 'Africa/Porto-Novo']);
});

function regUser(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function regScreen(string $role = 'admin')
{
    test()->actingAs(regUser($role));

    return Livewire::test(RegistrationsScreen::class);
}

function regData(array $overrides = []): RegisterData
{
    return new RegisterData(...array_merge([
        'pseudo' => 'Awa_S',
        'email' => 'awa@exemple.bj',
        'password' => 'Xomiton2026!',
        'acceptsTerms' => true,
        'acceptsPrivacy' => true,
    ], $overrides));
}

it('sert l’écran à l’admin et au superadmin, le refuse au modérateur, au pair, au membre et aux visiteurs', function (): void {
    foreach (['admin', 'superadmin'] as $role) {
        $html = $this->actingAs(regUser($role))->get(route('admin.inscriptions'))
            ->assertOk()
            ->assertSeeLivewire(RegistrationsScreen::class)
            ->assertSee('Options d’inscription')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="inscriptions"\s+aria-current="page"/');
    }

    foreach (['moderateur', 'pair', 'membre'] as $role) {
        $this->actingAs(regUser($role))->get(route('admin.inscriptions'))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('admin.inscriptions'))->assertRedirect();
});

it('ferme et rouvre les inscriptions : page publique, état et journal', function (): void {
    regScreen()->assertSee('Ouvertes')->call('setOpen', false)->assertSee('Fermées');

    expect(Settings::bool('registrations_open'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'registrations.closed')->exists())->toBeTrue();

    auth()->logout();
    $this->get('/inscription')->assertOk()->assertSee('Les inscriptions sont momentanément fermées');

    regScreen()->call('setOpen', true);
    auth()->logout();
    expect(Settings::bool('registrations_open'))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'registrations.opened')->exists())->toBeTrue();
    $this->get('/inscription')->assertOk()->assertDontSee('data-xm-registrations-closed', false);
});

it('affiche le message de fermeture configuré (≤ 300 caractères) et la date de réouverture sur la page publique', function (): void {
    regScreen()
        ->set('message', 'Nous préparons une nouvelle version. Revenez bientôt.')
        ->set('reopenAt', '2026-12-01T09:00')
        ->call('save')
        ->assertHasNoErrors()
        ->call('setOpen', false);

    auth()->logout();
    $this->get('/inscription')
        ->assertOk()
        ->assertSee('Nous préparons une nouvelle version. Revenez bientôt.')
        ->assertSee('1 décembre 2026');

    expect(AuditLog::query()->where('action', 'registrations.options_updated')->exists())->toBeTrue();
});

it('refuse un message de plus de 300 caractères, une date invalide et une plage inversée', function (): void {
    regScreen()
        ->set('message', str_repeat('a', 301))
        ->call('save')
        ->assertHasErrors(['message'])
        ->set('message', str_repeat('é', 300))
        ->set('reopenAt', 'demain')
        ->call('save')
        ->assertHasErrors(['reopenAt'])
        ->set('reopenAt', '')
        ->set('windowOn', true)
        ->set('windowStart', '20:00')
        ->set('windowEnd', '08:00')
        ->call('save')
        ->assertHasErrors(['windowEnd'])
        ->set('windowEnd', '22:00')
        ->call('save')
        ->assertHasNoErrors();

    expect(Settings::string('reg_closed_msg'))->toBe(str_repeat('é', 300))
        ->and(Settings::bool('reg_window_on'))->toBeTrue()
        ->and(Settings::string('reg_window_start'))->toBe('20:00');
});

it('refuse l’inscription hors de la plage horaire (heure du Bénin) avec le message configuré', function (): void {
    regScreen()
        ->set('message', 'Inscriptions ouvertes de 8 h à 20 h.')
        ->set('windowOn', true)
        ->set('windowStart', '08:00')
        ->set('windowEnd', '20:00')
        ->call('save')
        ->assertHasNoErrors();
    auth()->logout();

    // 10 h UTC = 11 h à Porto-Novo : ouvert.
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(10, 0));
    expect(RegistrationGate::isOpen())->toBeTrue();
    $this->get('/inscription')->assertOk()->assertDontSee('data-xm-registrations-closed', false);

    // 20 h 30 UTC = 21 h 30 au Bénin : fermé, message configuré.
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(20, 30));
    expect(RegistrationGate::isOpen())->toBeFalse();
    $this->get('/inscription')->assertOk()->assertSee('Inscriptions ouvertes de 8 h à 20 h.');
    expect(fn () => app(RegisterUser::class)(regData()))->toThrow(RegistrationsClosed::class);
    expect(User::query()->where('email', 'awa@exemple.bj')->exists())->toBeFalse();

    // 6 h 30 UTC = 7 h 30 au Bénin : pas encore ouvert (la plage est comptée en heure locale, pas en UTC).
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(6, 30));
    expect(RegistrationGate::isOpen())->toBeFalse();

    // 7 h UTC = 8 h au Bénin : ouvert.
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(7, 0));
    expect(RegistrationGate::isOpen())->toBeTrue();

    $this->travelBack();
});

it('annonce « hors plage » quand l’interrupteur est ouvert mais la plage fermée', function (): void {
    Settings::set('reg_window_on', true);
    Settings::set('reg_window_start', '08:00');
    Settings::set('reg_window_end', '20:00');
    $this->travelTo(now()->setDate(2026, 11, 2)->setTime(22, 0));

    regScreen()->assertSee('hors de la plage horaire')->assertSee('Fermées');

    $this->travelBack();
});

it('crée les comptes en attente quand la validation manuelle vise tous les nouveaux comptes (Q26)', function (): void {
    expect(RegistrationGate::manualValidation())->toBe('peers');

    $default = app(RegisterUser::class)(regData());
    expect($default->status)->toBe(UserStatus::Active);

    regScreen()->set('manual', 'all')->call('save')->assertHasNoErrors();
    expect(RegistrationGate::manualValidation())->toBe('all');

    $manual = app(RegisterUser::class)(regData(['pseudo' => 'Koffi_M', 'email' => 'koffi@exemple.bj']));
    expect($manual->status)->toBe(UserStatus::Pending);

    regScreen()->set('manual', 'peers')->call('save');
    expect(RegistrationGate::manualValidation())->toBe('peers');
});

it('refuse une valeur de validation manuelle inconnue', function (): void {
    regScreen()->set('manual', 'personne')->call('save')->assertHasErrors(['manual']);

    expect(Settings::string('reg_manual_validation', 'peers'))->toBe('peers');
});

it('liste les demandes en attente et les approuve ou les rejette', function (): void {
    Notification::fake();
    $peer = User::factory()->pending()->create(['pseudo' => 'fatou_p']);
    Profile::factory()->create(['user_id' => $peer->id, 'member_type' => MemberType::Peer]);
    $peer->syncRoles(['membre']);
    $member = User::factory()->pending()->create(['pseudo' => 'koffi_m']);
    $member->syncRoles(['membre']);

    $screen = regScreen()->assertSee('fatou_p')->assertSee('koffi_m');

    $screen->call('approve', $peer->id)->assertHasNoErrors();
    expect($peer->refresh()->status)->toBe(UserStatus::Active)->and($peer->hasRole('pair'))->toBeTrue();

    $screen->call('reject', $member->id)->assertHasNoErrors();
    expect(User::query()->find($member->id))->toBeNull()
        ->and(AuditLog::query()->where('action', 'users.registration_rejected')->exists())->toBeTrue();

    $screen->assertSee('Aucune demande en attente');
});

it('approuve une demande de membre en mode « tous » : compte actif et notification', function (): void {
    Notification::fake();
    $member = User::factory()->pending()->create();
    $member->syncRoles(['membre']);

    regScreen()->call('approve', $member->id);

    expect($member->refresh()->status)->toBe(UserStatus::Active)
        ->and(AuditLog::query()->where('action', 'users.registration_approved')->exists())->toBeTrue();
    Notification::assertSentTo($member, App\Notifications\ModerationNotice::class, fn ($n): bool => $n->kind === 'approved');
});

it('ne laisse pas le modérateur valider une demande ni appeler l’écran', function (): void {
    $member = User::factory()->pending()->create();
    $member->syncRoles(['membre']);
    $this->actingAs(regUser('moderateur'));

    Livewire::test(RegistrationsScreen::class)->assertForbidden();
    expect($member->refresh()->status)->toBe(UserStatus::Pending);
});

it('compte les inscriptions des huit dernières semaines au fuseau du Bénin', function (): void {
    $this->travelTo(now()->setDate(2026, 11, 4)->setTime(10, 0)); // mercredi
    User::factory()->count(2)->create(['created_at' => now()->subDays(1)]);
    User::factory()->create(['created_at' => now()->subWeeks(3)]);
    User::factory()->create(['created_at' => now()->subWeeks(12)]);

    $screen = regScreen();
    $weeks = $screen->instance()->weeks;

    expect($weeks)->toHaveCount(8)
        ->and(array_sum(array_column($weeks, 'count')))->toBe(3 + 1) // +1 : le compte de l’admin connecté
        ->and(max(array_column($weeks, 'share')))->toBe(100);

    $this->travelBack();
});

it('lit les réglages par défaut sans erreur', function (): void {
    $current = RegistrationSettings::current();

    expect($current['open'])->toBeTrue()->and($current['manual'])->toBe('peers')->and($current['window_on'])->toBeFalse();
});
