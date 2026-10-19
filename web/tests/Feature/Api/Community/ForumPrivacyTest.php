<?php

declare(strict_types=1);

use App\Application\Account\PersonalDataExport;
use App\Models\DatabaseNotification;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\ForumReplyPosted;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures as F;

/*
 * P8-05, critère de la ligne du plan : « Sujet anonyme affiché “Membre anonyme” sans fuite de l'auteur dans l'API
 * (RG-02) ». Mêmes règles que le fil (tests/Feature/Api/Feed/FeedPrivacyTest.php, Q72) : aucun oracle d'anonymat —
 * ni par le blocage, ni par la recherche, ni par la cloche, ni par la liste, ni par la fiche ; l'auteur·rice elle-même
 * ne reçoit pas d'identifiant (`is_mine` suffit).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ForumsSeeder::class);
    $this->reader = F::member();
    $this->author = F::member();
    $this->forumId = (int) Forum::query()->forKey('f1')->value('id');
});

function forumBlock(User $by, User $target): void
{
    UserBlock::query()->create(['user_id' => $by->id, 'blocked_user_id' => $target->id]);
}

/**
 * @return list<string|int>
 */
function forumIds(User $viewer, string $uri): array
{
    return array_column(F::as($viewer)->getJson($uri)->assertOk()->json('data'), 'id');
}

describe('Sujet anonyme', function (): void {
    it('affiche « Membre anonyme » sans identifiant, pseudo, rôle ni avatar personnel, dans la liste et la fiche', function (): void {
        $topic = ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId]);

        foreach (['/api/v1/forums/topics', '/api/v1/forums/topics/'.$topic->ulid] as $uri) {
            $response = F::as($this->reader)->getJson($uri)->assertOk();
            $author = $uri === '/api/v1/forums/topics' ? $response->json('data.0.author') : $response->json('data.author');

            expect($author)->toMatchArray(['id' => null, 'pseudo' => 'Membre anonyme', 'role' => null, 'anonymous' => true, 'badge' => null])
                ->and($author['avatar_url'])->toContain('personnage-1.png')
                ->and($response->getContent())->not->toContain($this->author->pseudo)
                ->and($response->getContent())->not->toContain('"user_id"');
        }
    });

    it('ne donne aucun identifiant à l’auteur·rice non plus : `is_mine` suffit', function (): void {
        $topic = ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId]);

        F::as($this->author)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()
            ->assertJsonPath('data.author.id', null)->assertJsonPath('data.is_mine', true)->assertJsonPath('data.can.delete', true);
        F::as($this->reader)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk()
            ->assertJsonPath('data.is_mine', false)->assertJsonPath('data.can.delete', false);
    });

    it('ne trouve pas un sujet anonyme en cherchant le pseudo de son auteur·rice', function (): void {
        ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId, 'title' => 'Une question', 'body' => 'Un message assez long.']);

        expect(forumIds($this->reader, '/api/v1/forums/topics?q='.urlencode($this->author->pseudo)))->toBe([]);
    });
});

describe('Oracle d’anonymat par le blocage', function (): void {
    it('ne fait pas de « bloquer » un test : le sujet anonyme d’un compte bloqué reste lisible, le sujet signé disparaît', function (): void {
        $anonymous = ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId]);
        $named = ForumTopic::factory()->for($this->author)->create(['forum_id' => $this->forumId]);
        forumBlock($this->reader, $this->author);

        F::as($this->reader)->getJson('/api/v1/forums/topics/'.$anonymous->ulid)->assertOk()->assertJsonPath('data.author.id', null);
        F::as($this->reader)->getJson('/api/v1/forums/topics/'.$named->ulid)->assertNotFound();
        expect(forumIds($this->reader, '/api/v1/forums/topics'))->toBe([$anonymous->ulid]);
    });

    it('garde le blocage posé par l’auteur·rice sur ses sujets anonymes', function (): void {
        $anonymous = ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId]);
        forumBlock($this->author, $this->reader);

        F::as($this->reader)->getJson('/api/v1/forums/topics/'.$anonymous->ulid)->assertNotFound();
        F::as($this->reader)->postJson('/api/v1/forums/topics/'.$anonymous->ulid.'/replies', ['body' => 'Bonjour.'])->assertNotFound();
        expect(forumIds($this->reader, '/api/v1/forums/topics'))->toBe([]);
    });

    it('liste la réponse anonyme d’un compte bloqué sans identifiant ni pseudo, retire la réponse signée', function (): void {
        $topic = ForumTopic::factory()->for(F::member())->create(['forum_id' => $this->forumId]);
        $anonymous = ForumReply::factory()->forTopic($topic)->for($this->author)->anonymous()->create(['body' => 'Je te crois.']);
        $named = ForumReply::factory()->forTopic($topic)->for($this->author)->create();
        forumBlock($this->reader, $this->author);

        $response = F::as($this->reader)->getJson('/api/v1/forums/topics/'.$topic->ulid.'/replies')->assertOk();

        expect(array_column($response->json('data'), 'id'))->toBe([$anonymous->id])->not->toContain($named->id)
            ->and($response->json('data.0.author'))->toMatchArray(['id' => null, 'pseudo' => 'Membre anonyme', 'role' => null, 'anonymous' => true])
            ->and($response->json('data.0.is_mine'))->toBeFalse()
            ->and($response->getContent())->not->toContain($this->author->pseudo);
    });
});

describe('Cloche', function (): void {
    it('prévient l’auteur·rice du sujet d’une réponse anonyme sans jamais nommer ni identifier qui a répondu', function (): void {
        $topic = ForumTopic::factory()->for($this->reader)->create(['forum_id' => $this->forumId]);

        F::as($this->author)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Courage.', 'is_anonymous' => true])->assertCreated();

        $notification = DatabaseNotification::query()->where('type', ForumReplyPosted::class)->sole();
        expect($notification->notifiable_id)->toBe($this->reader->id)
            ->and(json_encode($notification->data, JSON_UNESCAPED_UNICODE))->not->toContain($this->author->pseudo)
            ->and(array_keys($notification->data))->not->toContain('user_id')->not->toContain('actor_id')->not->toContain('author_id');
    });

    it('laisse un compte qui bloque l’auteur·rice d’un sujet anonyme y répondre ; la réponse, invisible pour elle, ne lui est pas notifiée', function (): void {
        $topic = ForumTopic::factory()->for($this->reader)->anonymous()->create(['forum_id' => $this->forumId]);
        forumBlock($this->author, $this->reader);

        F::as($this->author)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk();
        F::as($this->author)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Une réponse.', 'is_anonymous' => true])->assertCreated();

        expect(DatabaseNotification::query()->where('type', ForumReplyPosted::class)->count())->toBe(0)
            ->and(forumIds($this->reader, '/api/v1/forums/topics/'.$topic->ulid.'/replies'))->toBe([]);
    });
});

it('garde les sujets et réponses anonymes dans l’export RGPD de leur auteur·rice', function (): void {
    $topic = ForumTopic::factory()->for($this->author)->anonymous()->create(['forum_id' => $this->forumId]);
    ForumReply::factory()->forTopic($topic)->for($this->author)->anonymous()->create();

    $export = app(PersonalDataExport::class)->collect($this->author);

    expect($export['forum_topics'])->toHaveCount(1)
        ->and($export['forum_replies'])->toHaveCount(1);
});
