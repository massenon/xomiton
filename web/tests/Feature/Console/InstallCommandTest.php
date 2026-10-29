<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

/*
 * `xomiton:install` : seeders de base seulement + premier super-administrateur (GUIDE_DEPLOIEMENT.md §7).
 * Les tests tournent en environnement « production » simulé : la garde des comptes de démonstration est celle du code.
 */

beforeEach(function (): void {
    app()->detectEnvironment(fn (): string => 'production');
});

it('crée rôles, réglages et le premier super-administrateur sur une base vierge', function (): void {
    expect(Role::query()->count())->toBe(0)->and(User::query()->count())->toBe(0);

    $this->artisan('xomiton:install --admin-email=Premier@Example.org --generate-password')
        ->expectsOutputToContain('Diagnostic')
        ->expectsOutputToContain('XOMITON_LEGAL_PUBLISHER_NAME')
        ->assertSuccessful();

    expect(Role::query()->where('name', 'superadmin')->exists())->toBeTrue()
        ->and(Setting::query()->count())->toBeGreaterThan(0);

    $user = User::query()->where('email', 'premier@example.org')->firstOrFail();
    expect($user->hasRole(['membre', 'superadmin']))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->profile)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'install.first_admin')->where('subject_id', $user->id)->count())->toBe(1)
        ->and(User::query()->count())->toBe(1);
});

it('ne crée jamais de compte de démonstration, même hors production', function (): void {
    foreach (['production', 'local', 'staging', 'testing'] as $env) {
        app()->detectEnvironment(fn (): string => $env);
        $this->artisan('xomiton:install --no-admin')->assertSuccessful();
    }

    expect(User::query()->where('email', 'like', '%@xomiton.bj')->count())->toBe(0)
        ->and(User::query()->count())->toBe(0);
});

it('est idempotent : un second passage ne duplique rien et ne casse pas les réglages modifiés', function (): void {
    $this->artisan('xomiton:install --admin-email=a@example.org --generate-password')->assertSuccessful();

    $roles = Role::query()->count();
    $settings = Setting::query()->count();
    $first = Setting::query()->first();
    $first->value = 'modifié';
    $first->save();

    $this->artisan('xomiton:install')->assertSuccessful();
    $this->artisan('xomiton:install --no-admin')->assertSuccessful();

    expect(Role::query()->count())->toBe($roles)
        ->and(Setting::query()->count())->toBe($settings)
        ->and(User::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'install.first_admin')->count())->toBe(1);
});

it('refuse un second super-administrateur sans --force et l’accepte avec', function (): void {
    $this->artisan('xomiton:install --admin-email=a@example.org --generate-password')->assertSuccessful();

    $this->artisan('xomiton:install --admin-email=b@example.org --generate-password')
        ->expectsOutputToContain('existe déjà')
        ->assertFailed();
    expect(User::query()->where('email', 'b@example.org')->exists())->toBeFalse();

    $this->artisan('xomiton:install --admin-email=b@example.org --generate-password --force')->assertSuccessful();
    expect(User::role('superadmin')->count())->toBe(2);
});

it('refuse une adresse déjà utilisée ou invalide', function (): void {
    $this->artisan('xomiton:install --admin-email=pas-un-email --generate-password')->assertFailed();
    expect(User::query()->count())->toBe(0);
});

it('saisit un mot de passe masqué, le valide et ne l’écrit ni en sortie ni dans le journal', function (): void {
    Log::spy();
    $password = 'Secret-Tres-Long-42';

    $this->artisan('xomiton:install')
        ->expectsConfirmation('Créer maintenant le premier super-administrateur ?', 'yes')
        ->expectsQuestion('Adresse e-mail (réelle : elle recevra les codes de vérification)', 'saisi@example.org')
        ->expectsConfirmation('Générer un mot de passe aléatoire (sinon vous le saisissez, masqué) ?', 'no')
        ->expectsQuestion('Mot de passe (10 caractères minimum, majuscule, minuscule, chiffre)', $password)
        ->expectsQuestion('Confirmez le mot de passe', $password)
        ->doesntExpectOutputToContain($password)
        ->assertSuccessful();

    $user = User::query()->where('email', 'saisi@example.org')->firstOrFail();
    expect(Hash::check($password, $user->password))->toBeTrue();
    expect(AuditLog::query()->get()->map(fn ($l) => $l->detail.$l->action)->implode(' '))->not->toContain($password);
    Log::shouldNotHaveReceived('log', fn ($level, $message) => str_contains((string) $message, $password));
});

it('refuse un mot de passe faible saisi', function (): void {
    $this->artisan('xomiton:install')
        ->expectsConfirmation('Créer maintenant le premier super-administrateur ?', 'yes')
        ->expectsQuestion('Adresse e-mail (réelle : elle recevra les codes de vérification)', 'faible@example.org')
        ->expectsConfirmation('Générer un mot de passe aléatoire (sinon vous le saisissez, masqué) ?', 'no')
        ->expectsQuestion('Mot de passe (10 caractères minimum, majuscule, minuscule, chiffre)', 'court')
        ->expectsQuestion('Confirmez le mot de passe', 'court')
        ->assertFailed();

    expect(User::query()->count())->toBe(0);
});

it('affiche le mot de passe généré une seule fois et il est valide', function (): void {
    $shown = null;

    Illuminate\Support\Facades\Artisan::call('xomiton:install', ['--admin-email' => 'gen@example.org', '--generate-password' => true]);
    $output = Illuminate\Support\Facades\Artisan::output();
    preg_match('/^\s{2}(\S{24})$/m', $output, $m);
    $shown = $m[1] ?? null;

    expect($shown)->not->toBeNull()
        ->and(substr_count($output, (string) $shown))->toBe(1)
        ->and(Hash::check((string) $shown, User::query()->where('email', 'gen@example.org')->value('password')))->toBeTrue();
    expect(AuditLog::query()->get()->map(fn ($l) => (string) $l->detail)->implode(' '))->not->toContain((string) $shown);
});

it('--check n’écrit rien', function (): void {
    $this->artisan('xomiton:install --check')->assertFailed();

    expect(Role::query()->count())->toBe(0)->and(User::query()->count())->toBe(0);
});
