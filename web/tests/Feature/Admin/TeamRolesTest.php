<?php

declare(strict_types=1);

use App\Application\Admin\Roles\ManagePeerRole;
use App\Application\Admin\Roles\TeamInvitations;
use App\Application\Admin\Users\ChangeUserRole;
use App\Application\Admin\Users\ReviewRegistration;
use App\Application\Auth\RegisterData;
use App\Application\Auth\RegisterUser;
use App\Application\Auth\VerifyEmail;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Domain\Auth\Exceptions\InvitationInvalid;
use App\Enums\MemberType;
use App\Enums\OtpPurpose;
use App\Enums\UserStatus;
use App\Livewire\Admin\Registrations\Index as RegistrationsScreen;
use App\Livewire\Admin\Users\Index as UsersScreen;
use App\Models\AuditLog;
use App\Models\OtpCode;
use App\Models\Profile;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Notifications\RoleNotice;
use App\Support\Settings;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

/*
 * Un seul compte, rôles cumulables (décisions client du 2026-10-29) : attribuer / retirer le rôle pair à un membre
 * existant (le compte reste membre), inviter un·e pair·e ou un·e admin par lien à usage unique (même inscription que
 * tout le monde, rôle à la validation), refus motivé d'une demande de pair, non-escalade (aucun rôle auto-attribuable).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'app']);
});

function rlUser(array|string $roles, array $attributes = []): User
{
    $user = User::factory()->withProfile()->create($attributes);
    $user->syncRoles((array) $roles);

    return $user;
}

function rlRegister(array $overrides = []): RegisterData
{
    return new RegisterData(...array_merge([
        'pseudo' => 'Nouvelle_Pair',
        'email' => 'nouvelle@exemple.bj',
        'password' => 'Xomiton2026!',
        'acceptsTerms' => true,
        'acceptsPrivacy' => true,
        'confirmsAge' => true,
    ], $overrides));
}

function rlVerify(User $user): void
{
    OtpCode::query()->forEmail($user->email, OtpPurpose::Signup)->update(['code_hash' => OtpCode::hash('654321')]);
    app(VerifyEmail::class)($user->fresh(), '654321');
}

// --- Rôle pair attribué à un membre existant ------------------------------------------------------------------------

it('donne puis retire le rôle pair à un membre qui reste membre, avec journal et notification', function (): void {
    Notification::fake();
    $admin = rlUser(['membre', 'admin']);
    $member = rlUser('membre');

    expect(app(ManagePeerRole::class)->grant($member, $admin))->toBeTrue();

    expect($member->refresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair'])
        ->and($member->profile?->member_type)->toBe(MemberType::Peer)
        ->and(AuditLog::query()->where('action', 'roles.peer_granted')->where('user_id', $admin->id)->exists())->toBeTrue();
    Notification::assertSentTo($member, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_GRANTED);

    // Idempotent.
    expect(app(ManagePeerRole::class)->grant($member, $admin))->toBeFalse();

    expect(app(ManagePeerRole::class)->revoke($member, $admin, 'Fin de mission'))->toBeTrue();

    expect($member->refresh()->getRoleNames()->all())->toBe(['membre'])
        ->and($member->profile?->member_type)->toBe(MemberType::Member)
        ->and(AuditLog::query()->where('action', 'roles.peer_revoked')->where('detail', 'like', '%Fin de mission%')->exists())->toBeTrue();
    Notification::assertSentTo($member, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_REVOKED);
});

it('refuse le rôle pair à un compte en attente, suspendu ou à l’e-mail non confirmé, et à soi-même', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $pending = rlUser('membre', ['status' => UserStatus::Pending]);
    $unverified = rlUser('membre', ['email_verified_at' => null]);

    expect(fn () => app(ManagePeerRole::class)->grant($pending, $admin))->toThrow(AccountActionRefused::class)
        ->and(fn () => app(ManagePeerRole::class)->grant($unverified, $admin))->toThrow(AccountActionRefused::class)
        ->and(fn () => app(ManagePeerRole::class)->grant($admin, $admin))->toThrow(AuthorizationException::class);
});

it('n’autorise ni le pair, ni le membre, ni la modération à donner le rôle pair (non-escalade)', function (): void {
    $target = rlUser('membre');

    foreach (['pair', 'membre', 'moderateur'] as $role) {
        $actor = rlUser($role === 'membre' ? ['membre'] : ['membre', $role]);
        expect(fn () => app(ManagePeerRole::class)->grant($target, $actor))->toThrow(AuthorizationException::class);
    }

    expect($target->refresh()->hasRole('pair'))->toBeFalse();
});

it('depuis l’écran Utilisateurs : l’admin donne et retire le rôle pair, le modérateur ne le peut pas', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $member = rlUser('membre', ['pseudo' => 'koffi_m']);

    Livewire::actingAs($admin)->test(UsersScreen::class)->call('open', $member->id)->call('grantPeer')->assertHasNoErrors();
    expect($member->refresh()->hasRole('pair'))->toBeTrue()->and($member->hasRole('membre'))->toBeTrue();

    Livewire::actingAs($admin)->test(UsersScreen::class)->call('open', $member->id)->call('revokePeer');
    expect($member->refresh()->hasRole('pair'))->toBeFalse()->and($member->hasRole('membre'))->toBeTrue();

    $moderator = rlUser(['membre', 'moderateur']);
    Livewire::actingAs($moderator)->test(UsersScreen::class)->call('open', $member->id)->call('grantPeer')->assertForbidden();
    expect($member->refresh()->hasRole('pair'))->toBeFalse();
});

it('garde le rôle membre quand on change un niveau de rôle et le rôle pair quand un·e pair devient admin', function (): void {
    $super = rlUser(['membre', 'superadmin']);
    $peer = rlUser(['membre', 'pair']);

    app(ChangeUserRole::class)($peer, 'admin', $super);
    expect($peer->refresh()->getRoleNames()->sort()->values()->all())->toBe(['admin', 'membre', 'pair']);

    app(ChangeUserRole::class)($peer, 'pair', $super);
    expect($peer->refresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair']);

    app(ChangeUserRole::class)($peer, 'membre', $super);
    expect($peer->refresh()->getRoleNames()->all())->toBe(['membre'])
        ->and($peer->profile?->member_type)->toBe(MemberType::Member);
});

it('empêche un·e admin de se donner ou de donner un rôle d’administration (RG-15)', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $peer = rlUser(['membre', 'pair']);

    expect(fn () => app(ChangeUserRole::class)($admin, 'superadmin', $admin))->toThrow(AccountActionRefused::class)
        ->and(fn () => app(ChangeUserRole::class)($peer, 'admin', $admin))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeUserRole::class)($peer, 'admin', $peer))->toThrow(AccountActionRefused::class);

    expect($peer->refresh()->hasRole('admin'))->toBeFalse();
});

// --- Invitations ------------------------------------------------------------------------------------------------------

it('crée une invitation pair à usage unique : le code n’est jamais stocké en clair ni journalisé', function (): void {
    $admin = rlUser(['membre', 'admin']);

    $created = app(TeamInvitations::class)->create($admin, 'pair', 'Nouvelle@Exemple.bj', 'Atelier de Parakou');

    $invitation = $created['invitation'];
    expect($created['code'])->toMatch('/^XM(-[2-9A-HJKMNP-Z]{4}){3}$/')
        ->and($invitation->code_hash)->toBe(RoleInvitation::hash($created['code']))
        ->and($invitation->code_hash)->not->toContain($created['code'])
        ->and($invitation->email)->toBe('nouvelle@exemple.bj')
        ->and($invitation->status)->toBe(RoleInvitation::STATUS_PENDING)
        ->and($created['link'])->toContain('/inscription?')->toContain('invitation=')->toContain('signature=')
        ->and(AuditLog::query()->where('action', 'invitations.created')->first()?->detail)->not->toContain($created['code']);
});

it('réserve l’invitation d’un·e admin au super-admin et celle d’un·e pair à peers.invite', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $super = rlUser(['membre', 'superadmin']);
    $moderator = rlUser(['membre', 'moderateur']);
    $peer = rlUser(['membre', 'pair']);

    expect(fn () => app(TeamInvitations::class)->create($admin, 'admin'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TeamInvitations::class)->create($admin, 'superadmin'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TeamInvitations::class)->create($moderator, 'pair'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TeamInvitations::class)->create($peer, 'pair'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TeamInvitations::class)->create($peer, 'admin'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TeamInvitations::class)->create($super, 'chef'))->toThrow(AccountActionRefused::class);

    expect(app(TeamInvitations::class)->create($super, 'admin')['invitation']->role)->toBe('admin');
});

it('inscrit la personne invitée comme membre, puis lui donne le rôle pair quand son e-mail est confirmé', function (): void {
    Notification::fake();
    $admin = rlUser(['membre', 'admin']);
    $created = app(TeamInvitations::class)->create($admin, 'pair');

    $user = app(RegisterUser::class)(rlRegister(['invitationCode' => strtolower($created['code'])]));

    // Inscription ordinaire : membre seulement, aucun rôle d'équipe avant la validation.
    expect($user->getRoleNames()->all())->toBe(['membre'])
        ->and($user->profile?->member_type)->toBe(MemberType::Member)
        ->and($created['invitation']->refresh()->status)->toBe(RoleInvitation::STATUS_REDEEMED)
        ->and($created['invitation']->user_id)->toBe($user->id);

    rlVerify($user);

    expect($user->refresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair'])
        ->and($created['invitation']->refresh()->status)->toBe(RoleInvitation::STATUS_ACCEPTED)
        ->and(AuditLog::query()->where('action', 'roles.peer_granted')->where('detail', 'like', '%via=invitation%')->exists())->toBeTrue();
    Notification::assertSentTo($user, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_GRANTED);
});

it('refuse le code déjà utilisé, expiré, annulé ou lié à une autre adresse, d’un message unique', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $invitations = app(TeamInvitations::class);

    $used = $invitations->create($admin, 'pair');
    app(RegisterUser::class)(rlRegister(['invitationCode' => $used['code']]));
    expect(fn () => app(RegisterUser::class)(rlRegister(['pseudo' => 'Autre_Un', 'email' => 'autre@exemple.bj', 'invitationCode' => $used['code']])))
        ->toThrow(InvitationInvalid::class);

    $expired = $invitations->create($admin, 'pair');
    $expired['invitation']->forceFill(['expires_at' => now()->subMinute()])->save();
    $revoked = $invitations->create($admin, 'pair');
    $invitations->revoke($revoked['invitation'], $admin);
    $bound = $invitations->create($admin, 'pair', 'prevue@exemple.bj');

    foreach ([$expired['code'], $revoked['code'], 'XM-FAUX-FAUX-FAUX'] as $code) {
        expect(fn () => app(RegisterUser::class)(rlRegister(['pseudo' => 'Autre_Deux', 'email' => 'deux@exemple.bj', 'invitationCode' => $code])))
            ->toThrow(InvitationInvalid::class);
    }

    expect(fn () => app(RegisterUser::class)(rlRegister(['pseudo' => 'Autre_Trois', 'email' => 'trois@exemple.bj', 'invitationCode' => $bound['code']])))
        ->toThrow(InvitationInvalid::class);

    // Aucun compte n'a été créé par ces essais, et la case « 15 ans » reste exigée avec une invitation.
    expect(User::query()->where('email', 'like', '%@exemple.bj')->whereNotIn('email', ['nouvelle@exemple.bj'])->where('pseudo', 'like', 'Autre%')->count())->toBe(0);
});

it('ne donne pas le rôle si la personne qui a invité a perdu son droit entre-temps (aucune escalade par invitation)', function (): void {
    $super = rlUser(['membre', 'superadmin']);
    $created = app(TeamInvitations::class)->create($super, 'admin');
    $user = app(RegisterUser::class)(rlRegister(['invitationCode' => $created['code']]));

    $super->syncRoles(['membre']);
    rlVerify($user);

    expect($user->refresh()->hasRole('admin'))->toBeFalse()
        ->and($created['invitation']->refresh()->status)->toBe(RoleInvitation::STATUS_REFUSED);
});

it('donne le rôle admin par invitation du super-admin, en gardant le rôle membre', function (): void {
    $super = rlUser(['membre', 'superadmin']);
    $created = app(TeamInvitations::class)->create($super, 'admin');
    $user = app(RegisterUser::class)(rlRegister(['invitationCode' => $created['code']]));
    rlVerify($user);

    expect($user->refresh()->getRoleNames()->sort()->values()->all())->toBe(['admin', 'membre'])
        ->and(AuditLog::query()->where('action', 'roles.admin_granted')->exists())->toBeTrue();
});

it('ouvre l’inscription fermée au public à qui porte une invitation valable', function (): void {
    Settings::set('registrations_open', false);
    $admin = rlUser(['membre', 'admin']);
    $created = app(TeamInvitations::class)->create($admin, 'pair');

    $this->get('/inscription')->assertOk()->assertDontSee('name="pseudo"', false);
    $this->get('/inscription?invitation='.urlencode($created['code']))->assertOk()
        ->assertSee('name="pseudo"', false)
        ->assertSee('name="invitation"', false)
        ->assertSee('pair');

    $this->post('/inscription', [
        'pseudo' => 'Invitee_Web', 'email' => 'invitee@exemple.bj', 'password' => 'Xomiton2026!', 'password_confirmation' => 'Xomiton2026!',
        'cgu' => '1', 'privacy' => '1', 'age_confirmed' => '1', 'invitation' => $created['code'],
    ])->assertRedirect();

    expect(User::query()->where('email', 'invitee@exemple.bj')->firstOrFail()->getRoleNames()->all())->toBe(['membre']);

    auth('web')->logout();
    $this->flushSession();

    $this->post('/inscription', [
        'pseudo' => 'Invitee_Bis', 'email' => 'bis@exemple.bj', 'password' => 'Xomiton2026!', 'password_confirmation' => 'Xomiton2026!',
        'cgu' => '1', 'privacy' => '1', 'age_confirmed' => '1', 'invitation' => $created['code'],
    ])->assertSessionHasErrors('invitation');
});

it('crée le compte invité par l’API avec la case 15 ans exigée, jamais un rôle d’équipe immédiat', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $created = app(TeamInvitations::class)->create($admin, 'pair');
    $payload = ['pseudo' => 'Invitee_Api', 'email' => 'api@exemple.bj', 'password' => 'Xomiton2026!', 'cgu' => true, 'privacy' => true, 'invitation' => $created['code']];

    $this->postJson('/api/v1/auth/register', $payload)->assertStatus(422)->assertJsonPath('code', 'age_confirmation_required');

    $this->postJson('/api/v1/auth/register', $payload + ['age_confirmed' => true])->assertCreated()
        ->assertJsonPath('data.user.roles', ['membre'])
        ->assertJsonPath('data.user.spaces', ['member']);

    $this->postJson('/api/v1/auth/register', ['email' => 'api2@exemple.bj', 'pseudo' => 'Invitee_Api2', 'age_confirmed' => true] + $payload)
        ->assertStatus(422)->assertJsonPath('code', 'invitation_invalid')->assertJsonPath('errors.invitation.0', fn ($m) => is_string($m));
});

it('n’accorde jamais le rôle pair à l’inscription : le type « pair » du profil est refusé par l’API', function (): void {
    $this->postJson('/api/v1/auth/register', [
        'pseudo' => 'Veut_Etre_Pair', 'email' => 'veut@exemple.bj', 'password' => 'Xomiton2026!', 'cgu' => true, 'privacy' => true,
        'age_confirmed' => true, 'profile' => ['member_type' => 'pair'],
    ])->assertStatus(422)->assertJsonValidationErrors('profile.member_type');

    expect(User::query()->where('email', 'veut@exemple.bj')->exists())->toBeFalse();
});

it('annule une invitation et la liste sur l’écran Inscriptions, pour peers.invite seulement', function (): void {
    $admin = rlUser(['membre', 'admin']);

    Livewire::actingAs($admin)->test(RegistrationsScreen::class)->assertSee('Inviter dans l’équipe')->assertSee('Aucune invitation pour le moment.');

    $screen = Livewire::actingAs($admin)->test(RegistrationsScreen::class)
        ->set('inviteRole', 'pair')->set('inviteNote', 'Atelier')->call('invite')->assertHasNoErrors();
    $code = $screen->get('createdInvite')['code'];
    $invitation = RoleInvitation::query()->firstOrFail();

    expect(RoleInvitation::hash($code))->toBe($invitation->code_hash);
    $screen->call('revokeInvitation', $invitation->id);
    expect($invitation->refresh()->status)->toBe(RoleInvitation::STATUS_REVOKED);

    // L'admin ne peut pas inviter à un rôle d'administration depuis l'écran.
    Livewire::actingAs($admin)->test(RegistrationsScreen::class)->set('inviteRole', 'admin')->call('invite')->assertForbidden();
});

// --- Validation / refus d'une demande de pair --------------------------------------------------------------------------

it('refuse une demande de pair avec un motif : la personne reste membre, est prévenue, la décision est journalisée', function (): void {
    Notification::fake();
    $admin = rlUser(['membre', 'admin']);
    $applicant = rlUser('membre', ['status' => UserStatus::Pending]);
    Profile::query()->where('user_id', $applicant->id)->update(['member_type' => MemberType::Peer->value]);

    app(ReviewRegistration::class)->reject($applicant->refresh(), $admin, 'Formation pair non validée');

    expect($applicant->refresh()->status)->toBe(UserStatus::Active)
        ->and($applicant->trashed())->toBeFalse()
        ->and($applicant->getRoleNames()->all())->toBe(['membre'])
        ->and($applicant->profile?->member_type)->toBe(MemberType::Member)
        ->and(AuditLog::query()->where('action', 'users.peer_refused')->where('detail', 'like', '%Formation pair non validée%')->exists())->toBeTrue();
    Notification::assertSentTo($applicant, RoleNotice::class, fn (RoleNotice $n): bool => $n->kind === RoleNotice::PEER_REFUSED && $n->reason === 'Formation pair non validée');
});

it('valide un compte pair en attente : rôle pair ajouté au rôle membre', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $applicant = rlUser('membre', ['status' => UserStatus::Pending]);
    Profile::query()->where('user_id', $applicant->id)->update(['member_type' => MemberType::Peer->value]);

    app(ReviewRegistration::class)->approve($applicant->refresh(), $admin);

    expect($applicant->refresh()->status)->toBe(UserStatus::Active)
        ->and($applicant->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair']);
});

it('donne le rôle d’une invitation à l’approbation quand la validation manuelle de tous est active', function (): void {
    Settings::set('reg_manual_validation', 'all');
    $admin = rlUser(['membre', 'admin']);
    $created = app(TeamInvitations::class)->create($admin, 'pair');
    $user = app(RegisterUser::class)(rlRegister(['invitationCode' => $created['code']]));

    expect($user->status)->toBe(UserStatus::Pending);
    rlVerify($user);
    expect($user->refresh()->hasRole('pair'))->toBeFalse();

    app(ReviewRegistration::class)->approve($user, $admin);

    expect($user->refresh()->getRoleNames()->sort()->values()->all())->toBe(['membre', 'pair']);
});

it('rattache la personne pair invitée à la zone de l’invitation à la validation de son compte', function (): void {
    Notification::fake();
    $zone = App\Models\Zone::factory()->create();
    $admin = rlUser(['membre', 'admin']);
    $created = app(TeamInvitations::class)->create($admin, 'pair', zoneId: $zone->id, days: 10);

    expect($created['invitation']->zone_id)->toBe($zone->id)
        ->and((int) round(now()->diffInDays($created['invitation']->expires_at, true)))->toBe(10);

    $user = app(RegisterUser::class)(rlRegister(['invitationCode' => $created['code']]));
    expect($user->refresh()->zone_id)->not->toBe($zone->id);
    rlVerify($user);

    expect($user->refresh()->zone_id)->toBe($zone->id)->and($user->hasRole('pair'))->toBeTrue();
});

it('refuse une durée hors bornes ou un département inconnu à la création d’une invitation', function (): void {
    $admin = rlUser(['membre', 'admin']);
    $invitations = app(TeamInvitations::class);

    expect(fn () => $invitations->create($admin, 'pair', days: 0))->toThrow(AccountActionRefused::class)
        ->and(fn () => $invitations->create($admin, 'pair', days: 31))->toThrow(AccountActionRefused::class)
        ->and(fn () => $invitations->create($admin, 'pair', zoneId: 999999))->toThrow(AccountActionRefused::class);
});
