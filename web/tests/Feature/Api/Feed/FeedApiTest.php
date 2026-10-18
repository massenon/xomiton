<?php

declare(strict_types=1);

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Comment;
use App\Models\DatabaseNotification;
use App\Models\Group;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\UserBlock;
use App\Notifications\ReactionGrouped;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures;

/*
 * P7-01 / P7-02 : GET /api/v1/feed (visibilité public · abonné·es · moi, masquées, blocages, groupes, étiquette,
 * curseur), réactions idempotentes (j'aime + cloche regroupée, enregistrer, masquer), commentaires paginés.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reader = FeedFixtures::member();
    $this->author = FeedFixtures::member();
});

/**
 * @return list<string>
 */
function feedIds(array $json): array
{
    return array_column($json['data'], 'id');
}

describe('GET feed', function (): void {
    it('applique la visibilité : communauté, abonné·es suivi·es, « moi » pour soi seulement', function (): void {
        $public = Post::factory()->for($this->author)->create(['published_at' => now()->subMinutes(5)]);
        $followers = Post::factory()->for($this->author)->visibility(PostVisibility::Followers)->create(['published_at' => now()->subMinutes(4)]);
        $me = Post::factory()->for($this->author)->visibility(PostVisibility::Me)->create(['published_at' => now()->subMinutes(3)]);
        $mine = Post::factory()->for($this->reader)->visibility(PostVisibility::Me)->create(['published_at' => now()->subMinutes(2)]);
        Post::factory()->for($this->author)->hidden()->create();
        Post::factory()->for($this->author)->inGroup(Group::factory()->create())->create();

        $ids = feedIds(FeedFixtures::as($this->reader)->getJson('/api/v1/feed')->assertOk()->json());
        expect($ids)->toBe([$mine->ulid, $public->ulid]);

        FeedFixtures::follow($this->reader, $this->author);
        $ids = feedIds(FeedFixtures::as($this->reader)->getJson('/api/v1/feed')->assertOk()->json());
        expect($ids)->toBe([$mine->ulid, $followers->ulid, $public->ulid])->not->toContain($me->ulid);
    });

    it('retire les publications masquées par la personne et celles des comptes bloqués dans les deux sens', function (): void {
        $kept = Post::factory()->for($this->author)->create();
        $hiddenByMe = Post::factory()->for($this->author)->create();
        $blocked = FeedFixtures::member();
        $blocker = FeedFixtures::member();
        $fromBlocked = Post::factory()->for($blocked)->create();
        $fromBlocker = Post::factory()->for($blocker)->create();
        UserBlock::query()->create(['user_id' => $this->reader->id, 'blocked_user_id' => $blocked->id]);
        UserBlock::query()->create(['user_id' => $blocker->id, 'blocked_user_id' => $this->reader->id]);

        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$hiddenByMe->ulid.'/hide')->assertOk()->assertJsonPath('data.hidden', true);

        $ids = feedIds(FeedFixtures::as($this->reader)->getJson('/api/v1/feed')->json());
        expect($ids)->toContain($kept->ulid)->not->toContain($hiddenByMe->ulid)->not->toContain($fromBlocked->ulid)->not->toContain($fromBlocker->ulid);
        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$fromBlocker->ulid)->assertNotFound();

        FeedFixtures::as($this->reader)->deleteJson('/api/v1/posts/'.$hiddenByMe->ulid.'/hide')->assertOk()->assertJsonPath('data.changed', true);
        expect(feedIds(FeedFixtures::as($this->reader)->getJson('/api/v1/feed')->json()))->toContain($hiddenByMe->ulid);
    });

    it('filtre par étiquette et refuse une étiquette inconnue (422)', function (): void {
        $poll = Post::factory()->for($this->author)->poll()->create();
        Post::factory()->for($this->author)->tagged(PostTag::Question)->create();

        FeedFixtures::as($this->reader)->getJson('/api/v1/feed?tag=sondage')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $poll->ulid)->assertJsonPath('meta.tag', 'sondage');
        FeedFixtures::as($this->reader)->getJson('/api/v1/feed?tag=friends')->assertUnprocessable()->assertJsonValidationErrors('tag');
    });

    it('pagine par curseur, plus récentes d’abord, sans doublon ni oubli', function (): void {
        $posts = collect(range(1, 5))->map(fn (int $i): Post => Post::factory()->for($this->author)->create(['published_at' => now()->subMinutes(10 - $i)]));

        $first = FeedFixtures::as($this->reader)->getJson('/api/v1/feed?per_page=2')->assertOk()
            ->assertJsonPath('meta.per_page', 2)->assertJsonStructure(['meta' => ['next_cursor', 'prev_cursor'], 'links' => ['next', 'prev']]);
        $cursor = (string) $first->json('meta.next_cursor');
        $second = FeedFixtures::as($this->reader)->getJson('/api/v1/feed?per_page=2&cursor='.urlencode($cursor))->assertOk();
        $third = FeedFixtures::as($this->reader)->getJson('/api/v1/feed?per_page=2&cursor='.urlencode((string) $second->json('meta.next_cursor')))->assertOk();

        expect([...feedIds($first->json()), ...feedIds($second->json()), ...feedIds($third->json())])
            ->toBe($posts->reverse()->pluck('ulid')->values()->all())
            ->and($third->json('meta.next_cursor'))->toBeNull();
    });

    it('rend l’état propre à la personne qui lit : j’aime, enregistrée', function (): void {
        $post = Post::factory()->for($this->author)->create();
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk();
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/save')->assertOk();

        FeedFixtures::as($this->reader)->getJson('/api/v1/feed')->assertJsonPath('data.0.liked', true)->assertJsonPath('data.0.saved', true)
            ->assertJsonPath('data.0.likes_count', 1);
        FeedFixtures::as($this->author)->getJson('/api/v1/feed')->assertJsonPath('data.0.liked', false)->assertJsonPath('data.0.saved', false);

        FeedFixtures::as($this->reader)->getJson('/api/v1/feed/saved')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $post->ulid);
        FeedFixtures::as($this->author)->getJson('/api/v1/feed/saved')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('Réactions', function (): void {
    it('aime de façon idempotente : un seul j’aime, compteur juste, retrait sans effet en double', function (): void {
        $post = Post::factory()->for($this->author)->create();

        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk()
            ->assertJsonPath('data', ['liked' => true, 'likes_count' => 1, 'changed' => true]);
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk()
            ->assertJsonPath('data', ['liked' => true, 'likes_count' => 1, 'changed' => false]);
        expect(PostLike::query()->count())->toBe(1);

        FeedFixtures::as($this->reader)->deleteJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk()
            ->assertJsonPath('data', ['liked' => false, 'likes_count' => 0, 'changed' => true]);
        FeedFixtures::as($this->reader)->deleteJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk()
            ->assertJsonPath('data.likes_count', 0)->assertJsonPath('data.changed', false);
    });

    it('regroupe la cloche de l’auteur·rice : une notification non lue par publication, jamais pour soi', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $third = FeedFixtures::member();

        FeedFixtures::as($this->author)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk();
        expect(DatabaseNotification::query()->count())->toBe(0);

        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk();
        FeedFixtures::as($third)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk();

        $notification = DatabaseNotification::query()->where('type', ReactionGrouped::class)->sole();
        expect($notification->notifiable_id)->toBe($this->author->id)
            ->and($notification->data['count'])->toBe(2)
            ->and($notification->data['body'])->toBe($third->pseudo.' et 1 autre personne ont aimé ta publication.')
            ->and($notification->data['url'])->toEndWith('/fil/'.$post->ulid);
    });

    it('répond 404 pour réagir à une publication invisible', function (string $method, string $suffix): void {
        $post = Post::factory()->for($this->author)->visibility(PostVisibility::Me)->create();

        FeedFixtures::as($this->reader)->json($method, '/api/v1/posts/'.$post->ulid.$suffix)->assertNotFound()->assertJsonPath('code', 'not_found');
    })->with([
        ['POST', '/like'], ['DELETE', '/like'], ['POST', '/save'], ['POST', '/hide'], ['GET', '/comments'], ['POST', '/report'],
    ]);
});

