<?php

declare(strict_types=1);

use App\Application\Member\MobileSpaces;
use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Laravel\Sanctum\PersonalAccessToken;
use Laravel\Sanctum\Sanctum;
use Tests\Support\FeedFixtures as F;

/*
 * Application mobile unique : durée de vie glissante du jeton selon l'espace (D-MOB-9), interrupteurs serveur par espace
 * (P15-22), délai d'expiration locale servi par `GET app/version` (P15-11).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
});

function tokenFor(User $user): array
{
    $token = $user->createToken('Pixel 7');

    return [$token->plainTextToken, $token->accessToken];
}

function bearer(string $plain): Illuminate\Foundation\Testing\TestCase
{
    // Le garde Sanctum garde l'utilisateur de la requête précédente : on l'oublie pour authentifier ce jeton-ci.
    app('auth')->forgetGuards();

    return test()->flushHeaders()->withToken($plain);
}

it('donne au jeton une durée de vie glissante : 7 jours pour l’admin, 30 pour le pair, aucune pour le membre', function (): void {
    $admin = F::member('admin');
    $pair = F::member('pair');
    $member = F::member('membre');

    foreach ([[$admin, 7], [$pair, 30], [$member, null]] as [$user, $days]) {
        [$plain, $token] = tokenFor($user);
        expect($token->expires_at)->toBeNull();

        bearer($plain)->getJson('/api/v1/me')->assertOk();

        $expires = $token->fresh()->expires_at;
        expect($days === null ? $expires : $expires?->isSameDay(now()->addDays($days)))->toBe($days === null ? null : true);
    }
});

it('repousse l’échéance au plus une fois par jour et refuse (401) un jeton resté inutilisé trop longtemps', function (): void {
    $admin = F::member('admin');
    [$plain, $token] = tokenFor($admin);

    bearer($plain)->getJson('/api/v1/me')->assertOk();
    $first = $token->fresh()->expires_at;

    $this->travel(2)->hours();
    bearer($plain)->getJson('/api/v1/me')->assertOk();
    expect($token->fresh()->expires_at->equalTo($first))->toBeTrue();

    $this->travel(3)->days();
    bearer($plain)->getJson('/api/v1/me')->assertOk();
    expect($token->fresh()->expires_at->gt($first))->toBeTrue();

    $this->travel(8)->days();
    bearer($plain)->getJson('/api/v1/me')->assertUnauthorized();
});

it('ramène un jeton déjà long à la durée de l’espace le plus sensible quand le compte reçoit l’espace Admin', function (): void {
    $user = F::member('pair');
    [$plain, $token] = tokenFor($user);
    bearer($plain)->getJson('/api/v1/me')->assertOk();
    expect($token->fresh()->expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    $user->syncRoles(['membre', 'pair', 'admin']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    bearer($plain)->getJson('/api/v1/me')->assertOk();

    expect($token->fresh()->expires_at->isSameDay(now()->addDays(7)))->toBeTrue();
});

it('ne touche pas à une session web (jeton transitoire)', function (): void {
    $admin = F::member('admin');
    Sanctum::actingAs($admin);
    test()->flushHeaders()->getJson('/api/v1/me')->assertOk();

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('sert le délai d’expiration locale et les espaces ouverts dans GET app/version', function (): void {
    test()->flushHeaders()->getJson('/api/v1/app/version')->assertOk()
        ->assertJsonPath('data.offline_expiry_days', 14)
        ->assertJsonPath('data.spaces_enabled', ['peer' => true, 'admin' => true]);

    config(['xomiton.accounts.offline_expiry_days' => 30]);
    test()->getJson('/api/v1/app/version')->assertJsonPath('data.offline_expiry_days', 30);
});

it('coupe l’espace Admin sans republier : retiré de GET me, admin/* répond 403 space_disabled, le site web et les autres espaces sont intacts', function (): void {
    $admin = F::member('admin');
    $admin->syncRoles(['membre', 'pair', 'admin']);
    [$plain] = tokenFor($admin);

    bearer($plain)->getJson('/api/v1/me')->assertJsonPath('data.spaces', ['member', 'peer', 'admin']);
    bearer($plain)->getJson('/api/v1/admin/dashboard')->assertOk();

    $this->artisan('xomiton:mobile-space', ['space' => 'admin', 'state' => 'off'])->assertSuccessful();

    bearer($plain)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.spaces', ['member', 'peer']);
    bearer($plain)->getJson('/api/v1/admin/dashboard')->assertForbidden()->assertJsonPath('code', 'space_disabled');
    bearer($plain)->getJson('/api/v1/pair/cases')->assertOk();
    test()->flushHeaders()->getJson('/api/v1/app/version')->assertJsonPath('data.spaces_enabled', ['peer' => true, 'admin' => false]);
    expect(AuditLog::query()->where('action', 'platform.mobile_space_switched')->latest('id')->first()->detail)->toBe('espace=admin ; état=coupé');

    // Le site web (session, pas de jeton Bearer) garde son administration.
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();

    $this->artisan('xomiton:mobile-space', ['space' => 'admin', 'state' => 'on'])->assertSuccessful();
    bearer($plain)->getJson('/api/v1/admin/dashboard')->assertOk();
});

it('coupe l’espace Pair pour l’application seulement : pair/* répond 403 space_disabled au jeton, la session web passe', function (): void {
    $pair = F::member('pair');
    [$plain] = tokenFor($pair);

    MobileSpaces::set('peer', false);

    bearer($plain)->getJson('/api/v1/me')->assertJsonPath('data.spaces', ['member']);
    bearer($plain)->getJson('/api/v1/pair/cases')->assertForbidden()->assertJsonPath('code', 'space_disabled');
    bearer($plain)->getJson('/api/v1/pair/validation-rules')->assertForbidden()->assertJsonPath('code', 'space_disabled');
    app('auth')->forgetGuards();
    Sanctum::actingAs($pair);
    test()->flushHeaders()->getJson('/api/v1/pair/cases')->assertOk();

    // L'espace Membre ne se coupe pas : SOS, urgences et fil restent servis.
    bearer($plain)->getJson('/api/v1/feed')->assertOk();
    test()->flushHeaders()->getJson('/api/v1/urgences')->assertOk();
});

it('valide les arguments de la commande et décrit l’état', function (): void {
    $this->artisan('xomiton:mobile-space', ['space' => 'membre', 'state' => 'off'])->assertExitCode(2);
    $this->artisan('xomiton:mobile-space', ['space' => 'admin', 'state' => 'peut-être'])->assertExitCode(2);
    $this->artisan('xomiton:mobile-space', ['space' => 'peer'])->expectsOutput("Espace peer\u{00A0}: ouvert.")->assertSuccessful();

    expect(MobileSpaces::all())->toBe(['peer' => true, 'admin' => true])
        ->and(MobileSpaces::enabled('member'))->toBeTrue();

    // Idempotent : couper deux fois ne journalise qu'une fois.
    MobileSpaces::set('admin', false);
    MobileSpaces::set('admin', false);
    expect(AuditLog::query()->where('action', 'platform.mobile_space_switched')->count())->toBe(1);
});
