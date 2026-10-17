<?php

declare(strict_types=1);

use App\Enums\PostVisibility;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Event;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use Tests\Feature\Authorization\Support\RoleUsers;

/*
 * Réseau social (ROLE_ET_PERMISSIONS.md §3 « Réseau social », §4) : tous les rôles connectés participent ;
 * l'auteur·rice gère ses contenus ; posts.moderate / moderation.manage modèrent ; la messagerie reste privée.
 */

beforeEach(function (): void {
    RoleUsers::seedRoles();
    $this->users = RoleUsers::all();
});

describe('PostPolicy', function (): void {
    it('ouvre le fil et la publication à tous les rôles', function (): void {
        RoleUsers::assertAbility($this->users, 'viewAny', Post::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'create', Post::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', Post::factory()->create(), RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'like', Post::factory()->create(), RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'comment', Post::factory()->create(), RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'report', Post::factory()->create(), RoleUsers::ROLES);
    });

    it('respecte la visibilité : abonné·es et « moi seulement »', function (): void {
        $author = User::factory()->create();
        $followers = Post::factory()->for($author)->visibility(PostVisibility::Followers)->create();
        $me = Post::factory()->for($author)->visibility(PostVisibility::Me)->create();
        $hidden = Post::factory()->for($author)->hidden()->create();

        RoleUsers::assertAbility($this->users, 'view', $followers, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $me, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $hidden, ['moderateur', 'admin', 'superadmin']);

        Follow::query()->create(['follower_id' => $this->users['membre']->id, 'followed_id' => $author->id]);
        expect($this->users['membre']->can('view', $followers))->toBeTrue()
            ->and($this->users['membre']->can('view', $me))->toBeFalse()
            ->and($author->can('view', $me))->toBeTrue();
    });

    it('laisse l’auteur·rice modifier et la modération masquer ou supprimer', function (): void {
        $mine = fn (User $me): Post => Post::factory()->for($me)->create();
        $other = Post::factory()->create();

        RoleUsers::assertAbility($this->users, 'update', $mine, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'delete', $mine, RoleUsers::ROLES);
        // Gate::before : le superadmin passe outre les garde-fous « contenu personnel » (sauf sur son propre compte).
        RoleUsers::assertAbility($this->users, 'report', $mine, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $other, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $other, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'moderate', $other, ['moderateur', 'admin', 'superadmin']);
    });
});

describe('CommentPolicy', function (): void {
    it('applique les mêmes règles aux commentaires', function (): void {
        $mine = fn (User $me): Comment => Comment::factory()->for($me)->create();
        $other = Comment::factory()->create();
        $hidden = Comment::factory()->hidden()->create();

        RoleUsers::assertAbility($this->users, 'create', Comment::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $other, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $hidden, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $mine, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'update', $other, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $other, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'moderate', $other, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'report', $mine, ['superadmin']);
    });

    it('ne rend pas un commentaire plus visible que sa publication', function (): void {
        $author = User::factory()->create();
        $onPrivate = Comment::factory()->for(Post::factory()->for($author)->visibility(PostVisibility::Me)->create())->create();
        $onFollowers = Comment::factory()->for(Post::factory()->for($author)->visibility(PostVisibility::Followers)->create())->create();
        $onHidden = Comment::factory()->for(Post::factory()->for($author)->hidden()->create())->create();

        RoleUsers::assertAbility($this->users, 'view', $onPrivate, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $onFollowers, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $onHidden, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'report', $onPrivate, ['moderateur', 'admin', 'superadmin']);

        Follow::query()->create(['follower_id' => $this->users['membre']->id, 'followed_id' => $author->id]);
        expect($this->users['membre']->can('view', $onFollowers))->toBeTrue()
            ->and($this->users['membre']->can('view', $onPrivate))->toBeFalse()
            ->and($onPrivate->user?->can('view', $onPrivate))->toBeTrue();
    });
});

describe('GroupPolicy', function (): void {
    it('montre les groupes publics, cache les privés aux non-membres', function (): void {
        $public = Group::factory()->create();
        $private = Group::factory()->private()->create();
        $inactive = Group::factory()->inactive()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', Group::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'create', Group::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $public, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'join', $public, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $private, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $inactive, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'join', $inactive, ['superadmin']);

        GroupMember::factory()->for($private)->for($this->users['membre'])->create();
        expect($this->users['membre']->can('view', $private))->toBeTrue()
            ->and($this->users['membre']->can('post', $private))->toBeTrue()
            ->and($this->users['membre']->can('leave', $private))->toBeTrue()
            ->and($this->users['membre']->can('join', $private))->toBeFalse()
            ->and($this->users['pair']->can('post', $private))->toBeFalse();
    });

    it('confie la gestion au propriétaire, aux admins du groupe et à la modération', function (): void {
        $owned = fn (User $me): Group => Group::factory()->for($me, 'owner')->create();
        $other = Group::factory()->create();

        RoleUsers::assertAbility($this->users, 'update', $owned, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'delete', $owned, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'moderate', $owned, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'leave', $owned, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $other, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $other, ['moderateur', 'admin', 'superadmin']);

        GroupMember::factory()->for($other)->for($this->users['membre'])->moderator()->create();
        GroupMember::factory()->for($other)->for($this->users['pair'])->admin()->create();
        expect($this->users['membre']->can('moderate', $other))->toBeTrue()
            ->and($this->users['membre']->can('update', $other))->toBeFalse()
            ->and($this->users['pair']->can('update', $other))->toBeTrue()
            ->and($this->users['pair']->can('delete', $other))->toBeFalse();
    });
});

describe('EventPolicy', function (): void {
    it('laisse l’organisateur·rice gérer et la modération intervenir', function (): void {
        $mine = fn (User $me): Event => Event::factory()->for($me, 'organizer')->create();
        $other = Event::factory()->create();
        $draft = Event::factory()->draft()->create();

        RoleUsers::assertAbility($this->users, 'viewAny', Event::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'create', Event::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $other, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'rsvp', $other, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $draft, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'rsvp', $draft, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $mine, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'delete', $mine, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'update', $other, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $other, ['moderateur', 'admin', 'superadmin']);
    });
});

describe('ConversationPolicy et MessagePolicy', function (): void {
    it('réserve une conversation à ses participant·es, même face à l’administration', function (): void {
        $others = User::factory()->count(2)->create();
        $conversation = Conversation::factory()->withParticipants($others->all())->create(['created_by' => $others[0]->id]);
        $message = Message::factory()->in($conversation, $others[1])->create();

        RoleUsers::assertAbility($this->users, 'viewAny', Conversation::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'create', Conversation::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'create', Message::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'view', $conversation, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'sendMessage', $conversation, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'manage', $conversation, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $message, ['superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $message, ['superadmin']);

        expect($others[1]->can('view', $conversation))->toBeTrue()
            ->and($others[1]->can('sendMessage', $conversation))->toBeFalse() // sans rôle : pas de social.access
            ->and($others[1]->can('manage', $conversation))->toBeFalse()
            ->and($others[0]->can('manage', $conversation))->toBeTrue()
            ->and($others[1]->can('view', $message))->toBeTrue()
            ->and($others[1]->can('delete', $message))->toBeTrue()
            ->and($others[0]->can('delete', $message))->toBeTrue()
            ->and($others[1]->can('report', $message))->toBeFalse()
            ->and($others[0]->can('report', $message))->toBeTrue();
    });

    it('ferme la conversation à qui l’a quittée et ouvre l’écriture aux participant·es du réseau', function (): void {
        $member = $this->users['membre'];
        $pair = $this->users['pair'];
        $conversation = Conversation::factory()->withParticipants([$member, $pair])->create(['created_by' => $member->id]);

        expect($pair->can('view', $conversation))->toBeTrue()
            ->and($pair->can('sendMessage', $conversation))->toBeTrue()
            ->and($pair->can('leave', $conversation))->toBeTrue();

        $conversation->participants()->where('user_id', $pair->id)->update(['left_at' => now()]);

        expect($pair->can('view', $conversation))->toBeFalse()
            ->and($pair->can('sendMessage', $conversation))->toBeFalse()
            ->and($member->can('view', $conversation))->toBeTrue();
    });
});

describe('ModerationReportPolicy', function (): void {
    it('laisse tout le monde signaler et la modération traiter', function (): void {
        $report = ModerationReport::factory()->create();
        $mine = fn (User $me): ModerationReport => ModerationReport::factory()->reportedBy($me)->create();

        RoleUsers::assertAbility($this->users, 'create', ModerationReport::class, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'viewAny', ModerationReport::class, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $report, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'view', $mine, RoleUsers::ROLES);
        RoleUsers::assertAbility($this->users, 'handle', $report, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'update', $report, ['moderateur', 'admin', 'superadmin']);
        RoleUsers::assertAbility($this->users, 'delete', $report, ['moderateur', 'admin', 'superadmin']);
    });
});
