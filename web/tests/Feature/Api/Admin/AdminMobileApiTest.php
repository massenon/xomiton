<?php

declare(strict_types=1);

use App\Application\Admin\Mobile\StepUp;
use App\Application\Platform\MaintenanceStatus;
use App\Enums\AlertPriority;
use App\Enums\AlertStatus;
use App\Enums\MemberType;
use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\DirectoryContact;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\Profile;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Notifications\RoleNotice;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\FeedFixtures as F;

/*
 * API admin mobile (P15-16, application mobile unique) : groupe `admin/*` — réservé aux comptes actifs détenant
 * `admin_mobile.access` (403 pour membre, pair, modérateur, jeton sans permission), mêmes cas d'usage que l'administration
 * Livewire, position arrondie, Idempotency-Key sur les écritures, confirmation `step-up` des actions à fort impact,
 * limiteur, journal d'audit `source=mobile`, non-escalade.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Notification::fake();
    $this->admin = F::member('admin');
    $this->admin->syncRoles(['membre', 'admin']);
    $this->super = F::member('superadmin');
});

function admCall(User $user, string $method, string $uri, array $body = [], bool $stepUp = false, ?string $key = null): Illuminate\Testing\TestResponse
{
    $headers = [];

    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
        $headers['Idempotency-Key'] = $key ?? (string) Str::uuid();
    }

    if ($stepUp) {
        $headers[StepUp::HEADER] = app(StepUp::class)->issue($user, 'password')['token'];
    }

    return F::as($user)->withHeaders($headers)->json($method, '/api/v1/admin/'.$uri, $body);
}

// --- Accès : permission, rôle, jeton -------------------------------------------------------------------------------------

it('refuse (403) le groupe admin à un membre, un pair, un modérateur et à un admin sans admin_mobile.access, sur chaque route', function (): void {
    $alert = Alert::factory()->sos()->create();
    $routes = [
        ['GET', 'dashboard'], ['POST', 'step-up'], ['GET', 'alerts'], ['GET', 'alerts/'.$alert->ulid], ['POST', 'alerts/'.$alert->ulid.'/acknowledge'],
        ['POST', 'alerts/'.$alert->ulid.'/take'], ['POST', 'alerts/'.$alert->ulid.'/status'], ['POST', 'alerts/'.$alert->ulid.'/close'],
        ['POST', 'alerts/'.$alert->ulid.'/reassign'], ['POST', 'alerts/'.$alert->ulid.'/notes'], ['GET', 'registrations'],
        ['POST', 'registrations/1/approve'], ['POST', 'registrations/1/reject'], ['GET', 'members?q=aa'], ['GET', 'members/1'],
        ['POST', 'members/1/roles/peer'], ['DELETE', 'members/1/roles/peer'], ['PUT', 'members/1/role'], ['GET', 'invitations'],
        ['POST', 'invitations'], ['DELETE', 'invitations/1'], ['GET', 'maintenance'], ['POST', 'maintenance/enable'], ['POST', 'maintenance/disable'],
        ['GET', 'moderation/reports'], ['POST', 'moderation/reports/1/hide'], ['POST', 'moderation/reports/1/restore'], ['POST', 'moderation/reports/1/dismiss'],
        ['GET', 'notifications'], ['POST', 'notifications/read-all'],
    ];

    $stripped = F::member('admin');
    $stripped->syncRoles(['membre', 'admin']);
    $stripped->givePermissionTo([]);
    $stripped->roles->first(fn ($r) => $r->name === 'admin')?->revokePermissionTo('admin_mobile.access');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ([F::member('membre'), F::member('pair'), F::member('moderateur'), $stripped] as $denied) {
        foreach ($routes as [$method, $uri]) {
            admCall($denied, $method, $uri)->assertForbidden()->assertJsonPath('code', 'forbidden');
        }
    }

    // Rôle rétabli pour la suite : la permission est relue à chaque requête.
    Spatie\Permission\Models\Role::findByName('admin', 'web')->givePermissionTo('admin_mobile.access');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    admCall($this->admin, 'GET', 'dashboard')->assertOk();
});

it('refuse l’anonyme (401) et un admin suspendu (403), et ne fige pas de capacité dans le jeton', function (): void {
    $this->getJson('/api/v1/admin/dashboard')->assertUnauthorized();

    $suspended = F::member('admin');
    $suspended->update(['status' => UserStatus::Suspended]);
    admCall($suspended, 'GET', 'dashboard')->assertForbidden();

    // Un jeton émis AVANT l'attribution du rôle fonctionne dès que le rôle est donné (rôles cumulables, permission lue en direct).
    $member = F::member('membre');
    $token = $member->createToken('Pixel 7')->plainTextToken;
    $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertForbidden();
    $member->syncRoles(['membre', 'admin']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/api/v1/admin/dashboard')->assertOk();
});

it('sert des réponses no-store et applique le limiteur admin_api par compte', function (): void {
    admCall($this->admin, 'GET', 'dashboard')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

    config(['xomiton.rate_limits.admin_api_per_minute' => 2, 'xomiton.rate_limits.api_per_minute' => 1000]);
    admCall($this->admin, 'GET', 'dashboard')->assertOk();
    admCall($this->admin, 'GET', 'dashboard')->assertStatus(429);
    // Un autre compte n'est pas touché.
    admCall($this->super, 'GET', 'dashboard')->assertOk();
});

it('expose les espaces disponibles dans GET me : member, peer, admin selon les rôles cumulés', function (): void {
    $both = F::member('admin');
    $both->syncRoles(['membre', 'pair', 'admin']);

    F::as($both)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.spaces', ['member', 'peer', 'admin'])->assertJsonPath('data.role', 'admin');
    F::as(F::member('pair'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.spaces', ['member', 'peer']);
    F::as(F::member('membre'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.spaces', ['member']);
    F::as($this->admin)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.spaces', ['member', 'admin']);
});

// --- Tableau d'astreinte ----------------------------------------------------------------------------------------------------

it('compte alertes, SOS sans accusé, comptes en attente, modération et maintenance dans le tableau d’astreinte', function (): void {
    Alert::factory()->sos()->create();
    Alert::factory()->sos()->acknowledged()->create();
    Alert::factory()->priority(AlertPriority::Critical)->create();
    User::factory()->create(['status' => UserStatus::Pending]);
    ModerationReport::factory()->forSubject(Post::factory()->create())->create();

    $data = admCall($this->admin, 'GET', 'dashboard')->assertOk()->json('data');

    expect($data['alerts']['new'])->toBe(2)
        ->and($data['alerts']['sos_active'])->toBe(2)
        ->and($data['alerts']['sos_unacknowledged'])->toBe(1)
        ->and($data['pending_accounts'])->toBe(1)
        ->and($data['pending_moderation'])->toBe(1)
        ->and($data['maintenance'])->toMatchArray(['active' => false, 'state' => 'inactive'])
        ->and($data)->toHaveKeys(['on_call', 'poll_seconds', 'server_time']);
});

// --- Alertes -----------------------------------------------------------------------------------------------------------------

it('liste les alertes filtrables sans récit, contact ni position', function (): void {
    $sos = Alert::factory()->sos()->located(6.370312, 2.391234)->withContact('+229 01 97 38 88 47')->create(['description' => 'Menaces répétées devant chez moi.']);
    $report = Alert::factory()->create(['type' => 'denonciation']);
    $closed = Alert::factory()->withStatus(AlertStatus::Closed)->create();

    $response = admCall($this->admin, 'GET', 'alerts')->assertOk();
    $ids = array_column($response->json('data'), 'id');

    expect($ids)->toContain($sos->ulid, $report->ulid)->not->toContain($closed->ulid)
        ->and($response->json('data.0'))->not->toHaveKeys(['description', 'contact', 'position', 'tracking_code'])
        ->and($response->getContent())->not->toContain('Menaces répétées')->not->toContain('6.37')->not->toContain('01 97 38 88 47');

    expect(array_column(admCall($this->admin, 'GET', 'alerts?type=sos')->json('data'), 'id'))->toBe([$sos->ulid])
        ->and(array_column(admCall($this->admin, 'GET', 'alerts?type=report')->json('data'), 'id'))->toBe([$report->ulid])
        ->and(array_column(admCall($this->admin, 'GET', 'alerts?state=all&status=clos')->json('data'), 'id'))->toBe([$closed->ulid]);

    admCall($this->admin, 'GET', 'alerts?type=autre')->assertStatus(422);
});

it('détaille une alerte avec la position ARRONDIE, jamais exacte, et le récit', function (): void {
    $alert = Alert::factory()->sos()->located(6.370312, 2.391234)->withContact('+229 01 97 38 88 47')
        ->create(['description' => 'Menaces répétées devant chez moi.']);

    $response = admCall($this->admin, 'GET', 'alerts/'.$alert->ulid)->assertOk()
        ->assertJsonPath('data.description', 'Menaces répétées devant chez moi.')
        ->assertJsonPath('data.contact', '+229 01 97 38 88 47')
        ->assertJsonPath('data.position.lat', 6.37)
        ->assertJsonPath('data.position.lng', 2.39)
        ->assertJsonPath('data.position.rounded', true)
        ->assertJsonPath('data.can.acknowledge', true);

    expect($response->getContent())->not->toContain('6.3703')->not->toContain('2.3912')
        ->and($response->json('data'))->not->toHaveKeys(['tracking_code', 'idempotency_key', 'user_id', 'lat', 'lng']);

    config(['xomiton.alerts.admin_position_decimals' => 1]);
    admCall($this->admin, 'GET', 'alerts/'.$alert->ulid)->assertJsonPath('data.position.lat', 6.4);

    admCall($this->admin, 'GET', 'alerts/01ARZ3NDEKTSV4RRFFQ69G5FAV')->assertNotFound();
});

it('accuse réception, prend en charge et clôt une alerte : mêmes règles que le web, audit source=mobile', function (): void {
    $alert = Alert::factory()->sos()->create();

    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/acknowledge')->assertOk()
        ->assertJsonPath('meta.acknowledged_now', true)
        ->assertJsonPath('data.acknowledged', true);

    expect($alert->fresh()->acknowledged_by)->toBe($this->admin->id)
        ->and(AuditLog::query()->where('action', 'alerts.acknowledged')->first()->detail)->toContain('source=mobile');

    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/acknowledge')->assertOk()->assertJsonPath('meta.already_acknowledged', true);

    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/take')->assertOk()->assertJsonPath('data.status', 'en_cours');
    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/status', ['status' => 'traite'])->assertOk()->assertJsonPath('data.status', 'traite');
    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/status', ['status' => 'nouveau'])->assertStatus(422);
    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/close')->assertOk()->assertJsonPath('data.status', 'clos');
    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/close')->assertStatus(422);

    expect(AuditLog::query()->where('action', 'alerts.status_changed')->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'alerts.status_changed')->get()->every(fn ($log) => str_contains((string) $log->detail, 'source=mobile')))->toBeTrue();
});

it('exige Idempotency-Key sur les écritures et rejoue la réponse d’origine sans second effet', function (): void {
    $alert = Alert::factory()->sos()->create();

    F::as($this->admin)->postJson('/api/v1/admin/alerts/'.$alert->ulid.'/notes', ['body' => 'Appel passé'])
        ->assertStatus(422)->assertJsonPath('code', 'idempotency_key_required');

    $key = (string) Str::uuid();
    $first = admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/notes', ['body' => 'Appel passé à 14 h.'], key: $key)->assertCreated();
    $replay = admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/notes', ['body' => 'Appel passé à 14 h.'], key: $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json('data.notes'))->toBe($first->json('data.notes'))
        ->and($alert->notes()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'alerts.note_added')->count())->toBe(1);

    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/notes', ['body' => 'Autre contenu'], key: $key)->assertStatus(422)->assertJsonPath('code', 'idempotency_key_reused');
    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/notes', ['body' => ''])->assertStatus(422);
});

it('réattribue une alerte seulement avec la confirmation step-up, à usage unique et propre au compte', function (): void {
    $alert = Alert::factory()->sos()->create();
    $contact = DirectoryContact::factory()->create();

    admCall($this->admin, 'POST', 'alerts/'.$alert->ulid.'/reassign', ['contact_id' => $contact->id])
        ->assertForbidden()->assertJsonPath('code', 'step_up_required');
    expect($alert->fresh()->routed_to)->not->toBe($contact->id);

    $token = app(StepUp::class)->issue($this->admin, 'password')['token'];
    F::as($this->admin)->withHeaders(['Idempotency-Key' => (string) Str::uuid(), StepUp::HEADER => $token])
        ->postJson('/api/v1/admin/alerts/'.$alert->ulid.'/reassign', ['contact_id' => $contact->id])->assertOk()->assertJsonPath('data.routed_to.id', $contact->id);
    expect($alert->fresh()->routed_to)->toBe($contact->id);

    // Jeton déjà consommé, puis jeton d'un autre compte : refusés.
    F::as($this->admin)->withHeaders(['Idempotency-Key' => (string) Str::uuid(), StepUp::HEADER => $token])
        ->postJson('/api/v1/admin/alerts/'.$alert->ulid.'/reassign', ['contact_id' => $contact->id])->assertForbidden()->assertJsonPath('code', 'step_up_required');
    $foreign = app(StepUp::class)->issue($this->super, 'password')['token'];
    F::as($this->admin)->withHeaders(['Idempotency-Key' => (string) Str::uuid(), StepUp::HEADER => $foreign])
        ->postJson('/api/v1/admin/alerts/'.$alert->ulid.'/reassign', ['contact_id' => $contact->id])->assertForbidden();

    admCall($this->admin, 'GET', 'alerts/'.$alert->ulid.'/contacts')->assertOk();
});

it('délivre le jeton de confirmation contre le mot de passe, avec un plafond d’essais', function (): void {
    admCall($this->admin, 'POST', 'step-up', ['password' => 'mauvais'])->assertStatus(422)->assertJsonValidationErrors('password');
    $ok = admCall($this->admin, 'POST', 'step-up', ['password' => 'password'])->assertOk();
    expect($ok->json('data.token'))->toBeString()->and($ok->json('data.expires_in'))->toBe(300)->and($ok->json('data.header'))->toBe('X-Step-Up-Token');

    config(['xomiton.rate_limits.admin_step_up_per_hour' => 2]);
    admCall($this->super, 'POST', 'step-up', ['password' => 'x']);
    admCall($this->super, 'POST', 'step-up', ['password' => 'x']);
    admCall($this->super, 'POST', 'step-up', ['password' => 'password'])->assertStatus(429);
});

// --- Inscriptions et pairs en attente ----------------------------------------------------------------------------------------

it('liste les comptes en attente, valide une demande de pair (rôle pair + membre) et notifie', function (): void {
    $applicant = User::factory()->create(['status' => UserStatus::Pending]);
    $applicant->syncRoles(['membre']);
    Profile::factory()->create(['user_id' => $applicant->id, 'member_type' => MemberType::Peer]);
    $plain = User::factory()->create(['status' => UserStatus::Pending]);
    $plain->syncRoles(['membre']);

    $list = admCall($this->admin, 'GET', 'registrations')->assertOk()->assertJsonPath('meta.pending', 2);
    $kinds = collect($list->json('data'))->pluck('kind', 'id');
    expect($kinds[$applicant->id])->toBe('peer_request')->and($kinds[$plain->id])->toBe('registration');

    admCall($this->admin, 'POST', 'registrations/'.$applicant->id.'/approve')->assertOk()->assertJsonPath('data.status', 'actif')->assertJsonPath('data.is_peer', true);

    expect($applicant->fresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair']);
    admCall($this->admin, 'POST', 'registrations/'.$applicant->id.'/approve')->assertStatus(422);
});

it('refuse une demande de pair avec un motif et la confirmation : le compte reste membre, la personne est prévenue', function (): void {
    $applicant = User::factory()->create(['status' => UserStatus::Pending]);
    $applicant->syncRoles(['membre']);
    Profile::factory()->create(['user_id' => $applicant->id, 'member_type' => MemberType::Peer]);

    admCall($this->admin, 'POST', 'registrations/'.$applicant->id.'/reject', ['reason' => 'Formation non validée'])->assertForbidden()->assertJsonPath('code', 'step_up_required');
    admCall($this->admin, 'POST', 'registrations/'.$applicant->id.'/reject', ['reason' => ''], stepUp: true)->assertStatus(422);
    admCall($this->admin, 'POST', 'registrations/'.$applicant->id.'/reject', ['reason' => 'Formation non validée'], stepUp: true)->assertOk()->assertJsonPath('data.rejected', true);

    expect($applicant->fresh()->status)->toBe(UserStatus::Active)->and($applicant->fresh()->hasRole('pair'))->toBeFalse();
    Notification::assertSentTo($applicant, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_REFUSED && $n->reason === 'Formation non validée');
    expect(AuditLog::query()->where('action', 'users.peer_refused')->first()->detail)->toContain('Formation non validée')->toContain('source=mobile');
});

// --- Recherche d'un membre, rôles ---------------------------------------------------------------------------------------------

it('cherche un membre par pseudonyme (e-mail pour users.manage) sans jamais exposer d’autre donnée personnelle', function (): void {
    $target = User::factory()->create(['pseudo' => 'koffi_m', 'email' => 'koffi@exemple.bj', 'name' => 'Koffi Mensah']);
    $target->syncRoles(['membre']);
    User::factory()->create(['pseudo' => 'awa_s'])->syncRoles(['membre']);

    admCall($this->admin, 'GET', 'members?q=k')->assertStatus(422);
    $byPseudo = admCall($this->admin, 'GET', 'members?q=koffi')->assertOk();
    $byEmail = admCall($this->admin, 'GET', 'members?q=koffi%40exemple')->assertOk();

    expect(array_column($byPseudo->json('data'), 'pseudo'))->toBe(['koffi_m'])
        ->and(array_column($byEmail->json('data'), 'pseudo'))->toBe(['koffi_m'])
        ->and($byPseudo->json('data.0.email'))->toBe('koffi@exemple.bj')
        ->and($byPseudo->json('data.0'))->not->toHaveKeys(['name', 'password', 'trusted_contact_phone']);

    admCall($this->admin, 'GET', 'members/'.$target->id)->assertOk()->assertJsonPath('data.pseudo', 'koffi_m')->assertJsonPath('data.can.grant_peer', true);
});

it('donne puis retire le rôle pair avec confirmation ; le membre reste membre et est prévenu', function (): void {
    $target = User::factory()->withProfile()->create();
    $target->syncRoles(['membre']);

    admCall($this->admin, 'POST', 'members/'.$target->id.'/roles/peer')->assertForbidden()->assertJsonPath('code', 'step_up_required');
    admCall($this->admin, 'POST', 'members/'.$target->id.'/roles/peer', stepUp: true)->assertCreated()
        ->assertJsonPath('data.is_peer', true)->assertJsonPath('data.roles', ['membre', 'pair'])->assertJsonPath('data.spaces', ['member', 'peer']);
    Notification::assertSentTo($target, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_GRANTED);

    admCall($this->admin, 'DELETE', 'members/'.$target->id.'/roles/peer', ['reason' => 'Fin de mission'], stepUp: true)->assertOk()
        ->assertJsonPath('data.roles', ['membre'])->assertJsonPath('meta.changed', true);
    expect(AuditLog::query()->whereIn('action', ['roles.peer_granted', 'roles.peer_revoked'])->count())->toBe(2);
});

it('empêche l’escalade : un admin ne se donne ni ne donne un rôle d’administration, un pair ou un membre non plus', function (): void {
    $target = User::factory()->withProfile()->create();
    $target->syncRoles(['membre', 'pair']);

    admCall($this->admin, 'PUT', 'members/'.$target->id.'/role', ['role' => 'admin'], stepUp: true)->assertForbidden();
    admCall($this->admin, 'PUT', 'members/'.$this->admin->id.'/role', ['role' => 'superadmin'], stepUp: true)->assertStatus(422);
    expect($target->fresh()->hasRole('admin'))->toBeFalse();

    // Pair et membre : jamais d'accès à l'API admin, même pour s'attribuer un rôle.
    $peer = F::member('pair');
    admCall($peer, 'PUT', 'members/'.$peer->id.'/role', ['role' => 'superadmin'], stepUp: true)->assertForbidden();
    admCall($peer, 'POST', 'members/'.$peer->id.'/roles/peer')->assertForbidden();
    expect($peer->fresh()->hasRole('superadmin'))->toBeFalse();

    admCall($this->super, 'PUT', 'members/'.$target->id.'/role', ['role' => 'admin'], stepUp: true)->assertOk()
        ->assertJsonPath('data.roles', ['admin', 'membre', 'pair']);
});

// --- Invitations ----------------------------------------------------------------------------------------------------------------

it('crée une invitation pair (code et lien rendus une seule fois), la liste sans le code et l’annule', function (): void {
    admCall($this->admin, 'POST', 'invitations', ['role' => 'pair'])->assertForbidden()->assertJsonPath('code', 'step_up_required');

    $created = admCall($this->admin, 'POST', 'invitations', ['role' => 'pair', 'email' => 'nouvelle@exemple.bj', 'note' => 'Atelier'], stepUp: true)->assertCreated()
        ->assertJsonPath('data.role', 'pair')->assertJsonPath('data.status', 'pending');
    $code = $created->json('data.code');
    expect($code)->toStartWith('XM-')->and($created->json('data.link'))->toContain('/inscription?');

    $list = admCall($this->admin, 'GET', 'invitations')->assertOk();
    expect($list->getContent())->not->toContain($code)->and($list->json('data.0.code_hint'))->toBe(substr(str_replace('-', '', $code), -4));

    $id = $created->json('data.id');
    admCall($this->admin, 'DELETE', 'invitations/'.$id)->assertForbidden();
    admCall($this->admin, 'DELETE', 'invitations/'.$id, stepUp: true)->assertNoContent();
    expect(RoleInvitation::query()->find($id)->status)->toBe(RoleInvitation::STATUS_REVOKED);
});

it('réserve l’invitation d’un·e admin au super-admin', function (): void {
    admCall($this->admin, 'POST', 'invitations', ['role' => 'admin'], stepUp: true)->assertForbidden();
    admCall($this->admin, 'POST', 'invitations', ['role' => 'chef'], stepUp: true)->assertStatus(422);
    admCall($this->super, 'POST', 'invitations', ['role' => 'admin'], stepUp: true)->assertCreated()->assertJsonPath('data.role', 'admin');

    // L'admin ne voit pas les invitations d'administration.
    expect(array_column(admCall($this->admin, 'GET', 'invitations')->json('data'), 'role'))->not->toContain('admin');
    expect(array_column(admCall($this->super, 'GET', 'invitations')->json('data'), 'role'))->toContain('admin');
});

// --- Maintenance -------------------------------------------------------------------------------------------------------------------

it('active et désactive la maintenance avec confirmation, sans réactiver une maintenance déjà active', function (): void {
    admCall($this->admin, 'GET', 'maintenance')->assertOk()->assertJsonPath('data.state', 'inactive');

    $body = ['reason' => 'Mise à jour de la base', 'duration_minutes' => 30, 'confirm' => true];
    admCall($this->admin, 'POST', 'maintenance/enable', $body)->assertForbidden()->assertJsonPath('code', 'step_up_required');
    admCall($this->admin, 'POST', 'maintenance/enable', ['confirm' => false] + $body, stepUp: true)->assertStatus(422);
    admCall($this->admin, 'POST', 'maintenance/enable', $body, stepUp: true)->assertOk()->assertJsonPath('data.active', true)->assertJsonPath('data.state', 'ongoing');
    expect(MaintenanceStatus::active())->toBeTrue();

    // L'API admin reste servie pendant la maintenance (admin exempté), la réactivation est refusée.
    admCall($this->admin, 'POST', 'maintenance/enable', $body, stepUp: true)->assertStatus(422);
    admCall($this->admin, 'GET', 'maintenance')->assertOk()->assertJsonPath('data.active', true);

    admCall($this->admin, 'POST', 'maintenance/disable', ['confirm' => true], stepUp: true)->assertOk()->assertJsonPath('data.active', false);
    expect(MaintenanceStatus::active())->toBeFalse()
        ->and(AuditLog::query()->whereIn('action', ['maintenance.enabled', 'maintenance.disabled'])->get()->every(fn ($l) => str_contains((string) $l->detail, 'source=mobile')))->toBeTrue();
});

// --- Modération -----------------------------------------------------------------------------------------------------------------------

it('liste la file de modération, masque, restaure et ignore avec les mêmes règles que le web', function (): void {
    $author = F::member('membre');
    $post = Post::factory()->create(['user_id' => $author->id, 'body' => 'Un propos à relire']);
    $report = ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $author->id, 'reason' => 'Insultes', 'excerpt' => 'Un propos à relire']);
    ModerationReport::factory()->forSubject($post)->create(['reason' => 'Spam']);
    $comment = Comment::factory()->create();
    $other = ModerationReport::factory()->forSubject($comment)->create();

    $list = admCall($this->admin, 'GET', 'moderation/reports')->assertOk()->assertJsonPath('meta.pending', 2);
    $first = collect($list->json('data'))->firstWhere('id', $report->id);
    expect($first['reports'])->toBe(2)->and($first['can']['hide'])->toBeTrue()
        ->and($first['reported_user']['pseudo'])->toBe($author->pseudo)
        ->and($list->getContent())->not->toContain('reporter');

    admCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/hide', ['reason' => 'Insultes'])->assertOk()->assertJsonPath('meta.closed', 2)->assertJsonPath('data.hidden', true);
    expect($post->fresh()->status)->toBe(PostStatus::Hidden);

    admCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/restore')->assertOk()->assertJsonPath('data.hidden', false);
    expect($post->fresh()->status)->toBe(PostStatus::Published);

    admCall($this->admin, 'POST', 'moderation/reports/'.$other->id.'/dismiss')->assertOk()->assertJsonPath('data.status', 'rejete');
    admCall($this->admin, 'POST', 'moderation/reports/'.$other->id.'/dismiss')->assertStatus(422);
    admCall($this->admin, 'GET', 'moderation/reports?state=bidule')->assertStatus(422);
    expect(AuditLog::query()->where('action', 'like', 'moderation.%')->get()->every(fn ($l) => str_contains((string) $l->detail, 'source=mobile')))->toBeTrue();
});

// --- Notifications ----------------------------------------------------------------------------------------------------------------------

it('liste les notifications d’astreinte par catégorie et les marque lues', function (): void {
    foreach ([['sos', 'SOS'], ['alertes', 'Alerte'], ['communaute', 'Un like']] as [$category, $title]) {
        $this->admin->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => 'test', 'data' => ['title' => $title, 'body' => $title], 'category' => $category, 'is_sensitive' => false,
        ]);
    }

    $response = admCall($this->admin, 'GET', 'notifications')->assertOk();
    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.unread_by_category.sos'))->toBe(1)
        ->and($response->json('meta.unread_by_category.alertes'))->toBe(1);

    admCall($this->admin, 'POST', 'notifications/read-all')->assertOk()->assertJsonPath('data.marked', 2);
    expect($this->admin->notifications()->where('category', 'communaute')->whereNull('read_at')->count())->toBe(1);
});
