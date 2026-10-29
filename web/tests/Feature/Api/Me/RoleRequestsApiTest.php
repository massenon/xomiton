<?php

declare(strict_types=1);

use App\Application\Admin\Mobile\StepUp;
use App\Enums\UserStatus;
use App\Livewire\Admin\Registrations\Index as RegistrationsScreen;
use App\Models\AuditLog;
use App\Models\RoleRequest;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Notifications\RoleRequested;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * D-MOB-15 (tranché 2026-10-29) : un membre DEMANDE le rôle pair depuis son profil (`POST me/role-requests`), l'admin décide
 * (file web Livewire et API `admin/role-requests`, step-up). Jamais d'auto-attribution, une demande en cours à la fois,
 * motif court facultatif, journal et notifications.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Notification::fake();
    $this->admin = F::member('admin');
    $this->member = F::member('membre');
});

function rrAsk(User $user, array $body = [], ?string $key = null): Illuminate\Testing\TestResponse
{
    return F::as($user)->withHeaders(['Idempotency-Key' => $key ?? (string) Str::uuid()])->postJson('/api/v1/me/role-requests', $body);
}

function rrAdmin(User $user, string $method, string $uri, array $body = [], bool $stepUp = false): Illuminate\Testing\TestResponse
{
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    if ($stepUp) {
        $headers[StepUp::HEADER] = app(StepUp::class)->issue($user, 'password')['token'];
    }

    return F::as($user)->withHeaders($headers)->json($method, '/api/v1/admin/'.$uri, $body);
}

it('crée une demande (motif facultatif) sans donner le rôle, la liste, prévient les admins et journalise', function (): void {
    $response = rrAsk($this->member, ['reason' => '  Je co-anime un atelier à Cotonou.  '])->assertCreated()
        ->assertJsonPath('data.status', 'pending')->assertJsonPath('data.role', 'pair')
        ->assertJsonPath('data.reason', 'Je co-anime un atelier à Cotonou.');

    expect($this->member->fresh()->hasRole('pair'))->toBeFalse()
        ->and(AuditLog::query()->where('action', 'roles.request_created')->count())->toBe(1);
    Notification::assertSentTo($this->admin, RoleRequested::class);
    Notification::assertNotSentTo($this->member, RoleRequested::class);

    $list = F::as($this->member)->getJson('/api/v1/me/role-requests')->assertOk()->assertJsonPath('meta.can_request', false);
    expect($list->json('data.0.id'))->toBe($response->json('data.id'));
});

it('accepte une demande sans motif, refuse un motif trop long et ignore tout champ de rôle', function (): void {
    rrAsk($this->member, ['reason' => str_repeat('a', 201)])->assertStatus(422);
    rrAsk($this->member, ['role' => 'admin', 'roles' => ['superadmin']])->assertCreated()->assertJsonPath('data.role', 'pair');

    expect($this->member->fresh()->roles->pluck('name')->all())->toBe(['membre']);
});

it('exige Idempotency-Key, rejoue la même clé sans second effet et n’autorise qu’une demande en cours', function (): void {
    F::as($this->member)->postJson('/api/v1/me/role-requests', [])->assertStatus(422)->assertJsonPath('code', 'idempotency_key_required');

    $key = (string) Str::uuid();
    rrAsk($this->member, [], $key)->assertCreated();
    rrAsk($this->member, [], $key)->assertCreated()->assertHeader('Idempotent-Replayed', 'true');
    expect(RoleRequest::query()->count())->toBe(1);

    rrAsk($this->member)->assertStatus(422)->assertJsonPath('code', 'request_open');
    expect(RoleRequest::query()->count())->toBe(1);
});

it('refuse un compte déjà pair, un e-mail non confirmé ou un compte non actif, et limite le débit', function (): void {
    rrAsk(F::member('pair'))->assertStatus(422)->assertJsonPath('code', 'already_peer');

    $unverified = F::member('membre');
    $unverified->forceFill(['email_verified_at' => null])->save();
    rrAsk($unverified)->assertStatus(422)->assertJsonPath('code', 'email_unverified');

    app('auth')->forgetGuards();
    $this->flushHeaders()->postJson('/api/v1/me/role-requests')->assertUnauthorized();

    config(['xomiton.rate_limits.role_request_per_day' => 2]);
    $user = F::member('membre');
    foreach ([1, 2] as $_) {
        $request = rrAsk($user)->assertCreated();
        RoleRequest::query()->where('ulid', $request->json('data.id'))->update(['status' => 'rejected', 'open_user_id' => null]);
    }
    rrAsk($user)->assertStatus(429);
});

