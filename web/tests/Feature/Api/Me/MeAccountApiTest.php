<?php

declare(strict_types=1);

use App\Enums\ConsentKind;
use App\Enums\SubscriberStatus;
use App\Models\AuditLog;
use App\Models\Consent;
use App\Models\NewsletterSubscriber;
use App\Models\Profile;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\UserSetting;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\NewsletterTaxonomySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/*
 * P6-06 (lot api-me) : me/profile (GET, PATCH interests[], ally_status, age_range), me/settings (GET, alias du
 * prototype mobile theme system, locale, sounds, notifications), me/security (discret, aperçus, PIN jamais transmis,
 * Q37), DELETE me (mot de passe, effacement différé, accès coupés, 403 pour l'administration). Chaque écriture est
 * relue par GET me.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class, NewsletterTaxonomySeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->withProfile(['ally_status' => null, 'interests' => null], ['theme' => 'light', 'mask_sensitive_notifications' => false])
        ->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'name' => 'Awa Sossou']);
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

function freshMeGuards(): void
{
    app('auth')->forgetGuards();
}

// --- me/profile ------------------------------------------------------------------------------------------------------

it('répond 401 sur chaque route me/* sans jeton', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', '/api/v1/me/profile'], ['GET', '/api/v1/me/settings'], ['GET', '/api/v1/me/security'], ['PATCH', '/api/v1/me/security'],
    ['DELETE', '/api/v1/me'], ['GET', '/api/v1/me/newsletter'], ['POST', '/api/v1/me/export'],
]);

it('enregistre centres d’intérêt, statut allié·e et tranche d’âge, puis les relit par GET me/profile et GET me', function (): void {
    $this->withToken($this->token)->patchJson('/api/v1/me/profile', [
        'interests' => ['sante', 'droits', 'sante'],
    ])->assertUnprocessable()->assertJsonValidationErrors('interests.0');

    $this->withToken($this->token)->patchJson('/api/v1/me/profile', [
        'interests' => ['sante', 'droits'],
        'ally_status' => 'ressource',
        'age_range' => '25-34',
    ])->assertOk()
        ->assertJsonPath('data.profile.interests', ['sante', 'droits'])
        ->assertJsonPath('data.profile.ally_status', 'ressource')
        ->assertJsonPath('data.profile.age_range', '25-34');

    $this->withToken($this->token)->getJson('/api/v1/me/profile')
        ->assertOk()
        ->assertJsonPath('data.interests', ['sante', 'droits'])
        ->assertJsonPath('data.ally_status', 'ressource')
        ->assertJsonMissingPath('data.gender_identity');

    $this->withToken($this->token)->getJson('/api/v1/me')->assertJsonPath('data.profile.ally_status', 'ressource');

    // Chiffré en base (Q10 c), jamais lisible en clair.
    $raw = DB::table('profiles')->where('user_id', $this->user->id)->value('ally_status');
    expect($raw)->not->toBe('ressource')->and(decrypt($raw, false))->toBe('ressource');

    // « aucun » efface le statut ; [] vide les centres d'intérêt ; un champ absent reste tel quel.
    $this->withToken($this->token)->patchJson('/api/v1/me/profile', ['ally_status' => 'aucun', 'interests' => []])
        ->assertOk()
        ->assertJsonPath('data.profile.ally_status', null)
        ->assertJsonPath('data.profile.interests', [])
        ->assertJsonPath('data.profile.age_range', '25-34');
});

it('refuse en 422 un centre d’intérêt inconnu, un statut allié·e ou une tranche d’âge hors liste', function (): void {
    $this->withToken($this->token)->patchJson('/api/v1/me/profile', ['interests' => ['astrologie'], 'ally_status' => 'admin', 'age_range' => '99'])
        ->assertUnprocessable()
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['interests.0', 'ally_status', 'age_range']);
});

it('ne montre jamais le statut allié·e ni les centres d’intérêt à un autre compte', function (): void {
    $this->user->profile->forceFill(['ally_status' => 'allie', 'interests' => ['sante']])->save();
    $other = User::factory()->withProfile()->create();

    $data = App\Http\Resources\UserResource::make($this->user->fresh(['profile', 'settings']))->resolve(request()->setUserResolver(fn () => $other));

    expect($data['profile'])->not->toHaveKey('ally_status')->not->toHaveKey('interests');
});

// --- me/settings -----------------------------------------------------------------------------------------------------

it('lit les réglages et accepte les alias du prototype mobile : system, locale, sounds, notifications', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/me/settings')
        ->assertOk()
        ->assertJsonPath('data.theme', 'light')
        ->assertJsonPath('data.sounds_enabled', true);

    $this->withToken($this->token)->patchJson('/api/v1/me/settings', [
        'theme' => 'system',
        'locale' => 'fr',
        'sounds' => false,
        'notifications' => false,
    ])->assertOk()
        ->assertJsonPath('data.settings.theme', 'auto')
        ->assertJsonPath('data.settings.language', 'fr')
        ->assertJsonPath('data.settings.sounds_enabled', false)
        ->assertJsonPath('data.settings.notification_prefs.messages.push', false)
        ->assertJsonPath('data.settings.notification_prefs.communaute.push', false)
        ->assertJsonPath('data.settings.notification_prefs.messages.in_app', true)
        // Sécurité du compte et SOS restent actifs quoi qu'il arrive.
        ->assertJsonPath('data.settings.notification_prefs.sos.push', true)
        ->assertJsonPath('data.settings.notification_prefs.securite.push', true);

    // Le détail par catégorie l'emporte sur l'interrupteur global envoyé dans la même requête.
    $this->withToken($this->token)->patchJson('/api/v1/me/settings', [
        'notifications' => false,
        'notification_prefs' => ['messages' => ['push' => true]],
    ])->assertOk()
        ->assertJsonPath('data.settings.notification_prefs.messages.push', true)
        ->assertJsonPath('data.settings.notification_prefs.evenements.push', false);

    $this->withToken($this->token)->getJson('/api/v1/me')
        ->assertJsonPath('data.settings.theme', 'auto')
        ->assertJsonPath('data.settings.sounds_enabled', false);

    // Relecture sous les noms d'écriture du prototype mobile (aucune reconversion côté client).
    $this->withToken($this->token)->getJson('/api/v1/me/settings')
        ->assertJsonPath('data.locale', 'fr')
        ->assertJsonPath('data.sounds', false)
        ->assertJsonPath('data.notifications', true);

    $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['notifications' => false])->assertOk();
    $this->withToken($this->token)->getJson('/api/v1/me/settings')->assertJsonPath('data.notifications', false);

    expect(AuditLog::query()->where('action', 'me.settings_updated')->latest('id')->value('detail'))->toContain('notifications');
});

it('ne laisse aucun cache garder les lectures sensibles : me, me/profile, me/security en no-store', function (string $uri): void {
    $response = $this->withToken($this->token)->getJson($uri)->assertOk();

    expect($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('ETag'))->toBeNull();
})->with(['/api/v1/me', '/api/v1/me/profile', '/api/v1/me/security']);

it('limite la langue à config(xomiton.locales) (Q40) : 422 pour une locale annoncée « bientôt »', function (): void {
    $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['locale' => 'fon'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['locale' => __('profile.settings.errors.locale_unavailable')]);

    $this->withToken($this->token)->patchJson('/api/v1/me/settings', ['language' => 'en', 'theme' => 'pastel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language', 'theme']);
});

// --- me/security -----------------------------------------------------------------------------------------------------

it('lit et modifie la sécurité ; le mode discret masque les aperçus et neutralise l’onglet ; relu par GET me', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/me/security')
        ->assertOk()
        ->assertExactJson(['data' => ['discreet' => false, 'mask_previews' => false, 'pin_enabled' => false, 'neutral_tab_title' => false]]);

    $this->withToken($this->token)->patchJson('/api/v1/me/security', ['discreet' => true, 'pin_enabled' => true])
        ->assertOk()
        ->assertExactJson(['data' => ['discreet' => true, 'mask_previews' => true, 'pin_enabled' => true, 'neutral_tab_title' => true]]);

    $this->withToken($this->token)->getJson('/api/v1/me')
        ->assertJsonPath('data.profile.discreet_mode', true)
        ->assertJsonPath('data.settings.mask_sensitive_notifications', true)
        ->assertJsonPath('data.settings.pin_enabled', true);

    // Désactiver le mode discret ne touche à rien d'autre ; un choix explicite l'emporte.
    $this->withToken($this->token)->patchJson('/api/v1/me/security', ['discreet' => false])
        ->assertOk()->assertJsonPath('data.mask_previews', true);
    $this->withToken($this->token)->patchJson('/api/v1/me/security', ['discreet' => true, 'mask_previews' => false])
        ->assertOk()->assertJsonPath('data.mask_previews', false)->assertJsonPath('data.neutral_tab_title', true);

    expect(AuditLog::query()->where('action', 'me.security_updated')->count())->toBe(3);
});

it('refuse tout code PIN transmis (Q37 b) et efface un pin_hash hérité', function (): void {
    $this->user->settings->forceFill(['pin_enabled' => true, 'pin_hash' => bcrypt('1234')])->save();

    $this->withToken($this->token)->patchJson('/api/v1/me/security', ['pin_enabled' => true, 'pin' => '1234'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['pin' => __('account.security.pin_never_sent')]);

    $this->withToken($this->token)->patchJson('/api/v1/me/security', ['pin_enabled' => false])->assertOk()->assertJsonPath('data.pin_enabled', false);

    expect(UserSetting::query()->where('user_id', $this->user->id)->value('pin_hash'))->toBeNull();
});

it('crée profil et réglages manquants (compte importé) au lieu d’échouer', function (): void {
    $bare = User::factory()->create();
    $token = $bare->createToken('Moto G')->plainTextToken;

    $this->withToken($token)->getJson('/api/v1/me/security')->assertOk()->assertJsonPath('data.discreet', false);
    $this->withToken($token)->patchJson('/api/v1/me/security', ['mask_previews' => true])->assertOk()->assertJsonPath('data.mask_previews', true);

    expect(Profile::query()->where('user_id', $bare->id)->exists())->toBeTrue()
        ->and(UserSetting::query()->where('user_id', $bare->id)->value('mask_sensitive_notifications'))->toBeTrue();
});

// --- DELETE me -------------------------------------------------------------------------------------------------------

it('supprime le compte (mot de passe exigé), coupe tous les accès et annonce l’effacement sous 30 jours', function (): void {
    $this->travelTo(now()->setDate(2026, 10, 18)->setTime(10, 0));
    PushSubscription::factory()->for($this->user)->create();
    $device = SyncDevice::factory()->for($this->user)->create();
    $this->user->createToken('Tablette');
    $subscriber = NewsletterSubscriber::factory()->create(['email' => 'awa@exemple.bj', 'name' => 'Awa', 'status' => SubscriberStatus::Active, 'user_id' => $this->user->id, 'confirmed_at' => now()]);

    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'mauvais'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password' => __('profile.settings.errors.current_password')]);

    $this->withToken($this->token)->deleteJson('/api/v1/me')->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'password'])
        ->assertOk()
        ->assertJsonPath('data.deleted', true)
        ->assertJsonPath('data.erased_from', now()->addDays(30)->toIso8601String())
        ->assertJsonPath('data.message', __('profile.settings.delete.done', ['days' => 30]));

    $user = User::withTrashed()->findOrFail($this->user->id);

    expect($user->trashed())->toBeTrue()
        ->and($user->tokens()->count())->toBe(0)
        ->and(PushSubscription::query()->where('user_id', $user->id)->count())->toBe(0)
        ->and($device->fresh()->is_revoked)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'account.deletion_requested')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(DB::table('mail_outbox')->where('to', 'awa@exemple.bj')->where('template_slug', 'compte-supprime')->exists())->toBeTrue();

    // Plus de newsletter : désabonnement tracé (consentement retiré) avec le motif « Compte supprimé ».
    $subscriber->refresh();
    expect($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and($subscriber->unsubscribe_reason)->toBe(__('account.deletion.newsletter_reason'))
        ->and(Consent::query()->where('user_id', $user->id)->where('kind', ConsentKind::Newsletter)->where('granted', false)->exists())->toBeTrue();

    freshMeGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertUnauthorized();

    // Après le délai de grâce, l'anonymisation existante efface le statut allié·e et les centres d'intérêt.
    $user->profile->forceFill(['ally_status' => 'allie', 'interests' => ['sante']])->saveQuietly();
    $this->travel(31)->days();
    $this->artisan('accounts:anonymize')->assertSuccessful();

    expect($user->profile->fresh()->ally_status)->toBeNull()->and($user->profile->fresh()->interests)->toBeNull();

    // L'adresse ne survit nulle part : ni la ligne newsletter (désabonnée, détachée), ni les consentements.
    $subscriber->refresh();
    expect($subscriber->email)->not->toContain('awa')
        ->and($subscriber->name)->toBeNull()
        ->and($subscriber->user_id)->toBeNull()
        ->and($subscriber->status)->toBe(SubscriberStatus::Unsubscribed)
        ->and(NewsletterSubscriber::query()->where('email', 'awa@exemple.bj')->exists())->toBeFalse()
        ->and(Consent::query()->where('email', 'awa@exemple.bj')->exists())->toBeFalse();
});

it('refuse en 403 la suppression d’un compte de l’équipe d’administration (y compris superadmin)', function (string $role): void {
    $admin = User::factory()->withProfile()->create();
    $admin->assignRole($role);
    $token = $admin->createToken('Poste')->plainTextToken;

    $this->withToken($token)->deleteJson('/api/v1/me', ['password' => 'password'])
        ->assertForbidden()
        ->assertJsonPath('code', 'forbidden');

    expect($admin->fresh()->trashed())->toBeFalse();
})->with(['admin', 'superadmin']);

it('limite les suppressions et exports par heure, par compte et par route', function (): void {
    config(['xomiton.rate_limits.account_sensitive_per_hour' => 2]);
    Illuminate\Support\Facades\Queue::fake();

    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'x'])->assertUnprocessable();
    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'x'])->assertUnprocessable();
    $this->withToken($this->token)->deleteJson('/api/v1/me', ['password' => 'password'])->assertStatus(429)->assertJsonPath('code', 'too_many_requests');

    // Des mots de passe erronés sur DELETE me ne bloquent pas l'export RGPD (compteur distinct).
    $this->withToken($this->token)->postJson('/api/v1/me/export')->assertAccepted();
    $this->withToken($this->token)->postJson('/api/v1/me/export')->assertAccepted();
    $this->withToken($this->token)->postJson('/api/v1/me/export')->assertStatus(429);

    expect($this->user->fresh()->trashed())->toBeFalse();
});
