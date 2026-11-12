<?php

declare(strict_types=1);

use App\Application\Community\Forums\RemoveForumContent;
use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures as F;

/*
 * P18-03 (Q97) : réponse validée d'un sujet de forum — l'auteur·rice du sujet la marque, la modération peut aussi la
 * valider ou la retirer ; une autre personne ne le peut pas ; la réponse doit être une réponse publiée du sujet ; champ
 * `accepted_reply_id` / `is_solved` / `can.accept` dans l'API ; journal pour la modération seulement ; une réponse
 * supprimée n'est plus validée.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ForumsSeeder::class);
    $this->author = F::member();
    $this->helper = F::member();
    $this->other = F::member();
    $this->topic = ForumTopic::factory()->for($this->author)->create(['forum_id' => Forum::query()->forKey('f1')->value('id')]);
    $this->reply = ForumReply::factory()->for($this->helper)->create(['topic_id' => $this->topic->id]);
});

function acceptUrl(ForumTopic $topic): string
{
    return '/api/v1/forums/topics/'.$topic->ulid.'/accepted-reply';
}

it('laisse l’auteur·rice du sujet valider une réponse : sujet résolu, sans ligne au journal', function (): void {
    F::as($this->author)->postJson(acceptUrl($this->topic), ['reply_id' => $this->reply->id])
        ->assertOk()
        ->assertJsonPath('data.accepted_reply_id', $this->reply->id)
        ->assertJsonPath('data.is_solved', true)
        ->assertJsonPath('data.can.accept', true);

    $topic = $this->topic->refresh();
    expect($topic->accepted_reply_id)->toBe($this->reply->id)
        ->and($topic->accepted_by)->toBe($this->author->id)
        ->and($topic->accepted_at)->not->toBeNull()
        ->and($topic->isSolved())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'like', 'forum.reply_%accepted%')->count())->toBe(0);

    F::as($this->other)->getJson('/api/v1/forums/topics/'.$this->topic->ulid)
        ->assertOk()->assertJsonPath('data.is_solved', true)->assertJsonPath('data.can.accept', false);
});

it('refuse la validation à une autre personne (403) et la réponse d’un autre sujet ou masquée (422)', function (): void {
    F::as($this->other)->postJson(acceptUrl($this->topic), ['reply_id' => $this->reply->id])->assertForbidden();
    F::as($this->helper)->deleteJson(acceptUrl($this->topic))->assertForbidden();

    $elsewhere = ForumReply::factory()->create();
    F::as($this->author)->postJson(acceptUrl($this->topic), ['reply_id' => $elsewhere->id])
        ->assertUnprocessable()->assertJsonPath('code', 'reply_not_acceptable');

    $hidden = ForumReply::factory()->for($this->helper)->create(['topic_id' => $this->topic->id, 'status' => PostStatus::Hidden]);
    F::as($this->author)->postJson(acceptUrl($this->topic), ['reply_id' => $hidden->id])
        ->assertUnprocessable()->assertJsonPath('code', 'reply_not_acceptable');

    F::as($this->author)->postJson(acceptUrl($this->topic), [])->assertUnprocessable();

    expect($this->topic->refresh()->accepted_reply_id)->toBeNull();
});

it('laisse la modération valider puis retirer la validation, journalisé sans texte', function (): void {
    $moderator = F::member('moderateur');

    F::as($moderator)->postJson(acceptUrl($this->topic), ['reply_id' => $this->reply->id])->assertOk()->assertJsonPath('data.is_solved', true);
    F::as($moderator)->deleteJson(acceptUrl($this->topic))->assertOk()->assertJsonPath('data.is_solved', false)->assertJsonPath('data.accepted_reply_id', null);

    $logs = AuditLog::query()->whereIn('action', ['forum.reply_accepted_by_moderation', 'forum.reply_unaccepted_by_moderation'])->get();
    expect($logs)->toHaveCount(2)
        ->and($logs->every(fn (AuditLog $log): bool => ! str_contains((string) $log->detail, (string) $this->reply->body)))->toBeTrue();
});

it('remplace la réponse validée par une autre et retire la validation quand la réponse est supprimée', function (): void {
    $second = ForumReply::factory()->for($this->other)->create(['topic_id' => $this->topic->id]);

    F::as($this->author)->postJson(acceptUrl($this->topic), ['reply_id' => $this->reply->id])->assertOk();
    F::as($this->author)->postJson(acceptUrl($this->topic), ['reply_id' => $second->id])->assertOk()->assertJsonPath('data.accepted_reply_id', $second->id);

    app(RemoveForumContent::class)->reply($second, $this->other);

    expect($this->topic->refresh()->accepted_reply_id)->toBeNull();
});

it('retire la validation de façon idempotente et refuse un membre sans accès au réseau', function (): void {
    F::as($this->author)->deleteJson(acceptUrl($this->topic))->assertOk()->assertJsonPath('data.is_solved', false);
    F::as(User::factory()->create())->postJson(acceptUrl($this->topic), ['reply_id' => $this->reply->id])->assertNotFound();
});
