<?php

declare(strict_types=1);

use App\Application\Account\PersonalDataExport;
use App\Enums\GroupPrivacy;
use App\Enums\PostVisibility;
use App\Models\Comment;
use App\Models\DatabaseNotification;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostSave;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\ReactionGrouped;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeedFixtures;

/*
 * Revue du lot fil-domaine-api : oracle d'anonymat (blocage, abonné·es : RG-02, Q72), « Enregistrées » et groupes
 * privés (FR-SOC-05), mention « modifiée », réponses orphelines, cloche regroupée par personnes distinctes, export RGPD
 * des masquages, lien signé des images, limiteur du PATCH.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reader = FeedFixtures::member();
    $this->author = FeedFixtures::member();
});

function privacyBlock(User $by, User $target): void
{
    UserBlock::query()->create(['user_id' => $by->id, 'blocked_user_id' => $target->id]);
}

/**
 * @return list<string|int>
 */
function privacyIds(User $viewer, string $uri): array
{
    return array_column(FeedFixtures::as($viewer)->getJson($uri)->assertOk()->json('data'), 'id');
}

describe('Oracle d’anonymat', function (): void {
    it('ne fait pas de « bloquer » un test : la publication anonyme d’un compte bloqué reste lisible', function (): void {
        $anonymous = Post::factory()->for($this->author)->anonymous()->create();
        $named = Post::factory()->for($this->author)->create();
        privacyBlock($this->reader, $this->author);

        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$anonymous->ulid)->assertOk()
            ->assertJsonPath('data.author.id', null)->assertJsonPath('data.author.anonymous', true);
        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$named->ulid)->assertNotFound();

        expect(privacyIds($this->reader, '/api/v1/feed'))->toContain($anonymous->ulid)->not->toContain($named->ulid);
    });

    it('garde le blocage posé par l’auteur·rice sur ses contenus anonymes', function (): void {
        $anonymous = Post::factory()->for($this->author)->anonymous()->create();
        privacyBlock($this->author, $this->reader);

        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$anonymous->ulid)->assertNotFound();
        expect(privacyIds($this->reader, '/api/v1/feed'))->not->toContain($anonymous->ulid);
    });

    it('liste le commentaire anonyme d’un compte bloqué, sans identifiant ni pseudo', function (): void {
        $post = Post::factory()->for(FeedFixtures::member())->create();
        $anonymous = Comment::factory()->for($post)->for($this->author)->anonymous()->create(['body' => 'Je te crois.']);
        $named = Comment::factory()->for($post)->for($this->author)->create();
        privacyBlock($this->reader, $this->author);

        $response = FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid.'/comments')->assertOk();

        expect(array_column($response->json('data'), 'id'))->toBe([$anonymous->id])->not->toContain($named->id)
            ->and($response->json('data.0.author'))->toMatchArray(['id' => null, 'pseudo' => __('feed.post.anonymous_author'), 'role' => null, 'anonymous' => true])
            ->and($response->json('data.0.is_anonymous'))->toBeTrue()
            ->and($response->json('data.0.is_mine'))->toBeFalse()
            ->and($response->getContent())->not->toContain($this->author->pseudo);
    });

    it('refuse anonyme + abonné·es à la publication et à la modification (422)', function (): void {
        FeedFixtures::as($this->author)->postJson('/api/v1/posts', [
            'body' => 'Ce que j’ai vécu.', 'is_anonymous' => true, 'visibility' => PostVisibility::Followers->value,
        ])->assertUnprocessable()->assertJsonValidationErrors(['visibility' => __('feed.api.anonymous_followers')]);
        expect(Post::query()->count())->toBe(0);

        $post = Post::factory()->for($this->author)->anonymous()->create();
        FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['visibility' => PostVisibility::Followers->value])
            ->assertUnprocessable()->assertJsonValidationErrors('visibility');
        expect($post->refresh()->visibility)->toBe(PostVisibility::Public);

        FeedFixtures::as($this->author)->postJson('/api/v1/posts', [
            'body' => 'Pour moi seule.', 'is_anonymous' => true, 'visibility' => PostVisibility::Me->value,
        ])->assertCreated();
    });

    it('traite une ancienne publication anonyme « abonné·es » comme « moi » : suivre ne révèle rien', function (): void {
        $legacy = Post::factory()->for($this->author)->anonymous()->visibility(PostVisibility::Followers)->create();
        FeedFixtures::follow($this->reader, $this->author);

        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$legacy->ulid)->assertNotFound();
        expect(privacyIds($this->reader, '/api/v1/feed'))->not->toContain($legacy->ulid);
        FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$legacy->ulid)->assertOk();
    });
});

describe('Enregistrées', function (): void {
    it('applique la règle de groupe du détail : un groupe privé quitté disparaît des enregistrées', function (): void {
        $private = Group::factory()->private()->create();
        $public = Group::factory()->create(['privacy' => GroupPrivacy::Public, 'is_active' => true]);
        $membership = GroupMember::factory()->create(['group_id' => $private->id, 'user_id' => $this->reader->id]);
        $inPrivate = Post::factory()->for($this->author)->inGroup($private)->create(['published_at' => now()->subMinutes(2)]);
        $inPublic = Post::factory()->for($this->author)->inGroup($public)->create(['published_at' => now()->subMinute()]);

        foreach ([$inPrivate, $inPublic] as $post) {
            PostSave::query()->create(['post_id' => $post->id, 'user_id' => $this->reader->id]);
        }

        expect(privacyIds($this->reader, '/api/v1/feed/saved'))->toBe([$inPublic->ulid, $inPrivate->ulid]);

        $membership->delete();

        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$inPrivate->ulid)->assertNotFound();
        expect(privacyIds($this->reader, '/api/v1/feed/saved'))->toBe([$inPublic->ulid]);
    });
});

