<?php

declare(strict_types=1);

use App\Application\Admin\Users\VerifiedBadges;
use App\Enums\UserStatus;
use App\Enums\VerifiedBadge;
use App\Livewire\Admin\Users\Index as UsersScreen;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\User;
use App\Models\UserBadge;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * P18-08 : filtres de /admin/utilisateurs — plage d'inscription (heure du Bénin, bornes incluses), statut dont banni,
 * niveau d'activité, « bénévoles écoutant·es » = pair·es approuvé·es (Q97, pas de rôle à part), suspension temporaire
 * et bannissement depuis la fiche. P18-09 : badges vérifiés attribués à la main (`badges.manage`), éligibilité,
 * retrait, journal, affichage sur le profil et à côté du nom dans le fil (API), jamais pour un contenu anonyme.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 12)->setTime(12, 0));
    $this->admin = User::factory()->create(['pseudo' => 'Admin_P18', 'created_at' => now()->subYears(2), 'last_seen_at' => now()]);
    $this->admin->syncRoles(['admin']);
});

function p18Member(string $pseudo, array $attributes = [], string $role = 'membre'): User
{
    $user = User::factory()->create(['pseudo' => $pseudo] + $attributes);
    $user->syncRoles([$role]);

    return $user;
}

function p18Rows($component): array
{
    return $component->viewData('rows')->getCollection()->pluck('pseudo')->sort()->values()->all();
}

it('filtre par plage de dates d’inscription dans le fuseau du Bénin, bornes incluses, valeur mal formée ignorée', function (): void {
    p18Member('Ines', ['created_at' => '2026-11-09 23:30:00']);  // 10/11 00:30 au Bénin
    p18Member('Jade', ['created_at' => '2026-11-10 22:59:00']);  // 10/11 23:59 au Bénin
    p18Member('Kemi', ['created_at' => '2026-11-10 23:01:00']);  // 11/11 au Bénin

    $this->actingAs($this->admin);
    $screen = Livewire::test(UsersScreen::class)->set('joinedFrom', '2026-11-10')->set('joinedTo', '2026-11-10');
    expect(p18Rows($screen))->toBe(['Ines', 'Jade']);

    $screen->set('joinedTo', '2026-13-45');
    expect(p18Rows($screen))->toBe(['Ines', 'Jade', 'Kemi']);
});

it('filtre par statut banni et par niveau d’activité', function (): void {
    p18Member('Recent', ['last_seen_at' => now()->subDays(2), 'created_at' => now()->subYear()]);
    p18Member('Mois', ['last_seen_at' => now()->subDays(20), 'created_at' => now()->subYear()]);
    p18Member('Dormant', ['last_seen_at' => now()->subDays(40), 'created_at' => now()->subYear()]);
    p18Member('Jamais', ['last_seen_at' => null, 'created_at' => now()->subYear()]);
    p18Member('Exclue', ['status' => UserStatus::Banned, 'last_seen_at' => now()->subDays(40), 'created_at' => now()->subYear()]);

    $this->actingAs($this->admin);
    $screen = Livewire::test(UsersScreen::class);

    expect(p18Rows($screen->set('status', 'banni')))->toBe(['Exclue'])
        ->and($screen->instance()->stats()['banned'])->toBe(1);
    $screen->set('status', '');
    expect(p18Rows($screen->set('activity', '7j')))->toBe(['Admin_P18', 'Recent'])
        ->and(p18Rows($screen->set('activity', '30j')))->toBe(['Admin_P18', 'Mois', 'Recent'])
        ->and(p18Rows($screen->set('activity', 'inactif')))->toBe(['Dormant', 'Exclue', 'Jamais']);
});

it('filtre les bénévoles écoutant·es : pair·es approuvé·es (rôle pair, compte actif), sans nouveau rôle', function (): void {
    p18Member('Pair_ok', ['created_at' => now()->subYear()], 'pair');
    p18Member('Pair_attente', ['status' => UserStatus::Pending, 'created_at' => now()->subYear()], 'pair');
    p18Member('Membre', ['created_at' => now()->subYear()]);

    $this->actingAs($this->admin);
    expect(p18Rows(Livewire::test(UsersScreen::class)->set('role', UsersScreen::VOLUNTEERS)))->toBe(['Pair_ok']);
    expect(Spatie\Permission\Models\Role::query()->pluck('name')->all())->not->toContain('benevole');
});

