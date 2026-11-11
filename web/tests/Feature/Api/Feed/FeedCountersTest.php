<?php

declare(strict_types=1);

use App\Application\Feed\FeedCounters;
use App\Models\Comment;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\User;

/*
 * P7-01 : compteurs dénormalisés — décrément jamais sous zéro, recalcul depuis les tables de liaison après une dérive
 * (j'aime, commentaires publiés, votes par option, total et participant·es d'un sondage).
 */

it('ne descend jamais un compteur sous zéro', function (): void {
    $post = Post::factory()->create(['likes_count' => 0]);

    app(FeedCounters::class)->decrement($post, 'likes_count');

    expect($post->refresh()->likes_count)->toBe(0);
});

it('recalcule j’aime, commentaires et sondage depuis les tables de liaison', function (): void {
    $post = Post::factory()->poll(3)->create(['likes_count' => 40, 'comments_count' => 9]);
    PostLike::factory()->count(2)->create(['post_id' => $post->id]);
    Comment::factory()->for($post)->create();
    Comment::factory()->for($post)->hidden()->create();
    $poll = $post->poll()->with('options')->firstOrFail();
    [$a, $b] = [$poll->options[0], $poll->options[1]];
    $voter = User::factory()->create();
    PollVote::query()->insert([
        ['poll_option_id' => $a->id, 'poll_id' => $poll->id, 'user_id' => $voter->id, 'created_at' => now()],
        ['poll_option_id' => $b->id, 'poll_id' => $poll->id, 'user_id' => $voter->id, 'created_at' => now()],
        ['poll_option_id' => $a->id, 'poll_id' => $poll->id, 'user_id' => User::factory()->create()->id, 'created_at' => now()],
    ]);
    $poll->update(['votes_count' => 99, 'voters_count' => 99]);

    $counters = app(FeedCounters::class);
    $counters->recountLikes($post);
    $counters->recountComments($post);
    $counters->recountPoll($poll);

    expect($post->refresh()->likes_count)->toBe(2)
        ->and($post->comments_count)->toBe(1)
        ->and($poll->refresh()->votes_count)->toBe(3)
        ->and($poll->voters_count)->toBe(2)
        ->and($a->refresh()->votes_count)->toBe(2)
        ->and($b->refresh()->votes_count)->toBe(1);
});
