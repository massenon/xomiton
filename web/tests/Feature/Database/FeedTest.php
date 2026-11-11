<?php

declare(strict_types=1);

use App\Enums\GroupPrivacy;
use App\Enums\GroupRole;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Enums\PostStatus;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Comment;
use App\Models\Follow;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\Page;
use App\Models\PageFollower;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\PostSave;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
 * Lot 4 « feed » : une création par factory et une assertion métier par table
 * (DATABASE_DICTIONARY.md §4). Nécessite l'intégration des lots pour RefreshDatabase.
 */

describe('groups', function (): void {
    it('génère un ULID, caste la confidentialité et impose un slug unique', function (): void {
        $group = Group::factory()->private()->create(['slug' => 'cercle-des-soeurs']);

        expect($group->ulid)->toHaveLength(26)
            ->and($group->privacy)->toBe(GroupPrivacy::Private)
            ->and($group->privacy->label())->toBe('Privé')
            ->and($group->isPrivate())->toBeTrue()
            ->and($group->isNational())->toBeTrue()
            ->and(Group::query()->whereUlid($group->ulid)->exists())->toBeTrue();

        expect(fn () => Group::factory()->create(['slug' => 'cercle-des-soeurs']))->toThrow(QueryException::class);
    });

    it('filtre les groupes publics, actifs et nationaux ou de la zone', function (): void {
        Group::factory()->create();
        Group::factory()->private()->create();
        $zoned = Group::factory()->inZone()->create();
        Group::factory()->inactive()->create();

        expect(Group::query()->public()->count())->toBe(3)
            ->and(Group::query()->active()->count())->toBe(3)
            ->and(Group::query()->forZone(null)->count())->toBe(3)
            ->and(Group::query()->forZone($zoned->zone_id)->count())->toBe(4)
            ->and(GroupPrivacy::fromLabel('Privé'))->toBe(GroupPrivacy::Private);
    });
});

describe('pages', function (): void {
    it('crée une page avec ULID et slug unique, et filtre les pages vérifiées', function (): void {
        $page = Page::factory()->verified()->create(['slug' => 'xomiton-page-officielle', 'category' => 'Organisation']);
        Page::factory()->inactive()->create();

        expect($page->ulid)->toHaveLength(26)
            ->and($page->is_verified)->toBeTrue()
            ->and(Page::query()->verified()->count())->toBe(1)
            ->and(Page::query()->active()->inCategory('Organisation')->count())->toBe(1);

        expect(fn () => Page::factory()->create(['slug' => 'xomiton-page-officielle']))->toThrow(QueryException::class);
    });
});

describe('posts', function (): void {
    it('crée une publication datée avec ULID, enums castés et compteurs à zéro', function (): void {
        $post = Post::factory()->tagged(PostTag::Testimony)->create();

        expect($post->ulid)->toHaveLength(26)
            ->and($post->published_at)->not->toBeNull()
            ->and($post->tag)->toBe(PostTag::Testimony)
            ->and($post->tag->label())->toBe('Témoignage')
            ->and($post->visibility)->toBe(PostVisibility::Public)
            ->and($post->status)->toBe(PostStatus::Published)
            ->and($post->likes_count)->toBe(0)
            ->and($post->authorLabel())->toBe($post->user->pseudo)
            ->and(PostTag::fromLabel('Événement'))->toBe(PostTag::Event);
    });

    it('cache l’auteur·rice d’une publication anonyme et le motif de masquage', function (): void {
        $anonymous = Post::factory()->anonymous()->create();
        $hidden = Post::factory()->hidden('Propos haineux')->create();

        expect($anonymous->is_anonymous)->toBeTrue()
            ->and($anonymous->authorLabel())->toBe('Membre anonyme')
            ->and($hidden->status)->toBe(PostStatus::Hidden)
            ->and($hidden->hidden_reason)->toBe('Propos haineux')
            ->and($hidden->toArray())->not->toHaveKeys(['hidden_reason', 'reports_count'])
            ->and(Post::query()->published()->count())->toBe(1);
    });

    it('applique la visibilité : public, abonné·es, moi uniquement', function (): void {
        $viewer = User::factory()->create();
        $author = User::factory()->create();
        $stranger = User::factory()->create();

        Post::factory()->for($author)->create();
        Post::factory()->for($author)->visibility(PostVisibility::Followers)->create();
        Post::factory()->for($author)->visibility(PostVisibility::Me)->create();
        Post::factory()->for($stranger)->visibility(PostVisibility::Followers)->create();
        Post::factory()->for($viewer)->visibility(PostVisibility::Me)->create();
        Post::factory()->hidden()->create();

        expect(Post::query()->visibleTo(null)->count())->toBe(1)
            ->and(Post::query()->visibleTo($viewer)->count())->toBe(2);

        Follow::factory()->create(['follower_id' => $viewer->id, 'followed_id' => $author->id]);

        expect(Post::query()->visibleTo($viewer)->count())->toBe(3)
            ->and(Post::query()->visibleTo($author)->count())->toBe(3);
    });

    it('supprime logiquement et détache l’auteur·rice sans perdre la publication', function (): void {
        $post = Post::factory()->create();

        $post->user->forceDelete();

        expect($post->fresh()->user_id)->toBeNull();

        $post->delete();

        expect(Post::query()->count())->toBe(0)
            ->and(Post::withTrashed()->count())->toBe(1);
    });
});