it('suspend temporairement et bannit depuis la fiche du compte ; le modérateur ne peut pas bannir', function (): void {
    $target = p18Member('Cible', ['created_at' => now()->subYear()]);
    $this->actingAs($this->admin);

    Livewire::test(UsersScreen::class)->call('open', $target->id)
        ->set('suspendReason', 'Pause demandée')->set('suspendUntil', '2026-11-20')->call('suspend')->assertHasNoErrors();
    expect($target->refresh()->status)->toBe(UserStatus::Suspended)->and($target->suspended_until)->not->toBeNull();

    Livewire::test(UsersScreen::class)->call('open', $target->id)->call('ban')->assertHasErrors(['banReason' => 'required'])
        ->set('banReason', 'Récidive grave')->call('ban')->assertHasNoErrors();
    expect($target->refresh()->isBanned())->toBeTrue()->and($target->suspended_until)->toBeNull();

    $other = p18Member('Autre', ['created_at' => now()->subYear()]);
    $this->actingAs(p18Member('Modo', ['created_at' => now()->subYear()], 'moderateur'));
    Livewire::test(UsersScreen::class)->call('open', $other->id)->set('banReason', 'Essai')->call('ban')->assertForbidden();
    expect($other->refresh()->isBanned())->toBeFalse();
});

it('attribue et retire à la main les badges vérifiés, avec journal, selon l’éligibilité', function (): void {
    $member = p18Member('Awa', ['created_at' => now()->subYear()]);
    $peer = p18Member('Kossi', ['created_at' => now()->subYear()], 'pair');
    $this->actingAs($this->admin);

    Livewire::test(UsersScreen::class)->call('open', $member->id)
        ->assertSee("Attribuer «\u{a0}Membre vérifié\u{a0}»")
        ->assertDontSee("Attribuer «\u{a0}Pair animateur·rice\u{a0}»")
        ->call('grantBadge', 'membre_verifie')
        ->assertDispatched('toast', type: 'success', message: "Badge «\u{a0}Membre vérifié\u{a0}» attribué à Awa.")
        ->call('grantBadge', 'pair_animateur')
        ->assertDispatched('toast', type: 'error');

    Livewire::test(UsersScreen::class)->call('open', $peer->id)->call('grantBadge', 'pair_animateur');

    expect(UserBadge::query()->where('user_id', $member->id)->pluck('badge')->map->value->all())->toBe(['membre_verifie'])
        ->and(UserBadge::query()->where('user_id', $peer->id)->value('granted_by'))->toBe($this->admin->id)
        ->and(AuditLog::query()->where('action', 'badges.granted')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'badges.granted')->first()->detail)->toContain('badge=membre_verifie');

    Livewire::test(UsersScreen::class)->call('open', $member->id)->call('revokeBadge', 'membre_verifie')
        ->assertDispatched('toast', type: 'info');
    expect(UserBadge::query()->where('user_id', $member->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'badges.revoked')->exists())->toBeTrue();
});

it('refuse les badges sans badges.manage, sur son propre compte et sur un compte non actif', function (): void {
    $member = p18Member('Bea', ['created_at' => now()->subYear()]);

    expect(fn () => app(VerifiedBadges::class)->grant($member, VerifiedBadge::MemberVerified, p18Member('Modo2', [], 'moderateur')))->toThrow(AuthorizationException::class)
        ->and(fn () => app(VerifiedBadges::class)->grant($this->admin, VerifiedBadge::MemberVerified, $this->admin))->toThrow(AuthorizationException::class);

    $pending = p18Member('Attente', ['status' => UserStatus::Pending]);
    expect(VerifiedBadges::eligible($pending, VerifiedBadge::MemberVerified))->toBeFalse();
});

it('affiche les badges sur le profil et à côté du nom dans le fil, jamais pour un contenu anonyme ni après retrait du rôle pair', function (): void {
    $peer = F::member('pair');
    $viewer = F::member();
    app(VerifiedBadges::class)->grant($peer, VerifiedBadge::PeerFacilitator, $this->admin);
    Post::factory()->for($peer)->create(['body' => 'Atelier samedi']);
    Post::factory()->for($peer)->anonymous()->create(['body' => 'Témoignage anonyme']);

    F::as($viewer)->getJson('/api/v1/members/'.$peer->pseudo)
        ->assertOk()->assertJsonPath('data.badges.0.key', 'pair_animateur')->assertJsonPath('data.badges.0.label', 'Pair animateur·rice');

    $feed = F::as($viewer)->getJson('/api/v1/feed')->assertOk()->json('data');
    $signed = collect($feed)->firstWhere('body', 'Atelier samedi');
    $anonymous = collect($feed)->firstWhere('body', 'Témoignage anonyme');
    expect($signed['author']['badges'][0]['key'] ?? null)->toBe('pair_animateur')
        ->and($anonymous['author']['badges'] ?? null)->toBe([]);

    $peer->syncRoles(['membre']);
    expect(VerifiedBadges::for($peer->refresh()))->toBe([]);
});
