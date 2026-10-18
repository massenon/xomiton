<?php

declare(strict_types=1);

use App\Domain\Feed\Events\PollVoted;
use App\Enums\PostVisibility;
use App\Models\Poll;
use App\Models\PollVote;
use App\Models\Post;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Support\FeedFixtures;

/*
 * P7-02, FR-SOC-02, RG-09 : POST / DELETE /api/v1/posts/{ulid}/poll/vote — vote, changement de vote, idempotence,
 * retrait, choix multiple, sondage terminé (422), réponses étrangères (422), votes individuels jamais exposés.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = FeedFixtures::member();
    $this->voter = FeedFixtures::member();
    $this->post = Post::factory()->for($this->author)->poll(4)->create();
    $this->poll = $this->post->poll()->with('options')->firstOrFail();
    $this->optionIds = $this->poll->options->pluck('id')->all();
});

function voteUri(Post $post): string
{
    return '/api/v1/posts/'.$post->ulid.'/poll/vote';
}

it('enregistre un vote, montre les résultats après le vote et émet PollVoted', function (): void {
    Event::fake([PollVoted::class]);

    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[1]]])->assertOk()
        ->assertJsonPath('data.has_voted', true)
        ->assertJsonPath('data.my_choices', [$this->optionIds[1]])
        ->assertJsonPath('data.results_visible', true)
        ->assertJsonPath('data.votes_count', 1)
        ->assertJsonPath('data.voters_count', 1)
        ->assertJsonPath('data.options.1.votes_count', 1)
        ->assertJsonPath('data.options.1.percentage', 100)
        ->assertJsonPath('data.options.1.leading', true)
        ->assertJsonPath('data.options.0.leading', false);

    Event::assertDispatched(PollVoted::class, fn (PollVoted $event): bool => $event->pollId === $this->poll->id && $event->optionIds === [$this->optionIds[1]]);
});

it('est idempotent : renvoyer le même vote ne change aucun compteur ni ne réémet PollVoted', function (): void {
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0]]])->assertOk();
    Event::fake([PollVoted::class]);

    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0]]])->assertOk()
        ->assertJsonPath('data.votes_count', 1)->assertJsonPath('data.voters_count', 1);

    expect(PollVote::query()->count())->toBe(1);
    Event::assertNotDispatched(PollVoted::class);
});

it('remplace un vote en choix unique sans compter une personne de plus', function (): void {
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0]]])->assertOk();
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[2]]])->assertOk()
        ->assertJsonPath('data.my_choices', [$this->optionIds[2]])
        ->assertJsonPath('data.votes_count', 1)
        ->assertJsonPath('data.voters_count', 1)
        ->assertJsonPath('data.options.0.votes_count', 0)
        ->assertJsonPath('data.options.2.votes_count', 1);
});

it('accepte plusieurs réponses en choix multiple et les retire toutes', function (): void {
    $this->poll->update(['multiple' => true]);

    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0], $this->optionIds[3]]])->assertOk()
        ->assertJsonPath('data.votes_count', 2)->assertJsonPath('data.voters_count', 1);

    FeedFixtures::as($this->voter)->deleteJson(voteUri($this->post))->assertOk()
        ->assertJsonPath('data.has_voted', false)->assertJsonPath('data.my_choices', [])
        ->assertJsonPath('data.voters_count', 0)->assertJsonPath('data.votes_count', null);
    FeedFixtures::as($this->voter)->deleteJson(voteUri($this->post))->assertOk()->assertJsonPath('data.voters_count', 0);

    expect($this->poll->refresh()->votes_count)->toBe(0)->and(PollVote::query()->count())->toBe(0);
});

it('refuse deux réponses en choix unique, une réponse étrangère ou aucun choix (422)', function (array $payload, string $message): void {
    $foreign = Poll::factory()->withOptions(2)->create()->options()->first();
    $payload = array_map(fn ($choices) => array_map(fn ($c) => $c === 'foreign' ? $foreign->id : $this->optionIds[$c], $choices), $payload);

    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), $payload)
        ->assertUnprocessable()->assertJsonPath('errors.choices.0', __($message));
    expect(PollVote::query()->count())->toBe(0);
})->with([
    'deux réponses' => [['choices' => [0, 1]], 'feed.api.single_choice'],
    'réponse étrangère' => [['choices' => ['foreign']], 'feed.api.choice_unknown'],
    'aucun choix' => [['choices' => []], 'feed.poll.choose_one'],
]);

it('refuse vote et retrait sur un sondage terminé (422 poll_closed) et montre alors les résultats', function (): void {
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0]]])->assertOk();
    $this->poll->update(['ends_at' => now()->subMinute()]);
    $late = FeedFixtures::member();

    FeedFixtures::as($late)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[1]]])
        ->assertUnprocessable()->assertJsonPath('code', 'poll_closed');
    FeedFixtures::as($this->voter)->deleteJson(voteUri($this->post))->assertUnprocessable()->assertJsonPath('code', 'poll_closed');

    FeedFixtures::as($late)->getJson('/api/v1/posts/'.$this->post->ulid)->assertOk()
        ->assertJsonPath('data.poll.closed', true)->assertJsonPath('data.poll.results_visible', true)
        ->assertJsonPath('data.poll.votes_count', 1)->assertJsonPath('data.poll.my_choices', []);
});

it('ne montre les résultats avant le vote que si l’auteur·rice l’a permis', function (): void {
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[0]]])->assertOk();
    $reader = FeedFixtures::member();

    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$this->post->ulid)
        ->assertJsonPath('data.poll.results_visible', false)->assertJsonPath('data.poll.options.0.votes_count', null)
        ->assertJsonPath('data.poll.voters_count', 1);

    $this->poll->update(['results_before_vote' => true]);
    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$this->post->ulid)
        ->assertJsonPath('data.poll.results_visible', true)->assertJsonPath('data.poll.options.0.votes_count', 1);
});

it('n’expose jamais qui a voté quoi (RG-09) : seuls les totaux et ses propres choix sortent', function (): void {
    FeedFixtures::as($this->voter)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[2]]])->assertOk();
    $reader = FeedFixtures::member();
    FeedFixtures::as($reader)->postJson(voteUri($this->post), ['choices' => [$this->optionIds[1]]])->assertOk();

    foreach (['/api/v1/posts/'.$this->post->ulid, '/api/v1/feed'] as $uri) {
        $json = json_encode(FeedFixtures::as($reader)->getJson($uri)->assertOk()->json());

        expect($json)->not->toContain('"user_id"')->not->toContain('"votes":')
            ->not->toContain('"voters":')->not->toContain('"voted_by')->not->toContain((string) $this->voter->pseudo);
    }

    FeedFixtures::as($reader)->getJson('/api/v1/posts/'.$this->post->ulid)->assertJsonPath('data.poll.my_choices', [$this->optionIds[1]]);
    FeedFixtures::as($this->author)->getJson('/api/v1/posts/'.$this->post->ulid)->assertJsonPath('data.poll.my_choices', [])
        ->assertJsonPath('data.poll.voters_count', 2);
});

it('répond 404 sur une publication invisible ou sans sondage', function (): void {
    $private = Post::factory()->for($this->author)->visibility(PostVisibility::Me)->poll()->create();
    $plain = Post::factory()->for($this->author)->create();

    FeedFixtures::as($this->voter)->postJson(voteUri($private), ['choices' => [1]])->assertNotFound();
    FeedFixtures::as($this->voter)->postJson(voteUri($plain), ['choices' => [1]])->assertNotFound()->assertJsonPath('code', 'not_found');
    FeedFixtures::as($this->voter)->deleteJson(voteUri($plain))->assertNotFound();
});
