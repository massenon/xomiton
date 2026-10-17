<?php

declare(strict_types=1);

use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Commune;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Le garde `sanctum` garde en mémoire la personne résolue pendant le test : on l'oublie entre deux requêtes pour
 * rejouer l'authentification par jeton comme le ferait un vrai client.
 */
function freshGuards(): void
{
    app('auth')->forgetGuards();
}

/*
 * /api/v1/me (P2-06) : mon compte (jamais le nom réel), mon profil (phases 2-3), mes appareils connectés et leur révocation,
 * déconnexion du jeton courant ; 401 sans jeton.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);

    $this->user = User::factory()->withProfile()->create(['pseudo' => 'Awa_S', 'email' => 'awa@exemple.bj', 'name' => 'Awa Sossou']);
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

it('répond 401 JSON sans jeton, et 403 email_unverified pour une action réservée aux e-mails confirmés', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated')->assertJsonPath('message', __('auth.api.unauthenticated'));
    $this->get('/api/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    $this->getJson('/api/v1/inexistant')->assertNotFound()->assertJsonPath('code', 'not_found');

    Illuminate\Support\Facades\Route::middleware(['api', 'auth:sanctum'])->post('/api/v1/_test/publier', static function (): never {
        throw new App\Domain\Auth\Exceptions\EmailNotVerified;
    });

    $this->withToken($this->token)->postJson('/api/v1/_test/publier')
        ->assertForbidden()
        ->assertJsonPath('code', 'email_unverified')
        ->assertJsonPath('message', __('auth.email_not_verified'));
});

it('renvoie mon compte : pseudo, rôle, permissions, profil, réglages, e-mail confirmé, jamais le nom réel', function (): void {
    $response = $this->withToken($this->token)->getJson('/api/v1/me');

    $response->assertOk()
        ->assertJsonPath('data.pseudo', 'Awa_S')
        ->assertJsonPath('data.email', 'awa@exemple.bj')
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.role', 'membre')
        ->assertJsonPath('data.status', UserStatus::Active->value)
        ->assertJsonStructure(['data' => ['id', 'pseudo', 'avatar_url', 'roles', 'permissions', 'profile' => ['completion', 'member_type'], 'settings' => ['theme', 'language', 'notification_prefs']]])
        ->assertJsonMissingPath('data.name');

    expect($response->json('data.permissions'))->toContain('posts.create', 'social.access')->not->toContain('admin.access')
        ->and(json_encode($response->json()))->not->toContain('Sossou');
});

it('complète le profil (phases 2-3) et place un·e pair en attente ; 422 commune hors département ou téléphone invalide', function (): void {
    $zone = Zone::factory()->create();
    $commune = Commune::factory()->create(['zone_id' => $zone->id]);
    $elsewhere = Commune::factory()->create();

    $this->withToken($this->token)->patchJson('/api/v1/me/profile', ['zone_id' => $zone->id, 'commune_id' => $elsewhere->id, 'trusted_contact_phone' => '12'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['commune_id' => __('validation.commune_in_zone'), 'trusted_contact_phone' => __('validation.phone_bj')]);

    $this->withToken($this->token)->patchJson('/api/v1/me/profile', [
        'pronouns' => 'elle',
        'zone_id' => $zone->id,
        'commune_id' => $commune->id,
        'member_type' => 'pair',
        'discreet_mode' => true,
    ])
        ->assertOk()
        ->assertJsonPath('data.profile.pronouns', 'elle')
        ->assertJsonPath('data.profile.commune.id', $commune->id)
        ->assertJsonPath('data.profile.discreet_mode', true)
        ->assertJsonPath('data.status', UserStatus::Pending->value)
        ->assertJsonPath('data.role', 'pair');

    expect($this->user->fresh()?->hasRole('pair'))->toBeTrue();

    // La session API reste ouverte (RG-03 : seul l'accès à la collecte attend la validation).
    $this->withToken($this->token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.pending_validation', true);
});

it('liste mes appareils connectés (jetons et sessions web) et révoque un jeton ou une session, jamais ceux d’un autre compte', function (): void {
    $other = $this->user->createToken('Tablette')->plainTextToken;
    $stranger = User::factory()->create();
    $strangerToken = $stranger->createToken('Inconnu');
    DB::table('sessions')->insert(['id' => 'sess-web-1', 'user_id' => $this->user->id, 'ip_address' => '41.79.1.1', 'user_agent' => 'Firefox', 'payload' => '', 'last_activity' => time()]);
    DB::table('sessions')->insert(['id' => 'sess-autre', 'user_id' => $stranger->id, 'payload' => '', 'last_activity' => time()]);

    $list = $this->withToken($this->token)->getJson('/api/v1/me/sessions');
    $list->assertOk()->assertJsonPath('meta.total', 3)->assertJsonCount(3, 'data');

    $rows = collect($list->json('data'));
    $current = $rows->firstWhere('current', true);
    $web = $rows->firstWhere('kind', 'web');
    $tablet = $rows->firstWhere('name', 'Tablette');

    expect($current['name'])->toBe('Pixel 7')
        ->and($web['ip'])->toBe('41.79.1.1')
        ->and($web['id'])->toStartWith('web-')->not->toContain('sess-web-1')
        ->and($tablet['id'])->toStartWith('token-');

    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions/'.$tablet['id'])->assertOk()->assertJsonPath('data.revoked', true);
    freshGuards();
    $this->withToken($other)->getJson('/api/v1/me')->assertUnauthorized();
    freshGuards();

    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions/'.$web['id'])->assertOk();
    expect(DB::table('sessions')->where('id', 'sess-web-1')->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', 'sess-autre')->exists())->toBeTrue();

    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions/token-'.$strangerToken->accessToken->id)->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->withToken($this->token)->deleteJson('/api/v1/me/sessions/web-inconnue')->assertNotFound();
    expect($stranger->tokens()->count())->toBe(1)
        ->and(AuditLog::query()->ofAction('auth.session_revoked')->forSubject($this->user)->count())->toBe(2);
});

it('se déconnecte en révoquant le jeton courant seulement', function (): void {
    $other = $this->user->createToken('Tablette')->plainTextToken;

    $this->withToken($this->token)->postJson('/api/v1/auth/logout')->assertOk()->assertJsonPath('data.logged_out', true);

    freshGuards();
    $this->withToken($this->token)->getJson('/api/v1/me')->assertUnauthorized();
    freshGuards();
    $this->withToken($other)->getJson('/api/v1/me')->assertOk();
    freshGuards();
    $this->flushHeaders()->postJson('/api/v1/auth/logout')->assertUnauthorized();

    expect(AuditLog::query()->ofAction('auth.logout')->forSubject($this->user)->exists())->toBeTrue();
});
