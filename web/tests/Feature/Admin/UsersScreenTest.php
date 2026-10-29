<?php

declare(strict_types=1);

use App\Application\Admin\Users\AdminRoles;
use App\Application\Admin\Users\ChangeUserRole;
use App\Application\Admin\Users\PermissionMatrix;
use App\Application\Auth\Notifications\PeerAccountValidated;
use App\Domain\Account\Exceptions\AccountActionRefused;
use App\Enums\MemberType;
use App\Enums\UserStatus;
use App\Livewire\Admin\Users\Index as UsersScreen;
use App\Mail\TemplateMail;
use App\Models\AuditLog;
use App\Models\Profile;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\ModerationNotice;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/*
 * P12-01 et P12-09 : Utilisateurs & rôles /admin/utilisateurs (Livewire, WithDataTable) — accès par permission (403 par
 * rôle), liste filtrable sans e-mail pour le modérateur, validation des pair·es (P2-10) réutilisée, créer un compte sans
 * mot de passe affiché (e-mail de réinitialisation), changer de rôle (RG-15 : superadmin seul pour modération / admin /
 * superadmin, jamais soi-même, jamais le dernier superadmin), suspendre (motif, accès fermés) et réactiver, réinitialiser
 * le mot de passe par e-mail, supprimer (garde-fous), export CSV, matrice des permissions générée depuis spatie.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class]);
    Mail::fake();
    config(['mail.default' => 'array']);
});

function usrUser(string $role, array $attributes = []): User
{
    $user = User::factory()->create($attributes);
    $user->syncRoles([$role]);

    return $user;
}

function usrScreen(User $user)
{
    test()->actingAs($user);

    return Livewire::test(UsersScreen::class);
}

it('sert l’écran à l’admin, au superadmin et au modérateur, le refuse au pair, au membre et aux visiteurs', function (): void {
    foreach (['admin', 'superadmin', 'moderateur'] as $role) {
        $html = $this->actingAs(usrUser($role))->get(route('admin.utilisateurs'))
            ->assertOk()
            ->assertSeeLivewire(UsersScreen::class)
            ->assertSee('Utilisateurs & rôles')
            ->assertSee('Matrice des permissions')
            ->getContent();
        expect($html)->toMatch('/data-xm-nav="utilisateurs"\s+aria-current="page"/');
    }

    foreach (['pair', 'membre'] as $role) {
        $this->actingAs(usrUser($role))->get(route('admin.utilisateurs'))->assertForbidden();
    }

    auth()->logout();
    $this->get(route('admin.utilisateurs'))->assertRedirect();
});

it('liste les comptes avec rôle, statut et département, filtre par rôle, statut et recherche', function (): void {
    $zone = Zone::factory()->create(['name' => 'Atlantique']);
    $admin = usrUser('admin');
    $awa = usrUser('pair', ['pseudo' => 'awa_pair', 'name' => 'Awa Sagbo', 'email' => 'awa@exemple.bj', 'zone_id' => $zone->id]);
    usrUser('membre', ['pseudo' => 'koffi_m', 'name' => 'Koffi Ahoua', 'email' => 'koffi@exemple.bj']);
    $sus = usrUser('membre', ['pseudo' => 'zoe_sus', 'name' => 'Zoé']);
    $sus->forceFill(['status' => UserStatus::Suspended])->save();

    usrScreen($admin)
        ->assertSee(['awa_pair', 'koffi_m', 'Awa Sagbo', 'awa@exemple.bj', 'Atlantique'])
        ->assertSee('Pair éducateur·rice')
        ->set('role', 'pair')
        ->assertSee('awa_pair')
        ->assertDontSee('koffi_m')
        ->set('role', '')
        ->set('status', 'suspendu')
        ->assertSee('zoe_sus')
        ->assertDontSee('awa_pair')
        ->set('status', '')
        ->set('search', 'koffi@')
        ->assertSee('koffi_m')
        ->assertDontSee('awa_pair');

    expect($awa->refresh()->zone?->name)->toBe('Atlantique');
});

it('ne montre au modérateur ni e-mail ni téléphone, n’y cherche pas par e-mail et ne lui propose que « Suspendre »', function (): void {
    $moderator = usrUser('moderateur');
    $member = usrUser('membre', ['pseudo' => 'koffi_m', 'name' => 'Koffi Ahoua', 'email' => 'koffi@exemple.bj', 'phone' => '+229 01 97 38 88 47']);

    usrScreen($moderator)
        ->assertSee('koffi_m')
        ->assertDontSee('koffi@exemple.bj')
        ->assertDontSee('Créer un compte')
        ->assertDontSee('CSV')
        ->set('search', 'exemple.bj')
        ->assertDontSee('koffi_m')
        ->set('search', '')
        ->call('open', $member->id)
        ->assertSee('Suspendre')
        ->assertDontSee('+229 01 97 38 88 47')
        ->assertDontSee('Enregistrer le rôle')
        ->assertDontSee('Envoyer un lien de nouveau mot de passe')
        ->assertDontSee('Supprimer');

    // Chaque action réservée à l’administration reste refusée côté serveur, même appelée à la main.
    usrScreen($moderator)->call('open', $member->id)->set('roleChoice', 'pair')->call('changeRole')->assertForbidden();
    usrScreen($moderator)->call('open', $member->id)->call('resetPassword')->assertForbidden();
    usrScreen($moderator)->call('open', $member->id)->call('delete')->assertForbidden();
    usrScreen($moderator)->call('export')->assertForbidden();
    usrScreen($moderator)->set('newName', 'Awa Test')->set('newPseudo', 'awa_test')->set('newEmail', 'awa@test.bj')->call('create')->assertForbidden();
    expect($member->refresh()->hasRole('membre'))->toBeTrue()->and(User::query()->where('pseudo', 'awa_test')->exists())->toBeFalse();
});

it('laisse le modérateur suspendre un membre, ferme ses accès, le prévient et journalise', function (): void {
    Notification::fake();
    $moderator = usrUser('moderateur');
    $member = usrUser('membre', ['pseudo' => 'koffi_m']);
    $member->createToken('mobile');
    DB::table('sessions')->insert(['id' => 'sess-koffi', 'user_id' => $member->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'test', 'payload' => '', 'last_activity' => time()]);

    usrScreen($moderator)
        ->call('open', $member->id)
        ->call('suspend')
        ->assertHasErrors(['suspendReason' => 'required'])
        ->set('suspendReason', 'Propos haineux répétés')
        ->call('suspend')
        ->assertHasNoErrors();

    $member->refresh();
    expect($member->status)->toBe(UserStatus::Suspended)
        ->and($member->suspension_reason)->toBe('Propos haineux répétés')
        ->and($member->suspended_at)->not->toBeNull()
        ->and($member->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $member->id)->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'users.suspended')->where('user_id', $moderator->id)->exists())->toBeTrue();

    Notification::assertSentTo($member, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::SUSPENDED && $n->reason === 'Propos haineux répétés');
});

it('ne laisse jamais un modérateur toucher un compte de modération, d’administration ou de superadmin', function (): void {
    $moderator = usrUser('moderateur');

    foreach (['moderateur', 'admin', 'superadmin'] as $role) {
        $target = usrUser($role);

        usrScreen($moderator)
            ->call('open', $target->id)
            ->set('suspendReason', 'Test')
            ->call('suspend')
            ->assertForbidden();

        expect($target->refresh()->status)->toBe(UserStatus::Active);
    }

    // Ni lui-même.
    usrScreen($moderator)->call('open', $moderator->id)->set('suspendReason', 'Test')->call('suspend')->assertForbidden();
    expect($moderator->refresh()->status)->toBe(UserStatus::Active);
});

it('refuse à l’admin de toucher un compte d’administration, accepte le superadmin', function (): void {
    Notification::fake();
    $admin = usrUser('admin');
    $other = usrUser('admin');
    $super = usrUser('superadmin');

    usrScreen($admin)->call('open', $other->id)->set('suspendReason', 'Test')->call('suspend')->assertForbidden();
    usrScreen($admin)->call('open', $other->id)->call('resetPassword')->assertForbidden();
    usrScreen($admin)->call('open', $other->id)->call('delete')->assertForbidden();
    usrScreen($admin)->call('open', $super->id)->set('suspendReason', 'Test')->call('suspend')->assertForbidden();

    usrScreen($super)->call('open', $other->id)->set('suspendReason', 'Absence prolongée')->call('suspend')->assertHasNoErrors();
    expect($other->refresh()->status)->toBe(UserStatus::Suspended);
});

it('suspend et réactive un compte, la réactivation étant réservée à qui gère les utilisateurs', function (): void {
    Notification::fake();
    $moderator = usrUser('moderateur');
    $admin = usrUser('admin');
    $member = usrUser('membre');
    $member->forceFill(['status' => UserStatus::Suspended, 'suspended_at' => now(), 'suspension_reason' => 'Test'])->save();

    usrScreen($moderator)->call('open', $member->id)->call('reactivate')->assertForbidden();
    expect($member->refresh()->status)->toBe(UserStatus::Suspended);

    usrScreen($admin)->call('open', $member->id)->call('reactivate')->assertHasNoErrors();
    expect($member->refresh()->status)->toBe(UserStatus::Active)->and($member->suspension_reason)->toBeNull();
    Notification::assertSentTo($member, ModerationNotice::class, fn (ModerationNotice $n): bool => $n->kind === ModerationNotice::REACTIVATED);
    expect(AuditLog::query()->where('action', 'users.reactivated')->exists())->toBeTrue();
});

it('ne suspend ni soi-même ni un compte en attente', function (): void {
    $admin = usrUser('admin');
    $pending = User::factory()->pending()->create();
    $pending->syncRoles(['pair']);

    usrScreen($admin)->call('open', $admin->id)->set('suspendReason', 'Test')->call('suspend')->assertForbidden();

    usrScreen($admin)->call('open', $pending->id)->set('suspendReason', 'Test')->call('suspend')->assertHasNoErrors();
    expect($pending->refresh()->status)->toBe(UserStatus::Pending);
});

it('valide une demande de pair·e (P2-10) : actif, rôle pair, notification et journal ; l’admin seulement', function (): void {
    Notification::fake();
    $admin = usrUser('admin');
    $peer = User::factory()->pending()->create(['pseudo' => 'fatou_p']);
    Profile::factory()->create(['user_id' => $peer->id, 'member_type' => MemberType::Peer]);
    $peer->syncRoles(['membre']);

    usrScreen($admin)->assertSee('compte à valider')->assertSee('fatou_p')->call('approve', $peer->id)->assertHasNoErrors();

    $peer->refresh();
    expect($peer->status)->toBe(UserStatus::Active)->and($peer->hasRole('pair'))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'users.validate_peer')->exists())->toBeTrue();
    Notification::assertSentTo($peer, PeerAccountValidated::class);

    // Le modérateur ne voit pas le panneau et ne peut pas valider.
    $other = User::factory()->pending()->create();
    $other->syncRoles(['membre']);
    usrScreen(usrUser('moderateur'))->assertDontSee('compte à valider')->call('approve', $other->id)->assertForbidden();
    expect($other->refresh()->status)->toBe(UserStatus::Pending);
});

it('rejette une demande en attente : compte supprimé, accès fermés, journal', function (): void {
    $admin = usrUser('admin');
    $peer = User::factory()->pending()->create();
    $peer->syncRoles(['pair']);

    usrScreen($admin)->call('reject', $peer->id)->assertHasNoErrors();

    expect(User::query()->find($peer->id))->toBeNull()
        ->and(User::withTrashed()->find($peer->id))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'users.registration_rejected')->where('user_id', $admin->id)->exists())->toBeTrue();

    // Un compte déjà actif n’est pas une demande.
    $active = usrUser('membre');
    usrScreen($admin)->call('reject', $active->id);
    expect(User::query()->find($active->id))->not->toBeNull();
});

it('crée un compte sans mot de passe saisi ni affiché et envoie l’e-mail de réinitialisation', function (): void {
    $admin = usrUser('admin');
    $zone = Zone::factory()->create();

    usrScreen($admin)
        ->assertDontSee('Mot de passe temporaire')
        ->set('newName', 'Awa Sagbo')
        ->set('newPseudo', 'awa_s')
        ->set('newEmail', 'Awa@Exemple.bj')
        ->set('newPhone', '01 97 38 88 47')
        ->set('newZone', (string) $zone->id)
        ->set('newRole', 'pair')
        ->call('create')
        ->assertHasNoErrors();

    $user = User::query()->where('pseudo', 'awa_s')->firstOrFail();
    expect($user->email)->toBe('awa@exemple.bj')
        ->and($user->status)->toBe(UserStatus::Active)
        ->and($user->hasRole('pair'))->toBeTrue()
        ->and($user->profile?->member_type)->toBe(MemberType::Peer)
        ->and($user->settings)->not->toBeNull()
        ->and($user->zone_id)->toBe($zone->id)
        ->and($user->phone)->toStartWith('+229')
        ->and(AuditLog::query()->where('action', 'users.created')->where('user_id', $admin->id)->exists())->toBeTrue();

    $slugs = [];
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$slugs): bool {
        $slugs[] = $mail->templateSlug;

        return true;
    });
    expect($slugs)->toBe(['reinitialisation-mot-de-passe']);
});

it('valide la création : pseudonyme, e-mail unique, rôle attribuable', function (): void {
    $admin = usrUser('admin');
    usrUser('membre', ['email' => 'pris@exemple.bj']);

    usrScreen($admin)
        ->set('newName', 'A')->set('newPseudo', 'x')->set('newEmail', 'pas-un-email')->set('newRole', 'membre')
        ->call('create')
        ->assertHasErrors(['newName', 'newPseudo', 'newEmail'])
        ->set('newName', 'Awa Sagbo')->set('newPseudo', 'awa_s')->set('newEmail', 'pris@exemple.bj')
        ->call('create')
        ->assertHasErrors(['newEmail'])
        ->set('newEmail', 'awa@exemple.bj')->set('newRole', 'admin')
        ->call('create')
        ->assertHasErrors(['newRole']);

    expect(User::query()->where('pseudo', 'awa_s')->exists())->toBeFalse();
});

it('laisse le superadmin créer un compte de modération ou d’administration, pas l’admin', function (): void {
    $super = usrUser('superadmin');

    usrScreen($super)
        ->set('newName', 'Modou Diallo')->set('newPseudo', 'modou_d')->set('newEmail', 'modou@exemple.bj')->set('newRole', 'moderateur')
        ->call('create')->assertHasNoErrors();

    expect(User::query()->where('pseudo', 'modou_d')->firstOrFail()->hasRole('moderateur'))->toBeTrue()
        ->and(AdminRoles::assignable(usrUser('admin')))->toBe(['membre', 'pair'])
        ->and(AdminRoles::assignable($super))->toBe(AdminRoles::ALL)
        ->and(AdminRoles::assignable(usrUser('moderateur')))->toBe([]);
});

it('change un rôle selon RG-15 : admin pour membre et pair, superadmin seul pour la modération et l’administration', function (): void {
    $admin = usrUser('admin');
    $super = usrUser('superadmin');
    $member = usrUser('membre');
    Profile::factory()->create(['user_id' => $member->id, 'member_type' => MemberType::Member]);

    usrScreen($admin)->call('open', $member->id)->set('roleChoice', 'pair')->call('changeRole')->assertHasNoErrors();
    expect($member->refresh()->hasRole('pair'))->toBeTrue()->and($member->hasRole('membre'))->toBeTrue()
        ->and($member->profile?->member_type)->toBe(MemberType::Peer)
        ->and(AuditLog::query()->where('action', 'users.role_changed')->where('detail', 'like', '%ancien=membre%nouveau=pair%')->exists())->toBeTrue();

    usrScreen($admin)->call('open', $member->id)->set('roleChoice', 'moderateur')->call('changeRole')->assertForbidden();
    usrScreen($admin)->call('open', $member->id)->set('roleChoice', 'admin')->call('changeRole')->assertForbidden();
    expect($member->refresh()->hasRole('pair'))->toBeTrue();

    usrScreen($super)->call('open', $member->id)->set('roleChoice', 'moderateur')->call('changeRole')->assertHasNoErrors();
    expect($member->refresh()->hasRole('moderateur'))->toBeTrue();

    // Retirer un rôle d’administration reste une action de superadmin.
    usrScreen($admin)->call('open', $member->id)->set('roleChoice', 'membre')->call('changeRole')->assertForbidden();
    usrScreen($super)->call('open', $member->id)->set('roleChoice', 'membre')->call('changeRole')->assertHasNoErrors();
    expect($member->refresh()->hasRole('membre'))->toBeTrue();
});

it('refuse de changer son propre rôle et de rétrograder le dernier superadmin', function (): void {
    $super = usrUser('superadmin');

    usrScreen($super)->call('open', $super->id)->set('roleChoice', 'admin')->call('changeRole')->assertDispatched('toast', type: 'error', message: 'Vous ne pouvez pas modifier votre propre rôle.');
    expect($super->refresh()->hasRole('superadmin'))->toBeTrue();

    // Un compte qui détiendrait `roles.assign_admin` sans être superadmin ne peut pas vider le rôle.
    $delegate = usrUser('admin');
    $delegate->givePermissionTo('roles.assign_admin');

    expect(fn () => app(ChangeUserRole::class)($super, 'admin', $delegate))->toThrow(AccountActionRefused::class);
    expect($super->refresh()->hasRole('superadmin'))->toBeTrue();

    // À deux superadmin, la rétrogradation de l’un passe.
    $second = usrUser('superadmin');
    app(ChangeUserRole::class)($second, 'admin', $delegate);
    expect($second->refresh()->hasRole('admin'))->toBeTrue();
});

it('envoie le lien de nouveau mot de passe par e-mail, sans jamais afficher de mot de passe', function (): void {
    $admin = usrUser('admin');
    $member = usrUser('membre', ['email' => 'koffi@exemple.bj']);

    usrScreen($admin)->call('open', $member->id)->call('resetPassword')->assertDontSee('temporaire');

    $slugs = [];
    Mail::assertSent(TemplateMail::class, function (TemplateMail $mail) use (&$slugs): bool {
        $slugs[] = $mail->templateSlug;

        return true;
    });
    expect($slugs)->toBe(['reinitialisation-mot-de-passe'])
        ->and(AuditLog::query()->where('action', 'users.password_reset_sent')->exists())->toBeTrue();
});

it('supprime un compte avec ses garde-fous : pas soi-même, pas un compte d’administration sans superadmin', function (): void {
    $admin = usrUser('admin');
    $super = usrUser('superadmin');
    $member = usrUser('membre');

    usrScreen($admin)->call('open', $admin->id)->call('delete')->assertDispatched('toast', type: 'error', message: 'Vous ne pouvez pas supprimer votre propre compte.');
    expect(User::query()->find($admin->id))->not->toBeNull();

    usrScreen($admin)->call('open', $member->id)->call('delete')->assertHasNoErrors();
    expect(User::query()->find($member->id))->toBeNull()
        ->and(AuditLog::query()->where('action', 'users.deleted_by_admin')->exists())->toBeTrue();

    usrScreen($admin)->call('open', $super->id)->call('delete')->assertForbidden();
    expect(User::query()->find($super->id))->not->toBeNull();
});

it('exporte les comptes filtrés en CSV pour l’admin seulement', function (): void {
    usrUser('membre', ['pseudo' => 'koffi_m']);

    usrScreen(usrUser('admin'))->call('export')->assertRedirect();
});

it('ouvre la modale d’un identifiant inconnu en 404 et ignore un statut ou un rôle d’URL mal formé', function (): void {
    $admin = usrUser('admin');

    expect(fn () => usrScreen($admin)->call('open', 999999))->toThrow(ModelNotFoundException::class);

    $this->actingAs($admin)->get(route('admin.utilisateurs', ['role' => 'x', 'st' => ['a'], 'zone' => 'abc', 'par' => 'zz']))->assertOk();
});

it('génère la matrice des permissions depuis spatie, couvre tout le catalogue et la garde à jour', function (): void {
    $catalog = array_keys(RolesAndPermissionsSeeder::matrix());
    $covered = collect(PermissionMatrix::MODULES)->flatten()->all();

    expect(array_diff($catalog, $covered))->toBe([], 'Permission du catalogue absente de la matrice')
        ->and(array_diff($covered, $catalog))->toBe([], 'Permission de la matrice absente du catalogue');

    $matrix = PermissionMatrix::build();
    $levels = collect($matrix['rows'])->mapWithKeys(fn (array $row): array => [$row['key'] => $row['levels']]);

    expect(collect($matrix['roles'])->pluck('key')->all())->toBe(AdminRoles::ALL)
        ->and($levels['admin_roles']['superadmin'])->toBe('superadmin')
        ->and($levels['admin_roles']['admin'])->toBe('none')
        ->and($levels['moderation']['moderateur'])->toBe('full')
        ->and($levels['moderation']['membre'])->toBe('none')
        ->and($levels['users']['moderateur'])->toBe('read')
        ->and($levels['users']['admin'])->toBe('full')
        ->and($levels['social']['membre'])->toBe('full')
        ->and($levels['admin_access']['pair'])->toBe('none');

    // La matrice suit les rôles en base : retirer une permission la fait passer en lecture.
    Role::findByName('admin')->revokePermissionTo('newsletter.send');
    $after = collect(PermissionMatrix::build()['rows'])->firstWhere('key', 'newsletter');
    expect($after['levels']['admin'])->toBe('read');
});

it('compte les comptes par rôle dans la matrice', function (): void {
    usrUser('membre');
    usrUser('membre');
    usrUser('moderateur');

    $roles = collect(PermissionMatrix::build()['roles'])->pluck('accounts', 'key');

    expect($roles['membre'])->toBe(2)->and($roles['moderateur'])->toBe(1)->and($roles['superadmin'])->toBe(0);
});
