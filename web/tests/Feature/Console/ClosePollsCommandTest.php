<?php

declare(strict_types=1);

use App\Models\DatabaseNotification;
use App\Models\Poll;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PollEnded;
use Illuminate\Support\Facades\Notification;

/*
 * P7-07 : `polls:close` clôt les sondages échus (une seule fois) et envoie « Sondage terminé » à l'auteur·rice active
 * d'une publication encore publiée ; sondages sans fin, futurs ou déjà clos ignorés.
 */

function pollPost(?User $author = null, array $poll = []): Poll
{
    $post = Post::factory()->for($author ?? User::factory()->create())->poll(3)->create();
    $model = $post->poll()->firstOrFail();
    $model->update($poll);

    return $model->refresh();
}

it('clôt les sondages échus et prévient leur auteur·rice par la cloche', function (): void {
    $author = User::factory()->withProfile()->create();
    $ended = pollPost($author, ['ends_at' => now()->subMinutes(3), 'votes_count' => 12, 'voters_count' => 7]);
    $future = pollPost(poll: ['ends_at' => now()->addDay()]);
    $endless = pollPost(poll: ['ends_at' => null]);
    $already = pollPost(poll: ['ends_at' => now()->subDay(), 'closed_at' => now()->subHours(20)]);

    $this->artisan('polls:close')->expectsOutputToContain('1 sondage clos, 1 notification envoyée')->assertSuccessful();

    expect($ended->refresh()->closed_at?->equalTo($ended->ends_at))->toBeTrue()
        ->and($future->refresh()->closed_at)->toBeNull()
        ->and($endless->refresh()->closed_at)->toBeNull()
        ->and($already->refresh()->closed_at?->diffInMinutes(now()->subHours(20), true))->toBeLessThan(1);

    $notification = DatabaseNotification::query()->where('type', PollEnded::class)->sole();
    expect($notification->notifiable_id)->toBe($author->id)
        ->and($notification->data['title'])->toBe(__('notifications.poll_closed.title'))
        ->and($notification->data['body'])->toContain('7 participant·es')->not->toContain('12')
        ->and($notification->data['voters'])->toBe(7)
        ->and($notification->data['url'])->toEndWith('/fil/'.$ended->post->ulid);

    $this->artisan('polls:close')->expectsOutputToContain('0 sondage clos, 0 notification envoyée')->assertSuccessful();
    expect(DatabaseNotification::query()->count())->toBe(1);
});

it('clôt sans notifier quand la publication est masquée, supprimée ou l’auteur·rice suspendu·e', function (): void {
    Notification::fake();
    $hidden = pollPost(poll: ['ends_at' => now()->subMinute()]);
    $hidden->post->update(['status' => 'masque']);
    $deleted = pollPost(poll: ['ends_at' => now()->subMinute()]);
    $deleted->post->delete();
    $suspended = pollPost(User::factory()->suspended()->create(), ['ends_at' => now()->subMinute()]);

    $this->artisan('polls:close')->expectsOutputToContain('3 sondages clos, 0 notification envoyée')->assertSuccessful();

    expect(Poll::query()->whereNull('closed_at')->count())->toBe(0);
    Notification::assertNothingSent();
});

it('refuse une taille de lot invalide', function (): void {
    $this->artisan('polls:close', ['--chunk' => 0])->assertExitCode(2);
});
