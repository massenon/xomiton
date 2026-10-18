<?php

declare(strict_types=1);

use App\Enums\BadWordSeverity;
use App\Enums\GroupRole;
use App\Enums\MembershipStatus;
use App\Enums\ProfileVisibility;
use App\Enums\UserStatus;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DatabaseNotification;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\UserBlock;
use App\Models\Zone;
use App\Notifications\GroupJoinAccepted;
use App\Notifications\GroupJoinRequested;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\CommunityFixtures as C;
use Tests\Support\FeedFixtures as F;

/*
 * P8-02, docs/API.md §13 : créer un groupe (modale groups.php l.111-116), listes Découvrir / Mes groupes, visibilité
 * des groupes privés (FR-SOC-05, Q75 : absents des listes des non-membres, fiche réduite, publications et membres
 * introuvables), parcours créer → rejoindre → quitter, demandes d'adhésion (GroupJoinRequested / GroupJoinAccepted),
 * membres (profils visibles seulement), rôles, publications du groupe par l'API du fil, discussion du groupe (P8-01).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
    $this->cyn = F::member();
});

describe('création', function (): void {
    it('crée un groupe : 201 + Location, propriétaire admin active, un membre, zone facultative', function (): void {
        $zone = Zone::factory()->create(['name' => 'Zou']);

        $response = F::as($this->awa)->postJson('/api/v1/groups', [
            'name' => '  Cercle de Bohicon  ', 'description' => 'Écoute et entraide', 'privacy' => 'prive', 'zone_id' => $zone->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Cercle de Bohicon')
            ->assertJsonPath('data.privacy', 'prive')
            ->assertJsonPath('data.privacy_label', 'Privé')
            ->assertJsonPath('data.zone.name', 'Zou')
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.is_owner', true)
            ->assertJsonPath('data.restricted', false)
            ->assertJsonPath('data.membership.status', 'actif')
            ->assertJsonPath('data.membership.role', 'admin')
            ->assertJsonPath('data.can.post', true)
            ->assertJsonPath('data.can.moderate', true)
            ->assertJsonPath('data.pending_requests', 0);

        $group = Group::query()->where('ulid', $response->json('data.id'))->firstOrFail();
        expect($response->headers->get('Location'))->toEndWith('/groupes/'.$group->ulid)
            ->and($group->owner_id)->toBe($this->awa->id)
            ->and(GroupMember::query()->where('group_id', $group->id)->where('user_id', $this->awa->id)->value('role'))->toBe(GroupRole::Admin);
    });

    it('refuse un nom de moins de 3 caractères, une confidentialité inconnue, un mot bloqué, un e-mail non confirmé', function (): void {
        F::as($this->awa)->postJson('/api/v1/groups', ['name' => 'ab', 'privacy' => 'public'])
            ->assertUnprocessable()->assertJsonPath('errors.name.0', 'Le nom du groupe doit contenir au moins 3 caractères.');
        F::as($this->awa)->postJson('/api/v1/groups', ['name' => 'Cercle', 'privacy' => 'secret'])
            ->assertUnprocessable()->assertJsonValidationErrors('privacy');
        F::badWord('injure');
        F::as($this->awa)->postJson('/api/v1/groups', ['name' => 'Cercle', 'description' => 'une injure', 'privacy' => 'public'])
            ->assertUnprocessable()->assertJsonPath('code', 'content_blocked')->assertJsonValidationErrors('description');

        F::as(F::member(verified: false))->postJson('/api/v1/groups', ['name' => 'Cercle', 'privacy' => 'public'])->assertForbidden();
        expect(Group::query()->count())->toBe(0);
    });
});

describe('visibilité (FR-SOC-05)', function (): void {
    it('liste les groupes publics et ceux dont on est membre, jamais les privés des autres', function (): void {
        $public = C::group($this->awa, ['name' => 'Cercle public']);
        $private = C::group($this->awa, ['name' => 'Cercle privé', 'privacy' => 'prive']);
        $mine = C::group($this->bea, ['name' => 'Mon cercle privé', 'privacy' => 'prive']);
        Group::factory()->inactive()->create(['name' => 'Cercle fermé']);

        $names = fn ($response) => collect($response->json('data'))->pluck('name')->sort()->values()->all();

        expect($names(F::as($this->bea)->getJson('/api/v1/groups')->assertOk()->assertJsonPath('meta.tab', 'discover')))
            ->toBe(['Cercle public', 'Mon cercle privé'])
            ->and($names(F::as($this->cyn)->getJson('/api/v1/groups?q=privé')))->toBe([])
            ->and($names(F::as($this->awa)->getJson('/api/v1/groups?tab=mine')))->toBe(['Cercle privé', 'Cercle public']);

        C::join($this->cyn, $private)->assertOk()->assertJsonPath('data.membership.status', 'demande');
        expect($names(F::as($this->cyn)->getJson('/api/v1/groups?tab=mine')))->toBe(['Cercle privé'])
            ->and($names(F::as($this->cyn)->getJson('/api/v1/groups')))->toBe(['Cercle public']);
        F::as($this->cyn)->getJson('/api/v1/groups?tab=autre')->assertUnprocessable();
        expect($public->is_active && $mine->isPrivate())->toBeTrue();
    });

    it('ne montre d’un groupe privé à une non-membre qu’une fiche réduite ; publications, membres et discussion introuvables', function (): void {
        $group = C::group($this->awa, ['name' => 'Cercle privé', 'description' => 'Réservé', 'privacy' => 'prive']);
        C::postIn($this->awa, $group, 'Message du cercle')->assertCreated();

        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid)->assertOk()
            ->assertJsonPath('data.restricted', true)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.membership', null)
            ->assertJsonPath('data.pending_requests', null)
            ->assertJsonPath('data.can.join', true)
            ->assertJsonPath('data.can.post', false);
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/posts')->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/members')->assertNotFound();
        F::as($this->bea)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertForbidden();
        C::postIn($this->bea, $group)->assertUnprocessable()->assertJsonPath('errors.group.0', 'Tu dois être membre du groupe pour faire cela.');

        $post = Post::query()->where('group_id', $group->id)->firstOrFail();
        F::as($this->bea)->getJson('/api/v1/posts/'.$post->ulid)->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/feed')->assertJsonCount(0, 'data');

        F::as($this->awa)->getJson('/api/v1/groups/'.$group->ulid)->assertOk()
            ->assertJsonPath('data.restricted', false)->assertJsonPath('data.description', 'Réservé');
    });

    it('rend 404 pour un groupe inactif ou supprimé, sauf à la modération', function (): void {
        $inactive = Group::factory()->inactive()->create();
        $deleted = Group::factory()->create();
        $deleted->delete();

        F::as($this->bea)->getJson('/api/v1/groups/'.$inactive->ulid)->assertNotFound();
        F::as($this->bea)->getJson('/api/v1/groups/'.$deleted->ulid)->assertNotFound();
        C::join($this->bea, $inactive)->assertNotFound();
        F::as(F::member('moderateur'))->getJson('/api/v1/groups/'.$inactive->ulid)->assertOk();
    });
});

describe('adhésion', function (): void {
    it('parcours créer → rejoindre → publier → quitter un groupe public', function (): void {
        $group = C::group($this->awa);

        C::join($this->bea, $group)->assertOk()
            ->assertJsonPath('data.membership.status', 'actif')
            ->assertJsonPath('data.membership.role', 'membre')
            ->assertJsonPath('data.members_count', 2)
            ->assertJsonPath('data.can.leave', true)
            ->assertJsonPath('data.can.join', false);
        C::join($this->bea, $group)->assertOk()->assertJsonPath('data.members_count', 2);

        C::postIn($this->bea, $group)->assertCreated();
        expect($group->fresh()->posts_count)->toBe(1);

        C::leave($this->bea, $group)->assertOk()
            ->assertJsonPath('data.membership', null)
            ->assertJsonPath('data.members_count', 1)
            ->assertJsonPath('data.can.join', true);
        C::leave($this->bea, $group)->assertNotFound();
        C::postIn($this->bea, $group)->assertUnprocessable()->assertJsonValidationErrors('group');
    });

    it('retient la dernière admin ; la propriétaire qui part cède la propriété à une autre admin', function (): void {
        $group = C::group($this->awa);

        C::leave($this->awa, $group)->assertUnprocessable()->assertJsonPath('code', 'group_last_admin');

        C::addMember($group, $this->bea, GroupRole::Admin);
        C::leave($this->awa, $group)->assertOk();
        expect($group->fresh()->owner_id)->toBe($this->bea->id);
    });

    it('groupe privé : demande notifiée une fois à l’équipe, acceptée par la propriétaire, notifiée à la personne', function (): void {
        Notification::fake();
        $group = C::group($this->awa, ['name' => 'Cercle privé', 'privacy' => 'prive']);
        C::addMember($group, $this->cyn, GroupRole::Admin);
        $moderator = F::member();
        C::addMember($group, $moderator, GroupRole::Moderator);

        C::join($this->bea, $group)->assertOk()
            ->assertJsonPath('data.membership.status', 'demande')
            ->assertJsonPath('data.can.cancel_request', true)
            ->assertJsonPath('data.restricted', true);
        C::join($this->bea, $group)->assertOk();

        Notification::assertSentToTimes($this->awa, GroupJoinRequested::class, 1);
        Notification::assertSentToTimes($this->cyn, GroupJoinRequested::class, 1);
        Notification::assertNotSentTo($moderator, GroupJoinRequested::class);
        Notification::assertSentTo($this->awa, GroupJoinRequested::class, function (GroupJoinRequested $notification): bool {
            return $notification->url === route('groupes.show', ['group' => Group::query()->firstOrFail()->ulid, 'tab' => 'members'])
                && $notification->requesterName === $this->bea->pseudo;
        });

        F::as($this->awa)->getJson('/api/v1/groups/'.$group->ulid.'/members?status=requested')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $this->bea->id)
            ->assertJsonPath('data.0.can.accept', true)
            ->assertJsonPath('meta.pending_requests', 1);
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/posts')->assertNotFound();

        F::as($this->awa)->postJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id.'/accept')->assertOk()
            ->assertJsonPath('data.members_count', 4)
            ->assertJsonPath('data.pending_requests', 0);
        Notification::assertSentTo($this->bea, GroupJoinAccepted::class);
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/posts')->assertOk();
        F::as($this->awa)->postJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id.'/accept')->assertNotFound();
    });

    it('refuse une demande, annule la sienne ; une membre ordinaire ne voit pas les demandes', function (): void {
        $group = C::group($this->awa, ['privacy' => 'prive']);
        C::addMember($group, $this->cyn);
        C::join($this->bea, $group)->assertOk();

        F::as($this->cyn)->getJson('/api/v1/groups/'.$group->ulid.'/members?status=requested')->assertForbidden();
        F::as($this->cyn)->deleteJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id)->assertForbidden();
        F::as($this->awa)->deleteJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id)->assertOk()
            ->assertJsonPath('data.members_count', 2);
        expect(GroupMember::query()->where('user_id', $this->bea->id)->exists())->toBeFalse();

        C::join($this->bea, $group)->assertOk();
        C::leave($this->bea, $group)->assertOk()->assertJsonPath('data.membership', null);
        expect(GroupMember::query()->where('user_id', $this->bea->id)->exists())->toBeFalse();
    });

    it('refuse de rejoindre un groupe dont on est exclue', function (): void {
        $group = C::group($this->awa);
        C::addMember($group, $this->bea, GroupRole::Member, MembershipStatus::Banned);

        C::join($this->bea, $group)->assertForbidden();
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid)->assertOk()
            ->assertJsonPath('data.membership', null)->assertJsonPath('data.can.join', false);
    });
});

describe('membres et rôles', function (): void {
    it('ne liste pour une membre que les profils qui lui sont visibles ; l’équipe voit tout le monde', function (): void {
        $group = C::group($this->awa);
        C::addMember($group, $this->bea);
        $private = F::member();
        $private->profile->forceFill(['visibility' => ProfileVisibility::Private])->save();
        C::addMember($group, $private);
        $blocked = F::member();
        C::addMember($group, $blocked);
        UserBlock::query()->create(['user_id' => $blocked->id, 'blocked_user_id' => $this->bea->id]);

        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/members')->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $this->awa->id)
            ->assertJsonPath('data.0.is_owner', true)
            ->assertJsonPath('data.0.role_label', 'Propriétaire')
            ->assertJsonPath('data.1.id', $this->bea->id)
            ->assertJsonPath('data.1.is_me', true)
            ->assertJsonPath('data.1.can.remove', false)
            ->assertJsonPath('meta.hidden_count', 2)
            ->assertJsonPath('meta.pending_requests', null)
            ->assertJsonMissingPath('data.0.email');

        F::as($this->awa)->getJson('/api/v1/groups/'.$group->ulid.'/members')->assertOk()
            ->assertJsonCount(4, 'data')->assertJsonPath('meta.hidden_count', 0)
            ->assertJsonPath('data.1.can.remove', true)->assertJsonPath('data.1.can.make_admin', true);
    });

    it('change les rôles selon la hiérarchie du groupe', function (): void {
        $group = C::group($this->awa);
        C::addMember($group, $this->bea);
        C::addMember($group, $this->cyn);
        $url = fn ($user) => '/api/v1/groups/'.$group->ulid.'/members/'.$user->id;

        F::as($this->awa)->patchJson($url($this->bea), ['role' => 'moderateur'])->assertOk()
            ->assertJsonPath('data.role', 'moderateur')->assertJsonPath('data.role_label', 'Modérateur·rice');
        F::as($this->bea)->patchJson($url($this->cyn), ['role' => 'moderateur'])->assertForbidden();
        F::as($this->bea)->deleteJson($url($this->awa))->assertForbidden()->assertJsonPath('code', 'group_member_not_manageable');
        F::as($this->bea)->deleteJson($url($this->cyn))->assertOk();
        F::as($this->awa)->patchJson($url($this->bea), ['role' => 'chef'])->assertUnprocessable();

        F::as($this->awa)->patchJson($url($this->bea), ['role' => 'admin'])->assertOk()->assertJsonPath('data.role', 'admin');
        F::as($this->bea)->patchJson($url($this->awa), ['role' => 'membre'])->assertForbidden();
        F::as($this->bea)->deleteJson($url($this->bea))->assertNotFound();
    });
});

describe('publications du groupe', function (): void {
    it('publie par POST posts avec `group` : liste du groupe, absente du fil, commentaires réservés aux membres', function (): void {
        $group = C::group($this->awa);
        $id = C::postIn($this->awa, $group, 'Rendez-vous samedi')->assertCreated()->json('data.id');
        C::postIn($this->awa, $group, 'Privé', ['visibility' => 'me'])->assertUnprocessable()->assertJsonValidationErrors('visibility');
        F::as($this->awa)->postJson('/api/v1/posts', ['body' => 'Hors groupe'])->assertCreated();

        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/posts')->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id)
            ->assertJsonPath('data.0.can.comment', false)
            ->assertJsonPath('meta.next_cursor', null);
        F::as($this->bea)->getJson('/api/v1/feed')->assertJsonCount(1, 'data')->assertJsonPath('data.0.body', 'Hors groupe');
        F::as($this->bea)->postJson('/api/v1/posts/'.$id.'/comments', ['body' => 'Coucou'])->assertForbidden();
        F::as($this->awa)->patchJson('/api/v1/posts/'.$id, ['visibility' => 'followers'])->assertUnprocessable()->assertJsonValidationErrors('visibility');

        C::join($this->bea, $group)->assertOk();
        F::as($this->bea)->getJson('/api/v1/groups/'.$group->ulid.'/posts')->assertJsonPath('data.0.can.comment', true);
        F::as($this->bea)->postJson('/api/v1/posts/'.$id.'/comments', ['body' => 'Coucou'])->assertCreated();

        F::as($this->awa)->deleteJson('/api/v1/posts/'.$id)->assertNoContent();
        expect($group->fresh()->posts_count)->toBe(0);
    });
});

describe('discussion du groupe', function (): void {
    it('ouvre une seule conversation rattachée au groupe ; quitter le groupe fait quitter la discussion', function (): void {
        $group = C::group($this->awa);
        C::join($this->bea, $group)->assertOk();

        $id = F::as($this->bea)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertCreated()
            ->assertJsonPath('data.url', route('messages').'?c='.F::as($this->bea)->getJson('/api/v1/conversations')->json('data.0.id'))
            ->json('data.id');
        F::as($this->awa)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertOk()->assertJsonPath('data.id', $id);

        $conversation = Conversation::query()->where('ulid', $id)->firstOrFail();
        expect($conversation->group_id)->toBe($group->id)
            ->and($conversation->title)->toBe('Cercle d’entraide de Bohicon')
            ->and(ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $this->awa->id)->value('role'))->toBe(ConversationParticipant::ROLE_ADMIN);
        F::as($this->bea)->getJson('/api/v1/conversations/'.$id)->assertOk()->assertJsonPath('data.participants_count', 2);
        F::as($this->cyn)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertForbidden();

        C::leave($this->bea, $group)->assertOk();
        F::as($this->bea)->getJson('/api/v1/conversations/'.$id)->assertNotFound();
    });
});

describe('demandes d’adhésion : notifications de l’équipe', function (): void {
    it('annuler puis redemander ne renotifie pas l’équipe avant le délai ; une demande annulée ou tranchée passe à « lue »', function (): void {
        $group = C::group($this->awa, ['name' => 'Cercle privé', 'privacy' => 'prive']);
        $requests = fn () => DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->where('type', GroupJoinRequested::class);

        C::join($this->bea, $group)->assertOk();
        expect($requests()->whereNull('read_at')->count())->toBe(1);

        C::leave($this->bea, $group)->assertOk();
        expect($requests()->whereNull('read_at')->count())->toBe(0);

        C::join($this->bea, $group)->assertOk();
        C::leave($this->bea, $group)->assertOk();
        C::join($this->bea, $group)->assertOk()->assertJsonPath('data.membership.status', 'demande');
        expect($requests()->count())->toBe(1);

        $this->travel(25)->hours();
        C::leave($this->bea, $group)->assertOk();
        C::join($this->bea, $group)->assertOk();
        expect($requests()->count())->toBe(2)->and($requests()->whereNull('read_at')->count())->toBe(1);

        F::as($this->awa)->postJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id.'/accept')->assertOk();
        expect($requests()->whereNull('read_at')->count())->toBe(0);
    });

    it('ne notifie ni une admin qui bloque la personne ou en est bloquée, ni un compte suspendu', function (): void {
        Notification::fake();
        $group = C::group($this->awa, ['privacy' => 'prive']);
        C::addMember($group, $this->cyn, GroupRole::Admin);
        $suspended = F::member();
        C::addMember($group, $suspended, GroupRole::Admin);
        $suspended->forceFill(['status' => UserStatus::Suspended])->save();
        UserBlock::query()->create(['user_id' => $this->awa->id, 'blocked_user_id' => $this->bea->id]);

        C::join($this->bea, $group)->assertOk();
        Notification::assertNotSentTo($this->awa, GroupJoinRequested::class);
        Notification::assertSentToTimes($this->cyn, GroupJoinRequested::class, 1);
        Notification::assertNotSentTo($suspended, GroupJoinRequested::class);

        $eve = F::member();
        UserBlock::query()->create(['user_id' => $eve->id, 'blocked_user_id' => $this->cyn->id]);
        C::join($eve, $group)->assertOk();
        Notification::assertSentToTimes($this->awa, GroupJoinRequested::class, 1);
        Notification::assertSentToTimes($this->cyn, GroupJoinRequested::class, 1);
    });

    it('rend les notifications d’adhésion sensibles : texte neutre en mode discret, sans le nom du groupe', function (): void {
        $this->awa->settings->forceFill(['mask_sensitive_notifications' => true])->save();
        $group = C::group($this->awa, ['name' => 'Cercle Arc-en-ciel', 'privacy' => 'prive']);

        C::join($this->bea, $group)->assertOk();
        $notification = DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->where('type', GroupJoinRequested::class)->firstOrFail();

        expect($notification->is_sensitive)->toBeTrue()
            ->and((string) json_encode($notification->data))->not->toContain('Arc-en-ciel');
    });
});

describe('équipe du groupe', function (): void {
    it('laisse une modératrice du groupe accepter ou refuser une demande', function (): void {
        $group = C::group($this->awa, ['privacy' => 'prive']);
        $moderator = F::member();
        C::addMember($group, $moderator, GroupRole::Moderator);
        C::join($this->bea, $group)->assertOk();
        C::join($this->cyn, $group)->assertOk();

        F::as($moderator)->postJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id.'/accept')->assertOk()
            ->assertJsonPath('data.members_count', 3);
        F::as($moderator)->deleteJson('/api/v1/groups/'.$group->ulid.'/members/'.$this->cyn->id)->assertOk()
            ->assertJsonPath('data.members_count', 3)->assertJsonPath('data.pending_requests', 0);
        expect(GroupMember::query()->where('user_id', $this->cyn->id)->exists())->toBeFalse();
    });

    it('limite les gestes d’adhésion (429, community_write)', function (): void {
        config(['xomiton.rate_limits.community_write_per_minute' => 2]);
        $group = C::group($this->awa);

        C::join($this->bea, $group)->assertOk();
        C::leave($this->bea, $group)->assertOk();
        C::join($this->bea, $group)->assertTooManyRequests();
    });

    it('laisse quitter un groupe désactivé ou y annuler sa demande, sans pouvoir le rejoindre', function (): void {
        $group = C::group($this->awa);
        $private = C::group($this->awa, ['privacy' => 'prive']);
        C::join($this->bea, $group)->assertOk();
        C::join($this->bea, $private)->assertOk();
        Group::query()->whereKey([$group->id, $private->id])->update(['is_active' => false]);

        C::leave($this->bea, $group)->assertOk()->assertJsonPath('data.membership', null);
        C::leave($this->bea, $private)->assertOk()->assertJsonPath('data.membership', null);
        C::leave($this->bea, $group)->assertNotFound();
        C::join($this->bea, $group)->assertNotFound();
        expect(GroupMember::query()->where('user_id', $this->bea->id)->exists())->toBeFalse();
    });
});

describe('discussion du groupe : rôles', function (): void {
    it('garde le rôle posé par la messagerie, suit les rôles du groupe et marque « retirée » une membre retirée', function (): void {
        $group = C::group($this->awa);
        C::join($this->bea, $group)->assertOk();
        $id = F::as($this->bea)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertCreated()->json('data.id');
        $conversation = Conversation::query()->where('ulid', $id)->firstOrFail();
        $participant = fn () => ConversationParticipant::query()->where('conversation_id', $conversation->id)->where('user_id', $this->bea->id)->firstOrFail();
        $url = '/api/v1/groups/'.$group->ulid.'/members/'.$this->bea->id;

        expect($participant()->role)->toBe(ConversationParticipant::ROLE_MEMBER);
        F::as($this->awa)->patchJson($url, ['role' => 'moderateur'])->assertOk();
        expect($participant()->role)->toBe(ConversationParticipant::ROLE_ADMIN);
        F::as($this->awa)->patchJson($url, ['role' => 'membre'])->assertOk();
        expect($participant()->role)->toBe(ConversationParticipant::ROLE_MEMBER);

        $participant()->forceFill(['role' => ConversationParticipant::ROLE_ADMIN])->save();
        F::as($this->bea)->postJson('/api/v1/groups/'.$group->ulid.'/conversation')->assertOk();
        expect($participant()->role)->toBe(ConversationParticipant::ROLE_ADMIN);

        F::as($this->awa)->deleteJson($url)->assertOk();
        expect($participant()->left_at)->not->toBeNull()
            ->and($participant()->left_reason)->toBe(ConversationParticipant::LEFT_REMOVED);
    });
});

describe('création : filtre « à surveiller »', function (): void {
    it('crée le groupe et consigne les termes à surveiller au journal (relecture P12-05)', function (): void {
        F::badWord('louche', BadWordSeverity::Flag);

        F::as($this->awa)->postJson('/api/v1/groups', ['name' => 'Cercle louche', 'privacy' => 'public'])->assertCreated();

        $entry = AuditLog::query()->where('action', 'group.words_flagged')->firstOrFail();
        expect($entry->subject_id)->toBe(Group::query()->value('id'))
            ->and($entry->detail)->toContain('louche');
    });
});

describe('publications d’un groupe privé hors du groupe', function (): void {
    it('restent absentes des enregistrements, du profil et de la page /fil/{ulid} pour qui n’est pas membre', function (): void {
        $group = C::group($this->awa, ['privacy' => 'prive']);
        C::addMember($group, $this->bea);
        $id = C::postIn($this->awa, $group, 'Réservé au cercle')->assertCreated()->json('data.id');
        F::as($this->bea)->postJson('/api/v1/posts/'.$id.'/save')->assertSuccessful();
        F::as($this->bea)->getJson('/api/v1/feed/saved')->assertJsonCount(1, 'data');

        C::leave($this->bea, $group)->assertOk();
        F::as($this->bea)->getJson('/api/v1/feed/saved')->assertOk()->assertJsonCount(0, 'data');
        F::as($this->cyn)->getJson('/api/v1/users/'.$this->awa->id.'/posts')->assertOk()->assertJsonCount(0, 'data');
        $this->actingAs($this->cyn)->get('/fil/'.$id)->assertNotFound();
        $this->actingAs($this->awa)->get('/fil/'.$id)->assertOk();
    });
});