describe('Commentaires', function (): void {
    it('commente (201), répond à un commentaire de la publication et tient le compteur', function (): void {
        $post = Post::factory()->for($this->author)->create();

        $first = FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Merci pour ce partage.'])
            ->assertCreated()->assertJsonPath('data.author.pseudo', $this->reader->pseudo)->assertJsonPath('meta.comments_count', 1);
        FeedFixtures::as($this->author)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Avec plaisir !', 'parent_id' => $first->json('data.id')])
            ->assertCreated()->assertJsonPath('data.parent_id', $first->json('data.id'));

        expect($post->refresh()->comments_count)->toBe(2);
        $notification = DatabaseNotification::query()->where('notifiable_id', $this->author->id)->sole();
        expect($notification->data['kind'])->toBe(ReactionGrouped::KIND_COMMENT);
        expect(DatabaseNotification::query()->where('notifiable_id', $this->reader->id)->sole()->data['kind'])->toBe(ReactionGrouped::KIND_REPLY);
    });

    it('refuse un commentaire vide, bloqué ou une réponse à une autre publication (422)', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $foreign = Comment::factory()->create();
        FeedFixtures::badWord('tarée');

        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => ''])->assertUnprocessable()->assertJsonValidationErrors('body');
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Quelle tarée'])
            ->assertUnprocessable()->assertJsonPath('code', 'content_blocked');
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Réponse', 'parent_id' => $foreign->id])
            ->assertUnprocessable()->assertJsonPath('errors.parent_id.0', __('feed.api.parent_invalid'));
        expect($post->refresh()->comments_count)->toBe(0);
    });

    it('liste les commentaires publiés du plus ancien au plus récent, par curseur', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $old = Comment::factory()->for($post)->create(['created_at' => now()->subHour()]);
        $recent = Comment::factory()->for($post)->create(['created_at' => now()]);
        Comment::factory()->for($post)->hidden()->create();

        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid.'/comments?per_page=1')->assertOk()
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $old->id)->assertJsonPath('meta.per_page', 1);
        $all = FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid.'/comments')->assertOk();
        expect(array_column($all->json('data'), 'id'))->toBe([$old->id, $recent->id]);
    });

    it('supprime son commentaire (204) et recalcule le compteur, jamais celui d’une autre personne (403)', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $comment = FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'À supprimer'])->json('data.id');

        FeedFixtures::as($this->author)->deleteJson('/api/v1/comments/'.$comment)->assertForbidden();
        FeedFixtures::as($this->reader)->deleteJson('/api/v1/comments/'.$comment)->assertNoContent();
        expect($post->refresh()->comments_count)->toBe(0);
        FeedFixtures::as($this->reader)->deleteJson('/api/v1/comments/999999')->assertNotFound();
    });
});