describe('Mention « modifiée »', function (): void {
    it('reste fausse après un j’aime ou un commentaire, devient vraie après une vraie modification', function (): void {
        $post = Post::factory()->for($this->author)->create(['body' => 'Premier jet.', 'published_at' => now()]);
        $this->travel(5)->minutes();

        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/like')->assertOk();
        FeedFixtures::as($this->reader)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Merci pour ça.'])->assertCreated();
        FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid)->assertOk()->assertJsonPath('data.edited', false);

        FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => 'Premier jet.'])
            ->assertOk()->assertJsonPath('data.edited', false);
        FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$post->ulid, ['body' => 'Texte corrigé.'])
            ->assertOk()->assertJsonPath('data.edited', true);
        expect($post->refresh()->edited_at)->not->toBeNull();
    });
});

describe('Commentaires', function (): void {
    it('remonte au premier niveau la réponse à un commentaire supprimé', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $parent = Comment::factory()->for($post)->for($this->author)->create();
        $reply = Comment::factory()->for($post)->for($this->reader)->replyTo($parent)->create();

        $before = FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid.'/comments')->assertOk()->json('data');
        expect(array_column($before, 'parent_id', 'id'))->toBe([$parent->id => null, $reply->id => $parent->id]);

        FeedFixtures::as($this->author)->deleteJson('/api/v1/comments/'.$parent->id)->assertNoContent();

        $after = FeedFixtures::as($this->reader)->getJson('/api/v1/posts/'.$post->ulid.'/comments')->assertOk();
        expect(array_column($after->json('data'), 'parent_id', 'id'))->toBe([$reply->id => null])
            ->and($after->json('meta.comments_count'))->toBe(1);
    });
});

describe('Cloche regroupée', function (): void {
    it('compte des personnes distinctes : retirer puis remettre son j’aime, ou commenter trois fois, compte une fois', function (): void {
        $post = Post::factory()->for($this->author)->create();
        $third = FeedFixtures::member();
        $uri = '/api/v1/posts/'.$post->ulid;

        FeedFixtures::as($this->reader)->postJson($uri.'/like')->assertOk();
        FeedFixtures::as($this->reader)->deleteJson($uri.'/like')->assertOk();
        FeedFixtures::as($this->reader)->postJson($uri.'/like')->assertOk();

        $like = fn () => DatabaseNotification::query()->where('type', ReactionGrouped::class)->where('data->kind', ReactionGrouped::KIND_LIKE)->sole();
        expect($like()->data['count'])->toBe(1)
            ->and($like()->data['body'])->toBe(__('notifications.reaction.liked_one', ['name' => $this->reader->pseudo]));

        FeedFixtures::as($third)->postJson($uri.'/like')->assertOk();
        expect($like()->data['count'])->toBe(2)
            ->and($like()->data['body'])->toBe($third->pseudo.' et 1 autre personne ont aimé ta publication.')
            ->and($like()->data)->not->toHaveKey('actor_ids');

        foreach (['Merci.', 'Vraiment merci.', 'Courage à toi.'] as $body) {
            FeedFixtures::as($this->reader)->postJson($uri.'/comments', ['body' => $body])->assertCreated();
        }

        $comment = DatabaseNotification::query()->where('type', ReactionGrouped::class)->where('data->kind', ReactionGrouped::KIND_COMMENT)->sole();
        expect($comment->data['count'])->toBe(1)
            ->and($comment->data['body'])->toBe(__('notifications.reaction.commented_one', ['name' => $this->reader->pseudo]));
    });
});

it('ajoute les publications masquées (`hides`) à l’export RGPD', function (): void {
    $post = Post::factory()->for($this->author)->create();
    PostHide::query()->create(['post_id' => $post->id, 'user_id' => $this->reader->id]);

    $export = app(PersonalDataExport::class)->collect($this->reader);

    expect($export['hides'])->toHaveCount(1)
        ->and($export['hides'][0]['post_id'])->toBe($post->ulid)
        ->and($export['hides'][0]['created_at'])->not->toBeNull();
});

it('sert l’image par le lien signé, refuse un lien sans signature, altéré ou expiré (403)', function (): void {
    Storage::fake('local');
    Storage::fake('public');

    $url = (string) FeedFixtures::as($this->author)->post('/api/v1/posts', [
        'body' => 'Notre atelier.', 'image' => UploadedFile::fake()->createWithContent('photo.jpg', FeedFixtures::jpegWithExif()),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.media.0.url');

    expect((string) $this->get($url)->assertOk()->headers->get('Content-Type'))->toStartWith('image/');
    $this->get(strtok($url, '?'))->assertForbidden();
    $this->get((string) preg_replace('/signature=([0-9a-f])/', 'signature=x', $url))->assertForbidden();

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
    expect((int) $query['expires'])->toBeGreaterThan(now()->getTimestamp());
    $this->travelTo(CarbonImmutable::createFromTimestamp((int) $query['expires'] + 1));
    $this->get($url)->assertForbidden();
});

it('soumet la modification d’une publication au limiteur feed_write', function (): void {
    expect(Route::getRoutes()->getByName('api.posts.update')?->gatherMiddleware())->toContain('throttle:feed_write');
});