describe('post_media', function (): void {
    it('attache des médias ordonnés et les supprime avec la publication', function (): void {
        $post = Post::factory()->withImage()->create();
        PostMedia::factory()->video()->create(['post_id' => $post->id, 'position' => 1]);

        expect($post->media)->toHaveCount(2)
            ->and($post->media->first()->isImage())->toBeTrue()
            ->and(PostMedia::query()->images()->count())->toBe(1);

        $post->forceDelete();

        expect(PostMedia::query()->count())->toBe(0);
    });
});

describe('post_likes', function (): void {
    it('n’autorise qu’un seul « j’aime » par membre et par publication', function (): void {
        $like = PostLike::factory()->create();

        expect($like->created_at)->not->toBeNull()
            ->and($like->post->likes()->count())->toBe(1);

        expect(fn () => PostLike::factory()->create(['post_id' => $like->post_id, 'user_id' => $like->user_id]))
            ->toThrow(QueryException::class);
    });
});

describe('post_saves', function (): void {
    it('n’enregistre une publication qu’une fois par membre', function (): void {
        $save = PostSave::factory()->create();

        expect($save->created_at)->not->toBeNull()
            ->and($save->user)->toBeInstanceOf(User::class);

        expect(fn () => PostSave::factory()->create(['post_id' => $save->post_id, 'user_id' => $save->user_id]))
            ->toThrow(QueryException::class);
    });
});

describe('comments', function (): void {
    it('crée un commentaire, une réponse en cascade et filtre les publiés de premier niveau', function (): void {
        $comment = Comment::factory()->anonymous()->create();
        $reply = Comment::factory()->replyTo($comment)->create();
        Comment::factory()->hidden()->create(['post_id' => $comment->post_id]);

        expect($comment->is_anonymous)->toBeTrue()
            ->and($reply->isReply())->toBeTrue()
            ->and($reply->post_id)->toBe($comment->post_id)
            ->and($comment->replies)->toHaveCount(1)
            ->and(Comment::query()->published()->topLevel()->count())->toBe(1);

        $comment->forceDelete();

        expect(Comment::query()->count())->toBe(1);
    });
});

describe('polls', function (): void {
    it('lie un sondage unique à une publication « Sondage » avec 2 à 6 options', function (): void {
        $post = Post::factory()->poll(4)->create();
        $poll = $post->poll;

        expect($post->isPoll())->toBeTrue()
            ->and($poll)->toBeInstanceOf(Poll::class)
            ->and($poll->options)->toHaveCount(4)
            ->and($poll->options->first()->label)->toBe('Santé sexuelle et reproductive')
            ->and($poll->isOpen())->toBeTrue()
            ->and(Poll::query()->open()->count())->toBe(1);

        expect(fn () => Poll::factory()->create(['post_id' => $post->id]))->toThrow(QueryException::class);
    });

    it('ferme le sondage à l’échéance ou à la clôture et montre alors les résultats', function (): void {
        $ended = Poll::factory()->ended()->create();
        $closed = Poll::factory()->closed()->create();
        $open = Poll::factory()->create();

        expect($ended->isOpen())->toBeFalse()
            ->and($closed->isOpen())->toBeFalse()
            ->and(Poll::query()->open()->count())->toBe(1)
            ->and($ended->canShowResultsTo(null))->toBeTrue()
            ->and($open->canShowResultsTo(null))->toBeFalse();
    });
});

