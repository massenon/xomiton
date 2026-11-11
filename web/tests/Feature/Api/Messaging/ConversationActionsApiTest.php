<?php

declare(strict_types=1);

use App\Domain\Moderation\Events\ContentReported;
use App\Enums\ContentReportReason;
use App\Enums\ModerationKind;
use App\Models\ConversationParticipant;
use App\Models\ModerationReport;
use App\Models\UserBlock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Support\FeedFixtures;
use Tests\Support\MessagingFixtures as M;

/*
 * P8-01, docs/API.md §12.3 : quitter un groupe (un·e admin reste toujours), masquer une conversation privée (elle
 * revient au prochain message), bloquer / débloquer depuis la conversation, signaler (sans extrait en clair, ADR-33),
 * participant·es d'un groupe gérés par son équipe.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = M::member();
    $this->bea = M::member();
    $this->cyn = M::member();
});

it('quitte un groupe : plus listé, plus lisible, et la personne suivante devient admin si l’admin part', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea, $this->cyn]);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$group->ulid.'/leave')->assertOk()->assertJsonPath('data.left', true);

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$group->ulid)->assertNotFound();
    M::send($this->awa, $group)->assertNotFound();
    expect(ConversationParticipant::query()->where('conversation_id', $group->id)->active()->admins()->pluck('user_id')->all())->toBe([$this->bea->id]);
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations/'.$group->ulid)->assertOk()
        ->assertJsonPath('data.participants_count', 2)->assertJsonPath('data.can_manage', true);
});

it('masque une conversation privée, qui revient avec son historique au prochain message reçu', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    M::send($this->bea, $conversation, 'Premier')->assertCreated();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/leave')->assertOk();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');

    M::send($this->bea, $conversation, 'Second')->assertCreated();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.unread_count', 2);
});

it('bloque et débloque l’autre personne depuis une conversation privée ; refusé dans un groupe', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    $group = M::groupConversation($this->awa, [$this->bea]);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/block')->assertOk()
        ->assertJsonPath('data.blocked_by_me', true)->assertJsonPath('data.can_send', false);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/block')->assertOk();
    expect(UserBlock::query()->where(['user_id' => $this->awa->id, 'blocked_user_id' => $this->bea->id])->count())->toBe(1);
    M::send($this->bea, $conversation)->assertForbidden();

    FeedFixtures::as($this->awa)->deleteJson('/api/v1/conversations/'.$conversation->ulid.'/block')->assertOk()
        ->assertJsonPath('data.blocked_by_me', false)->assertJsonPath('data.can_send', true);
    M::send($this->bea, $conversation)->assertCreated();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$group->ulid.'/block')
        ->assertUnprocessable()->assertJsonPath('code', 'conversation_private_only');
});

it('signale le dernier message reçu (201 puis 200), sans extrait en clair, et prévient la modération', function (): void {
    Event::fake([ContentReported::class]);
    $conversation = M::privateConversation($this->awa, $this->bea);
    $message = M::send($this->bea, $conversation, 'Menace explicite')->json('data.id');
    M::send($this->awa, $conversation, 'Ma réponse')->assertCreated();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/report', ['reason' => 'menaces', 'details' => 'Depuis hier.'])
        ->assertCreated()->assertJsonPath('data', ['reported' => true, 'reason' => 'menaces']);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/report', ['reason' => 'menaces'])->assertOk();

    $report = ModerationReport::query()->sole();
    expect($report->kind)->toBe(ModerationKind::Message)
        ->and($report->subject_id)->toBe($message)
        ->and($report->excerpt)->toBeNull()
        ->and($report->reported_user_id)->toBe($this->bea->id)
        ->and($report->reason)->toBe(ContentReportReason::Threats->label());
    Event::assertDispatchedTimes(ContentReported::class, 1);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/report', ['reason' => 'inconnu'])->assertUnprocessable();
});

it('signale la personne d’une conversation privée sans message reçu, et refuse un groupe sans message d’autrui', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    $group = M::groupConversation($this->awa, [$this->bea]);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/report', ['reason' => 'harcelement'])->assertCreated();
    expect(ModerationReport::query()->sole()->kind)->toBe(ModerationKind::User);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$group->ulid.'/report', ['reason' => 'spam'])
        ->assertUnprocessable()->assertJsonPath('code', 'nothing_to_report');
    $own = M::send($this->awa, $group, 'Le mien')->json('data.id');
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$group->ulid.'/report', ['reason' => 'spam', 'message_id' => $own])
        ->assertUnprocessable();
});

it('ajoute et retire des participant·es (équipe du groupe seulement), jamais soi-même ni la créatrice', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea]);
    $url = '/api/v1/conversations/'.$group->ulid.'/participants';

    FeedFixtures::as($this->bea)->postJson($url, ['user_ids' => [$this->cyn->id]])->assertForbidden();
    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$this->cyn->id]])->assertOk()->assertJsonPath('data.participants_count', 3);

    $blocked = M::member();
    M::block($blocked, $this->awa);
    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$blocked->id]])->assertUnprocessable()->assertJsonPath('code', 'recipient_unavailable');

    FeedFixtures::as($this->bea)->deleteJson($url.'/'.$this->cyn->id)->assertForbidden();
    FeedFixtures::as($this->awa)->deleteJson($url.'/'.$this->awa->id)->assertForbidden();
    FeedFixtures::as($this->awa)->deleteJson($url.'/'.$this->cyn->id)->assertOk()->assertJsonPath('data.participants_count', 2);
    FeedFixtures::as($this->cyn)->getJson('/api/v1/conversations/'.$group->ulid)->assertNotFound();

    $private = M::privateConversation($this->awa, $this->bea);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$private->ulid.'/participants', ['user_ids' => [$this->cyn->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'conversation_group_only');
});

it('met en sourdine jusqu’à réactivation et refuse une durée inconnue', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/mute', ['duration' => 'always'])
        ->assertOk()->assertJsonPath('data.muted', true);
    expect(ConversationParticipant::query()->where('user_id', $this->awa->id)->value('muted_until'))->toBeGreaterThan(now()->addYears(50)->toDateTimeString());
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$conversation->ulid.'/mute', ['duration' => '3j'])->assertUnprocessable();
});