it('approuve par l’API admin avec step-up : le rôle est donné, la personne reste membre, notifiée et journalisée', function (): void {
    $id = rrAsk($this->member, ['reason' => 'Atelier'])->assertCreated()->json('data.id');

    $queue = rrAdmin($this->admin, 'GET', 'role-requests')->assertOk()->assertJsonPath('meta.pending', 1)->assertJsonPath('data.0.id', $id)
        ->assertJsonPath('data.0.user.pseudo', $this->member->pseudo)->assertJsonPath('data.0.reason', 'Atelier');
    expect($queue->getContent())->not->toContain($this->member->email);

    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/approve')->assertForbidden()->assertJsonPath('code', 'step_up_required');
    expect($this->member->fresh()->hasRole('pair'))->toBeFalse();

    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/approve', [], stepUp: true)->assertOk()->assertJsonPath('data.status', 'approved');

    expect($this->member->fresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair'])
        ->and(AuditLog::query()->where('action', 'roles.request_approved')->count())->toBe(1)
        ->and(RoleRequest::query()->first()->open_user_id)->toBeNull();
    Notification::assertSentTo($this->member, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_GRANTED);

    // Déjà traitée : pas de seconde décision.
    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/approve', [], stepUp: true)->assertStatus(422)->assertJsonPath('code', 'already_decided');
    rrAdmin($this->admin, 'GET', 'role-requests')->assertJsonCount(0, 'data');
    rrAdmin($this->admin, 'GET', 'role-requests?status=approved')->assertJsonCount(1, 'data');

    // Une nouvelle demande est possible pour qui n'est plus pair (retrait ultérieur).
    $this->member->removeRole('pair');
    rrAsk($this->member)->assertCreated();
});

it('refuse avec un motif obligatoire et step-up : le compte reste membre, la personne reçoit le motif, elle peut redemander', function (): void {
    $id = rrAsk($this->member)->assertCreated()->json('data.id');

    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/reject', ['reason' => 'Pas encore de formation'])->assertForbidden()->assertJsonPath('code', 'step_up_required');
    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/reject', ['reason' => 'ab'], stepUp: true)->assertStatus(422);
    rrAdmin($this->admin, 'POST', 'role-requests/'.$id.'/reject', ['reason' => 'Pas encore de formation'], stepUp: true)->assertOk()
        ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.decision_reason', 'Pas encore de formation');

    expect($this->member->fresh()->hasRole('pair'))->toBeFalse()->and($this->member->fresh()->status)->toBe(UserStatus::Active);
    Notification::assertSentTo($this->member, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_REFUSED && $n->reason === 'Pas encore de formation');

    $mine = F::as($this->member)->getJson('/api/v1/me/role-requests')->assertOk()->assertJsonPath('meta.can_request', true);
    expect($mine->json('data.0.decision_reason'))->toBe('Pas encore de formation')->and($mine->getContent())->not->toContain('decided_by');
    rrAsk($this->member)->assertCreated();
});

it('ne permet aucune escalade : membre, pair et modérateur n’ont pas la file ; un·e admin ne décide jamais sa propre demande', function (): void {
    $id = rrAsk($this->member)->assertCreated()->json('data.id');

    foreach ([$this->member, F::member('pair'), F::member('moderateur')] as $denied) {
        rrAdmin($denied, 'GET', 'role-requests')->assertForbidden();
        rrAdmin($denied, 'POST', 'role-requests/'.$id.'/approve', [], stepUp: true)->assertForbidden();
        rrAdmin($denied, 'POST', 'role-requests/'.$id.'/reject', ['reason' => 'non'], stepUp: true)->assertForbidden();
    }
    expect($this->member->fresh()->hasRole('pair'))->toBeFalse();

    // Un·e admin sans rôle pair demande le rôle : il ne peut pas l'approuver lui-même (un·e autre admin le fait).
    $own = rrAsk($this->admin)->assertCreated()->json('data.id');
    rrAdmin($this->admin, 'POST', 'role-requests/'.$own.'/approve', [], stepUp: true)->assertForbidden();
    expect($this->admin->fresh()->hasRole('pair'))->toBeFalse();
    rrAdmin($this->admin, 'POST', 'role-requests/01ARZ3NDEKTSV4RRFFQ69G5FAV/approve', [], stepUp: true)->assertNotFound();

    $other = F::member('superadmin');
    rrAdmin($other, 'POST', 'role-requests/'.$own.'/approve', [], stepUp: true)->assertOk();
    expect($this->admin->fresh()->hasRole('pair'))->toBeTrue()->and($this->admin->fresh()->hasRole('admin'))->toBeTrue();

    // La demande ne peut jamais viser autre chose que le rôle pair (aucun rôle d'administration par cette voie).
    expect(RoleRequest::query()->pluck('role')->unique()->all())->toBe(['pair']);
});

it('traite la file côté web (Livewire) : approuver, refuser avec motif, mêmes règles', function (): void {
    rrAsk($this->member)->assertCreated();
    $second = F::member('membre');
    rrAsk($second, ['reason' => 'Je suis éducatrice'])->assertCreated();

    $component = Livewire::actingAs($this->admin)->test(RegistrationsScreen::class)
        ->assertSee($this->member->pseudo)->assertSee('Je suis éducatrice');

    $first = RoleRequest::query()->where('user_id', $this->member->id)->first();
    $component->call('approveRoleRequest', $first->id);
    expect($this->member->fresh()->hasRole('pair'))->toBeTrue();

    $other = RoleRequest::query()->where('user_id', $second->id)->first();
    $component->set('roleRejectReason', '')->call('rejectRoleRequest', $other->id);
    expect($other->fresh()->status)->toBe('pending');

    $component->set('roleRejectReason', 'Pas cette fois')->call('rejectRoleRequest', $other->id);
    expect($other->fresh()->status)->toBe('rejected')->and($second->fresh()->hasRole('pair'))->toBeFalse();

    // Un membre sans permission n'ouvre pas la page.
    Livewire::actingAs(F::member('membre'))->test(RegistrationsScreen::class)->assertForbidden();
});
