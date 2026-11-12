<?php

declare(strict_types=1);

use App\Application\Moderation\SpamDetector;
use App\Enums\ContentReportReason;
use App\Enums\PostStatus;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Post;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Tests\Support\FeedFixtures as F;

/*
 * P18-07 : détection du spam par fréquence et doublons (seuils en config), mise en quarantaine par le masquage existant
 * et signalement « Spam » à la modération ; jamais pour les messages privés ni pour l'équipe de modération ; « Approuver »
 * rend le contenu visible (couvert par ModerationPriorityAndActionsTest).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['xomiton.moderation.spam' => [
        'enabled' => true, 'window_minutes' => 10, 'max_per_window' => 4,
        'duplicate_hours' => 24, 'duplicate_threshold' => 3, 'duplicate_min_length' => 15,
    ]]);
    $this->author = F::member();
});

function spamPost(string $body): Post
{
    $id = F::as(test()->author)->postJson('/api/v1/posts', ['body' => $body])->assertCreated()->json('data.id');

    return Post::query()->where('ulid', $id)->firstOrFail();
}

it('met en quarantaine le contenu qui franchit le seuil de fréquence et le signale (motif Spam), sans texte au journal', function (): void {
    foreach (range(1, 3) as $i) {
        expect(spamPost("Publication numéro {$i} sur le fil")->status)->toBe(PostStatus::Published);
    }

    $fourth = spamPost('Publication numéro 4 sur le fil');

    expect($fourth->refresh()->status)->toBe(PostStatus::Hidden);
    $report = ModerationReport::query()->forSubject($fourth)->firstOrFail();
    expect($report->reason)->toBe(ContentReportReason::Spam->label())
        ->and($report->reporter_user_id)->toBeNull()
        ->and($report->details)->toContain('Fréquence')
        ->and($report->isPending())->toBeTrue();

    $log = AuditLog::query()->where('action', 'moderation.spam_quarantined')->firstOrFail();
    expect($log->detail)->toBe('signal=frequence ; nombre=4')->and($log->actor)->toBe(AuditLog::ACTOR_SYSTEM);
});

it('repère les doublons (même texte sans casse ni ponctuation) au seuil, et laisse passer les textes courts', function (): void {
    config(['xomiton.moderation.spam.max_per_window' => 50]);

    spamPost('Gagne de l’argent facile ici !');
    spamPost('gagne de l’argent FACILE ici');
    $third = spamPost('Gagne de l’argent, facile, ici...');

    expect($third->refresh()->status)->toBe(PostStatus::Hidden)
        ->and(ModerationReport::query()->forSubject($third)->value('details'))->toContain('Doublons');

    foreach (range(1, 4) as $i) {
        expect(spamPost('Merci !')->refresh()->status)->toBe(PostStatus::Published);
    }
});

it('surveille aussi les réponses de forum, jamais l’équipe de modération', function (): void {
    $this->seed(ForumsSeeder::class);
    config(['xomiton.moderation.spam.max_per_window' => 3]);
    $topic = ForumTopic::factory()->create(['forum_id' => Forum::query()->forKey('f1')->value('id')]);

    foreach (range(1, 3) as $i) {
        F::as($this->author)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => "Réponse {$i} au sujet"])->assertCreated();
    }

    expect(ForumReply::query()->where('user_id', $this->author->id)->latest('id')->first()?->status)->toBe(PostStatus::Hidden);

    $moderator = F::member('moderateur');
    foreach (range(1, 4) as $i) {
        F::as($moderator)->postJson('/api/v1/forums/topics/'.$topic->ulid.'/replies', ['body' => 'Rappel de la charte du forum'])->assertCreated();
    }

    expect(ForumReply::query()->where('user_id', $moderator->id)->where('status', PostStatus::Hidden->value)->count())->toBe(0);
});

it('ne lit ni ne compte jamais les messages privés et respecte l’interrupteur de configuration', function (): void {
    $conversation = Conversation::factory()->create();
    Message::factory()->count(10)->create(['conversation_id' => $conversation->id, 'user_id' => $this->author->id, 'body' => 'Même message privé répété']);

    expect(spamPost('Une seule publication publique')->refresh()->status)->toBe(PostStatus::Published);

    config(['xomiton.moderation.spam.enabled' => false, 'xomiton.moderation.spam.max_per_window' => 2]);
    spamPost('Deuxième publication');
    expect(spamPost('Troisième publication')->refresh()->status)->toBe(PostStatus::Published);
});

it('normalise le texte comparé (casse, accents conservés, ponctuation et espaces)', function (): void {
    expect(SpamDetector::normalize("  Bonjour,   À TOUTES !\n"))->toBe('bonjour à toutes');
});