describe('poll_options', function (): void {
    it('refuse deux options de même libellé dans un sondage et calcule le pourcentage', function (): void {
        $option = PollOption::factory()->create(['label' => 'Sécurité numérique', 'votes_count' => 3]);
        $option->poll->update(['votes_count' => 4]);

        expect($option->percentage())->toBe(75.0);

        expect(fn () => PollOption::factory()->create(['poll_id' => $option->poll_id, 'label' => 'Sécurité numérique']))
            ->toThrow(QueryException::class);
    });
});

describe('poll_votes', function (): void {
    it('enregistre un vote par option et par membre, avec le sondage dénormalisé', function (): void {
        $vote = PollVote::factory()->create();

        expect($vote->poll_id)->toBe($vote->option->poll_id)
            ->and($vote->created_at)->not->toBeNull()
            ->and($vote->poll->hasVoted($vote->user))->toBeTrue()
            ->and(PollVote::query()->forVoter($vote->poll, $vote->user)->count())->toBe(1);

        expect(fn () => PollVote::factory()->forOption($vote->option)->create(['user_id' => $vote->user_id]))
            ->toThrow(QueryException::class);
    });
});

describe('follows', function (): void {
    it('crée un abonnement unique et le retrouve par scope', function (): void {
        $follow = Follow::factory()->create();

        expect(Follow::query()->between($follow->follower_id, $follow->followed_id)->exists())->toBeTrue()
            ->and(Follow::query()->followersOf($follow->followed_id)->count())->toBe(1);

        expect(fn () => Follow::factory()->create([
            'follower_id' => $follow->follower_id,
            'followed_id' => $follow->followed_id,
        ]))->toThrow(QueryException::class);
    });
});

describe('invitations', function (): void {
    it('caste le statut, filtre les invitations en attente et interdit le doublon', function (): void {
        $pending = Invitation::factory()->create();
        $accepted = Invitation::factory()->accepted()->create(['to_user_id' => $pending->to_user_id]);

        expect($pending->status)->toBe(InvitationStatus::Pending)
            ->and($pending->isPending())->toBeTrue()
            ->and($accepted->responded_at)->not->toBeNull()
            ->and($accepted->status->badge())->toBe('success')
            ->and(Invitation::query()->receivedBy($pending->to_user_id)->pending()->count())->toBe(1);

        expect(fn () => Invitation::factory()->create([
            'from_user_id' => $pending->from_user_id,
            'to_user_id' => $pending->to_user_id,
        ]))->toThrow(QueryException::class);
    });
});

describe('group_members', function (): void {
    it('gère rôles et statuts d’adhésion et n’admet qu’une adhésion par membre', function (): void {
        $group = Group::factory()->private()->create();
        $admin = GroupMember::factory()->admin()->create(['group_id' => $group->id]);
        GroupMember::factory()->create(['group_id' => $group->id]);
        GroupMember::factory()->pending()->create(['group_id' => $group->id]);
        GroupMember::factory()->banned()->create(['group_id' => $group->id]);

        expect($admin->role)->toBe(GroupRole::Admin)
            ->and($admin->canModerate())->toBeTrue()
            ->and($admin->status)->toBe(MembershipStatus::Active)
            ->and($group->members()->count())->toBe(2)
            ->and($group->memberships()->pending()->count())->toBe(1)
            ->and($group->memberships()->moderators()->count())->toBe(1);

        expect(fn () => GroupMember::factory()->create(['group_id' => $group->id, 'user_id' => $admin->user_id]))
            ->toThrow(QueryException::class);
    });
});

describe('page_followers', function (): void {
    it('n’abonne un·e membre qu’une fois à une page et suit la suppression de la page', function (): void {
        $follower = PageFollower::factory()->create();

        expect($follower->created_at)->not->toBeNull()
            ->and($follower->page->followers()->count())->toBe(1);

        expect(fn () => PageFollower::factory()->create(['page_id' => $follower->page_id, 'user_id' => $follower->user_id]))
            ->toThrow(QueryException::class);

        $follower->page->forceDelete();

        expect(PageFollower::query()->count())->toBe(0);
    });
});
