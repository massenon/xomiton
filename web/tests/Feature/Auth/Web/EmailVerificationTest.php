<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureEmailIsVerified;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/*
 * Intégration phase 2 (Q12 c) : vérification d'e-mail différée et non bloquante — middleware `verified`
 * (App\Http\Middleware\EnsureEmailIsVerified), Gate 'verified' et bandeau « Confirme ton e-mail ».
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    Route::middleware(['web', 'auth', 'verified'])->get('/_test/publier', static fn () => 'publié')->name('_test.publier');
    Route::middleware(['api', 'auth:sanctum', 'verified'])->post('/api/v1/_test/publier', static fn () => ['ok' => true]);

    $this->unverified = User::factory()->create(['email_verified_at' => null]);
    $this->unverified->assignRole('membre');
    $this->verified = User::factory()->create(['email_verified_at' => now()]);
    $this->verified->assignRole('membre');
});

it('l’alias verified pointe sur le middleware maison et non sur celui de Laravel', function (): void {
    expect(app(Illuminate\Contracts\Http\Kernel::class)->getMiddlewareAliases()['verified'] ?? null)->toBe(EnsureEmailIsVerified::class);
});

it('refuse 403 « Confirme ton e-mail » tant que l’adresse n’est pas confirmée, puis laisse passer', function (): void {
    $this->actingAs($this->unverified)->get('/_test/publier')
        ->assertForbidden()
        ->assertSee(__('auth.email_not_verified'));

    $this->actingAs($this->verified)->get('/_test/publier')->assertOk()->assertSee('publié');
});

it('répond en JSON { message, code: email_unverified } sur l’API', function (): void {
    $this->actingAs($this->unverified, 'sanctum')->postJson('/api/v1/_test/publier')
        ->assertForbidden()
        ->assertJson(['message' => __('auth.email_not_verified'), 'code' => 'email_unverified']);

    $this->actingAs($this->verified, 'sanctum')->postJson('/api/v1/_test/publier')->assertOk()->assertJson(['ok' => true]);
});

it('expose la même règle par la Gate verified et User::hasVerifiedEmail()', function (): void {
    expect($this->unverified->hasVerifiedEmail())->toBeFalse()
        ->and($this->verified->hasVerifiedEmail())->toBeTrue()
        ->and(Gate::forUser($this->unverified)->allows('verified'))->toBeFalse()
        ->and(Gate::forUser($this->verified)->allows('verified'))->toBeTrue();
});

it('affiche le bandeau de rappel avec « Renvoyer le code » sur le fil tant que l’e-mail n’est pas confirmé', function (): void {
    $this->actingAs($this->unverified)->get(route('fil'))
        ->assertOk()
        ->assertSee(__('auth.verify_banner.title'))
        ->assertSee(__('auth.verify_banner.resend'))
        ->assertSee(route('otp.resend'), false)
        ->assertSee(route('otp', ['purpose' => 'signup']), false);

    $this->actingAs($this->verified)->get(route('fil'))
        ->assertOk()
        ->assertDontSee(__('auth.verify_banner.title'));
});

it('ne montre rien à un·e visiteur·se et laisse les pages publiques accessibles', function (): void {
    $this->get(route('home'))->assertOk()->assertDontSee(__('auth.verify_banner.title'));
});
