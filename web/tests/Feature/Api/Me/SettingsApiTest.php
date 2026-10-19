<?php

declare(strict_types=1);

use App\Application\Auth\CompleteRegistrationProfile;
use App\Enums\ConsentKind;
use App\Enums\ProfileVisibility;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\Consent;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Zone;
use App\Rules\Pseudo;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/*
 * P8-09 (lot parametres-notifications) : API des sections de /parametres absentes de P6-06 — `PUT me/password`
 * (actuel vérifié, règles PasswordStrength, autres appareils déconnectés, e-mail `mot-de-passe-modifie` dans la boîte
 * d'envoi, session courante gardée sous un nouvel id, cookie « se souvenir de moi » reposé, limiteur), `GET / PATCH
 * me/privacy` (visibilité, zone, « Qui suivre », GPS au SOS, consentement aux données sensibles), `GET / PATCH
 * me/identity` (nom facultatif, pseudonyme réservé et délai, téléphone, zone, commune, présentation, complétion,
 * limiteur), `DELETE me/sessions` (tous les autres appareils).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->withProfile([], ['sos_share_gps' => false])
        ->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'name' => 'Awa Sossou']);
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

it('répond 401 sans jeton sur les nouvelles routes me/*', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with([
    ['PUT', '/api/v1/me/password'], ['GET', '/api/v1/me/privacy'], ['PATCH', '/api/v1/me/privacy'],
    ['GET', '/api/v1/me/identity'], ['PATCH', '/api/v1/me/identity'], ['DELETE', '/api/v1/me/sessions'],
]);

// --- me/password -----------------------------------------------------------------------------------------------------

it('refuse un mot de passe actuel faux, un nouveau trop faible, non confirmé ou identique', function (): void {
    $this->withToken($this->token)->putJson('/api/v1/me/password', [
        'current_password' => 'faux', 'password' => 'Nouveau-Mot2passe', 'password_confirmation' => 'Nouveau-Mot2passe',
    ])->assertUnprocessable()->assertJsonPath('errors.current_password.0', __('profile.settings.errors.current_password'));

    $this->withToken($this->token)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'court', 'password_confirmation' => 'court',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->withToken($this->token)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'Nouveau-Mot2passe', 'password_confirmation' => 'Autre-Mot2passe',
    ])->assertUnprocessable()
        ->assertJsonPath('errors.password_confirmation.0', __('profile.settings.errors.password_confirmation'))
        ->assertJsonMissingValidationErrors('password');

    $this->withToken($this->token)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'Nouveau-Mot2passe',
    ])->assertUnprocessable()->assertJsonPath('errors.password_confirmation.0', __('profile.settings.errors.password_confirmation_required'));

    expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue()
        ->and(DB::table('mail_outbox')->where('template_slug', 'mot-de-passe-modifie')->exists())->toBeFalse();
});

it('change le mot de passe, garde l’accès courant, ferme les autres et dépose l’e-mail dans la boîte d’envoi', function (): void {
    $other = $this->user->createToken('Ancien téléphone');
    PushSubscription::factory()->create(['user_id' => $this->user->id, 'token_id' => $other->accessToken->id]);

    $this->withToken($this->token)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'Nouveau-Mot2passe', 'password_confirmation' => 'Nouveau-Mot2passe',
    ])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.changed', true)
        ->assertJsonPath('data.revoked', 1);

    $user = $this->user->fresh();
    expect(Hash::check('Nouveau-Mot2passe', $user->password))->toBeTrue()
        ->and($user->tokens()->pluck('name')->all())->toBe(['Pixel 7'])
        ->and(PushSubscription::query()->where('token_id', $other->accessToken->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'auth.password_changed')->where('user_id', $user->id)->exists())->toBeTrue()
        ->and(DB::table('mail_outbox')->where('to', 'awa@exemple.bj')->where('template_slug', 'mot-de-passe-modifie')->exists())->toBeTrue();

    Mail::assertNothingSent();
    app('auth')->forgetGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk();
});

it('change le mot de passe depuis l’espace Vue (session) et ferme les jetons mobiles', function (): void {
    $this->actingAs($this->user)->putJson('/api/v1/me/password', [
        'current_password' => 'password', 'password' => 'Nouveau-Mot2passe!', 'password_confirmation' => 'Nouveau-Mot2passe!',
    ])->assertOk()->assertJsonPath('data.revoked', 1);

    expect($this->user->tokens()->count())->toBe(0);
});

it('garde la seule session courante, sous un nouvel identifiant, et repose le cookie « se souvenir de moi »', function (): void {
    config(['session.driver' => 'database']);
    $current = Str::random(40);
    DB::table('sessions')->insert([
        ['id' => $current, 'user_id' => $this->user->id, 'ip_address' => null, 'user_agent' => 'Firefox', 'payload' => base64_encode(serialize([])), 'last_activity' => time()],
        ['id' => 'autre-navigateur', 'user_id' => $this->user->id, 'ip_address' => null, 'user_agent' => 'Chrome', 'payload' => '', 'last_activity' => time()],
    ]);
    $guard = Auth::guard('web');
    $oldToken = (string) $this->user->remember_token;

    $response = $this->actingAs($this->user)
        ->withHeaders(['Referer' => 'http://localhost/parametres'])
        ->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->withCookie($guard->getRecallerName(), $this->user->id.'|'.$oldToken.'|x')
        ->putJson('/api/v1/me/password', [
            'current_password' => 'password', 'password' => 'Nouveau-Mot2passe', 'password_confirmation' => 'Nouveau-Mot2passe',
        ])->assertOk()->assertJsonPath('data.revoked', 2);

    $user = $this->user->fresh();
    $rows = DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all();
    expect($rows)->toHaveCount(1)
        ->and($rows[0])->not->toBe($current)
        ->and($rows[0])->not->toBe('autre-navigateur')
        ->and($user->remember_token)->not->toBe($oldToken);

    $response->assertCookie(config('session.cookie'), $rows[0])
        ->assertCookie($guard->getRecallerName(), $user->id.'|'.$user->remember_token.'|'.$guard->hashPasswordForCookie($user->password));
});

it('limite les changements de mot de passe (account_sensitive → 429)', function (): void {
    config(['xomiton.rate_limits.account_sensitive_per_hour' => 2]);
    $body = ['current_password' => 'faux', 'password' => 'Nouveau-Mot2passe', 'password_confirmation' => 'Nouveau-Mot2passe'];

    $this->withToken($this->token)->putJson('/api/v1/me/password', $body)->assertUnprocessable();
    $this->withToken($this->token)->putJson('/api/v1/me/password', $body)->assertUnprocessable();
    $this->withToken($this->token)->putJson('/api/v1/me/password', $body)->assertTooManyRequests();
});

// --- me/privacy ------------------------------------------------------------------------------------------------------

it('lit puis enregistre la confidentialité du profil et le consentement GPS', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/me/privacy')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data', ['visibility' => 'community', 'show_zone' => true, 'searchable' => true, 'sos_share_gps' => false, 'sensitive_consent' => false]);

    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', ['visibility' => 'secret'])
        ->assertUnprocessable()->assertJsonValidationErrors('visibility');

    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', [
        'visibility' => 'followers', 'show_zone' => false, 'searchable' => false, 'sos_share_gps' => true,
    ])->assertOk()
        ->assertJsonPath('data', ['visibility' => 'followers', 'show_zone' => false, 'searchable' => false, 'sos_share_gps' => true, 'sensitive_consent' => false]);

    $user = $this->user->fresh(['profile', 'settings']);
    expect($user->profile->visibility)->toBe(ProfileVisibility::Followers)
        ->and($user->profile->searchable)->toBeFalse()
        ->and($user->settings->sos_share_gps)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'me.settings_updated')->where('detail', 'like', 'section=confidentialite%')->exists())->toBeTrue();

    // Champ absent : laissé tel quel.
    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', ['show_zone' => true])
        ->assertOk()->assertJsonPath('data.visibility', 'followers')->assertJsonPath('data.show_zone', true);
});

it('donne puis retire le consentement aux données sensibles depuis la confidentialité (effacement, lignes sans IP)', function (): void {
    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', ['sensitive_consent' => true])
        ->assertOk()->assertJsonPath('data.sensitive_consent', true);
    $this->user->profile->forceFill(['gender_identity' => 'non_binaire', 'orientation' => 'lesbienne', 'shared_fields' => ['gender_identity']])->save();
    app('auth')->forgetGuards();

    // Même réponse : aucune nouvelle ligne.
    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', ['sensitive_consent' => true, 'show_zone' => false])->assertOk();
    expect(Consent::query()->where('user_id', $this->user->id)->ofKind(ConsentKind::SensitiveData)->count())->toBe(1);

    $this->withToken($this->token)->patchJson('/api/v1/me/privacy', ['sensitive_consent' => false])
        ->assertOk()->assertJsonPath('data.sensitive_consent', false);

    $profile = $this->user->profile->fresh();
    $rows = Consent::query()->where('user_id', $this->user->id)->ofKind(ConsentKind::SensitiveData)->orderBy('id')->get();
    expect($profile->gender_identity)->toBeNull()
        ->and($profile->orientation)->toBeNull()
        ->and($profile->shared_fields)->toBeNull()
        ->and($rows->pluck('granted')->map(fn ($granted): bool => (bool) $granted)->all())->toBe([true, false])
        ->and($rows->pluck('ip')->filter()->all())->toBe([])
        ->and($rows->pluck('user_agent')->filter()->all())->toBe([]);

    $this->withToken($this->token)->getJson('/api/v1/me/privacy')->assertJsonPath('data.sensitive_consent', false);
});

// --- me/identity -----------------------------------------------------------------------------------------------------

it('lit puis enregistre nom, pseudonyme, téléphone normalisé, zone, commune et présentation', function (): void {
    $zone = Zone::factory()->create(['name' => 'Littoral']);
    $commune = Commune::factory()->create(['zone_id' => $zone->id, 'name' => 'Cotonou']);
    $otherZone = Zone::factory()->create();

    $this->withToken($this->token)->getJson('/api/v1/me/identity')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.name', 'Awa Sossou')
        ->assertJsonPath('data.email', 'awa@exemple.bj');

    $this->withToken($this->token)->patchJson('/api/v1/me/identity', [
        'name' => 'Awa Mariam Sossou', 'pseudo' => 'awa.s', 'phone' => '01 97 38 88 47', 'bio' => '  Pair éducatrice.  ',
        'zone_id' => $zone->id, 'commune_id' => $commune->id,
    ])->assertOk()
        ->assertJsonPath('data.name', 'Awa Mariam Sossou')
        ->assertJsonPath('data.pseudo', 'awa.s')
        ->assertJsonPath('data.bio', 'Pair éducatrice.')
        ->assertJsonPath('data.zone_id', $zone->id)
        ->assertJsonPath('data.commune_id', $commune->id);

    $user = $this->user->fresh('profile');
    expect($user->phone)->toBe('+229 01 97 38 88 47')
        ->and($user->profile->bio)->toBe('Pair éducatrice.');

    // Commune d'un autre département, pseudonyme pris (casse ignorée), présentation trop longue, nom d'un caractère.
    User::factory()->create(['pseudo' => 'Nadia']);
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', [
        'zone_id' => $otherZone->id, 'commune_id' => $commune->id, 'pseudo' => 'NADIA', 'bio' => str_repeat('a', 201), 'name' => 'A',
    ])->assertUnprocessable()->assertJsonValidationErrors(['commune_id', 'pseudo', 'bio', 'name']);

    // Son propre pseudonyme (autre casse) reste accepté ; changer de département sans commune efface la commune.
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['pseudo' => 'AWA.S', 'zone_id' => $otherZone->id, 'phone' => null])
        ->assertOk()->assertJsonPath('data.commune_id', null)->assertJsonPath('data.phone', null);
});

it('enregistre le profil d’un compte créé sans nom (nom facultatif, vide = effacé)', function (): void {
    $user = User::factory()->withProfile(['bio' => null])->create(['name' => null, 'pseudo' => 'Sans_Nom']);
    $user->assignRole('membre');
    $zone = Zone::factory()->create();

    $this->actingAs($user)->patchJson('/api/v1/me/identity', ['name' => null, 'pseudo' => 'Sans_Nom', 'zone_id' => $zone->id, 'bio' => 'Bonjour'])
        ->assertOk()->assertJsonPath('data.name', null)->assertJsonPath('data.bio', 'Bonjour');

    $this->actingAs($this->user)->patchJson('/api/v1/me/identity', ['name' => ''])->assertOk()->assertJsonPath('data.name', null);
    expect($this->user->fresh()->name)->toBeNull();
});

it('recalcule le taux de complétion quand la zone, la commune ou la présentation changent', function (): void {
    $this->user->profile->forceFill(['bio' => null, 'pronouns' => null, 'avatar_path' => null, 'completion' => 0])->save();
    $this->user->forceFill(['zone_id' => null, 'commune_id' => null])->save();
    $zone = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id]);
    $before = CompleteRegistrationProfile::completion($this->user->fresh(), $this->user->profile->fresh());

    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['bio' => 'Pair éducatrice.', 'zone_id' => $zone->id, 'commune_id' => $commune->id])->assertOk();

    $user = $this->user->fresh('profile');
    expect($user->profile->completion)->toBeGreaterThan($before)
        ->and($user->profile->completion)->toBe(CompleteRegistrationProfile::completion($user, $user->profile))
        ->and($user->profile->progressive_answered)->toContain('location');
});

it('vérifie une commune seule contre le département actuel du compte', function (): void {
    $zone = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id]);
    $elsewhere = Commune::factory()->create(['zone_id' => Zone::factory()->create()->id]);
    $this->user->forceFill(['zone_id' => $zone->id, 'commune_id' => null])->save();

    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['commune_id' => $commune->id])
        ->assertOk()->assertJsonPath('data.zone_id', $zone->id)->assertJsonPath('data.commune_id', $commune->id);
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['commune_id' => $elsewhere->id])
        ->assertUnprocessable()->assertJsonValidationErrors('commune_id');
});

it('réserve l’ancien pseudonyme à son compte et impose un délai entre deux changements', function (): void {
    $other = User::factory()->withProfile()->create(['pseudo' => 'Nadia']);
    $other->assignRole('membre');

    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['pseudo' => 'Awa_Nouveau'])->assertOk();

    // Ni un autre compte ni une inscription ne reprennent l'ancien pseudonyme (casse ignorée).
    $this->actingAs($other)->patchJson('/api/v1/me/identity', ['pseudo' => 'awa_s'])
        ->assertUnprocessable()->assertJsonValidationErrors('pseudo');
    expect(Validator::make(['pseudo' => 'AWA_S'], ['pseudo' => [new Pseudo]])->fails())->toBeTrue();

    // Délai : pas de nouveau changement tout de suite (la casse seule reste permise).
    app('auth')->forgetGuards();
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['pseudo' => 'Awa_Encore'])
        ->assertUnprocessable()->assertJsonPath('errors.pseudo.0', __('profile.settings.errors.pseudo_cooldown', ['hours' => 24]));
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['pseudo' => 'AWA_NOUVEAU'])->assertOk();

    // Après le délai, la personne peut revenir à son ancien pseudonyme.
    $this->travel(25)->hours();
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['pseudo' => 'Awa_S'])->assertOk()->assertJsonPath('data.pseudo', 'Awa_S');
});

it('limite les enregistrements de la section « Profil » par compte (identity_update → 429)', function (): void {
    config(['xomiton.rate_limits.identity_update_per_hour' => 2]);

    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['bio' => 'Un'])->assertOk();
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['bio' => 'Deux'])->assertOk();
    $this->withToken($this->token)->patchJson('/api/v1/me/identity', ['bio' => 'Trois'])->assertTooManyRequests();
});

// --- me/sessions -----------------------------------------------------------------------------------------------------

it('liste les appareils d’un compte web sans jeton mobile (session courante signalée)', function (): void {
    config(['session.driver' => 'database']);
    $this->user->tokens()->delete();
    $current = Str::random(40);
    DB::table('sessions')->insert(['id' => $current, 'user_id' => $this->user->id, 'ip_address' => null, 'user_agent' => 'Firefox', 'payload' => base64_encode(serialize([])), 'last_activity' => time()]);

    $this->actingAs($this->user)->withHeaders(['Referer' => 'http://localhost/parametres'])->withCredentials()
        ->withCookie(config('session.cookie'), $current)
        ->getJson('/api/v1/me/sessions')
        ->assertOk()->assertJsonPath('data.0.kind', 'web')->assertJsonPath('data.0.current', true);
});

it('déconnecte tous les autres appareils, jamais l’accès courant', function (): void {
    $this->user->createToken('Tablette');
    $this->user->createToken('Ordinateur');

    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions')->assertOk()->assertJsonPath('data.revoked', 2);

    expect($this->user->tokens()->pluck('name')->all())->toBe(['Pixel 7'])
        ->and(AuditLog::query()->where('action', 'auth.sessions_revoked_others')->exists())->toBeTrue();
});
