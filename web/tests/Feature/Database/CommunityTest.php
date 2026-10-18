<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\ConversationKind;
use App\Enums\PostStatus;
use App\Enums\RsvpStatus;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\ForumsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lot 5 « community » : une création par factory et une assertion métier par table
 * (DATABASE_DICTIONARY.md §4). Nécessite l'intégration des lots pour RefreshDatabase.
 */

describe('events', function (): void {
    it('crée un événement avec ULID, enums castés, slug unique et organisateur·rice affichable', function (): void {
        $event = Event::factory()->online()->create(['slug' => 'webinaire-connaitre-ses-droits']);
        $labelled = Event::factory()->organizedBy('Équipe Xomiton')->create();

        expect($event->ulid)->toHaveLength(26)
            ->and($event->status)->toBe(ContentStatus::Published)
            ->and($event->is_online)->toBeTrue()
            ->and($event->starts_at)->not->toBeNull()
            ->and($event->isUpcoming())->toBeTrue()
            ->and($event->organizerLabel())->toBe($event->organizer->pseudo)
            ->and($labelled->organizerLabel())->toBe('Équipe Xomiton')
            ->and(Event::query()->whereUlid($event->ulid)->exists())->toBeTrue();

        expect(fn () => Event::factory()->create(['slug' => 'webinaire-connaitre-ses-droits']))->toThrow(QueryException::class);
    });

    it('distingue les événements à venir, passés, publiés et à rappeler', function (): void {
        Event::factory()->create(['starts_at' => now()->addHours(6), 'ends_at' => now()->addHours(8)]);
        Event::factory()->create(['starts_at' => now()->addWeek(), 'ends_at' => null]);
        Event::factory()->past()->create();
        Event::factory()->draft()->create();
        Event::factory()->hidden()->past()->create();

        expect(Event::query()->published()->count())->toBe(3)
            ->and(Event::query()->published()->upcoming()->count())->toBe(2)
            ->and(Event::query()->published()->past()->count())->toBe(1)
            ->and(Event::query()->reminderDue()->count())->toBe(1)
            ->and(Event::query()->online()->count())->toBe(0);
    });

    it('détache l’organisateur·rice supprimé·e et supprime logiquement l’événement', function (): void {
        $event = Event::factory()->create();

        $event->organizer->forceDelete();

        expect($event->fresh()->organizer_id)->toBeNull();

        $event->delete();

        expect(Event::query()->count())->toBe(0)
            ->and(Event::withTrashed()->count())->toBe(1);
    });
});

describe('event_rsvps', function (): void {
    it('n’admet qu’une réponse par membre et par événement et ne compte que les « going »', function (): void {
        $event = Event::factory()->create();
        $going = EventRsvp::factory()->create(['event_id' => $event->id]);
        EventRsvp::factory()->interested()->create(['event_id' => $event->id]);
        EventRsvp::factory()->declined()->create(['event_id' => $event->id]);
        EventRsvp::factory()->withoutReminder()->create(['event_id' => $event->id]);

        expect($going->status)->toBe(RsvpStatus::Going)
            ->and($going->status->label())->toBe('Participe')
            ->and($going->isGoing())->toBeTrue()
            ->and($going->reminder_opt_in)->toBeTrue()
            ->and($event->attendees()->count())->toBe(2)
            ->and($event->rsvps()->interested()->count())->toBe(1)
            ->and($event->rsvps()->wantsReminder()->count())->toBe(1);

        expect(fn () => EventRsvp::factory()->create(['event_id' => $event->id, 'user_id' => $going->user_id]))
            ->toThrow(QueryException::class);

        $event->forceDelete();

        expect(EventRsvp::query()->count())->toBe(0);
    });
});

describe('forums', function (): void {
    it('seed les 5 forums du prototype de façon idempotente, avec clé et slug uniques', function (): void {
        (new ForumsSeeder)->run();
        (new ForumsSeeder)->run();

        $forum = Forum::query()->forKey('f3')->firstOrFail();

        expect(Forum::query()->count())->toBe(5)
            ->and($forum->name)->toBe('Santé & bien-être')
            ->and($forum->slug)->toBe('sante-bien-etre')
            ->and($forum->icon)->toBe('bi-heart-pulse')
            ->and($forum->position)->toBe(2)
            ->and($forum->topics_count)->toBe(0)
            ->and(Forum::query()->active()->ordered()->first()->key)->toBe('f1');

        expect(fn () => Forum::factory()->create(['key' => 'f3']))->toThrow(QueryException::class);
    });

    it('crée un forum par factory et filtre les actifs', function (): void {
        Forum::factory()->create();
        Forum::factory()->inactive()->create();

        expect(Forum::query()->active()->count())->toBe(1);
    });
});

describe('forum_topics', function (): void {
    it('crée un sujet avec ULID, statut casté, auteur·rice masquable et tri par activité', function (): void {
        $topic = ForumTopic::factory()->anonymous()->create();
        $pinned = ForumTopic::factory()->pinned()->create(['forum_id' => $topic->forum_id]);
        ForumTopic::factory()->hidden()->create(['forum_id' => $topic->forum_id]);

        expect($topic->ulid)->toHaveLength(26)
            ->and($topic->status)->toBe(PostStatus::Published)
            ->and($topic->authorLabel())->toBe('Membre anonyme')
            ->and($pinned->authorLabel())->toBe($pinned->user->pseudo)
            ->and($topic->forum->topics()->published()->count())->toBe(2)
            ->and($topic->forum->topics()->published()->byActivity()->first()->is($pinned))->toBeTrue()
            ->and(ForumTopic::query()->pinned()->count())->toBe(1);
    });

    it('empêche la suppression d’un forum qui a des sujets', function (): void {
        $topic = ForumTopic::factory()->create();

        expect(fn () => $topic->forum->delete())->toThrow(QueryException::class);
    });
});

describe('forum_replies', function (): void {
    it('attache des réponses supprimées en cascade avec le sujet et détache l’auteur·rice supprimé·e', function (): void {
        $topic = ForumTopic::factory()->create();
        $reply = ForumReply::factory()->forTopic($topic)->create();
        ForumReply::factory()->forTopic($topic)->anonymous()->hidden()->create();

        expect($reply->status)->toBe(PostStatus::Published)
            ->and($reply->authorLabel())->toBe($reply->user->pseudo)
            ->and($topic->replies)->toHaveCount(2)
            ->and($topic->replies()->published()->count())->toBe(1);

        $reply->user->forceDelete();

        expect($reply->fresh()->user_id)->toBeNull()
            ->and($reply->fresh()->authorLabel())->toBe('Membre anonyme');

        $topic->forceDelete();

        expect(ForumReply::query()->count())->toBe(0);
    });
});

describe('conversations', function (): void {
    it('crée une conversation privée ou de groupe avec ULID et la rend visible à ses participant·es', function (): void {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $carol = User::factory()->create();

        $private = Conversation::factory()->withParticipants([$alice, $bob])->create(['created_by' => $alice->id]);
        $group = Conversation::factory()->group()->withParticipants([$alice, $carol])->create();

        expect($private->ulid)->toHaveLength(26)
            ->and($private->kind)->toBe(ConversationKind::Private)
            ->and($private->isGroup())->toBeFalse()
            ->and($group->kind->label())->toBe('Conversation de groupe')
            ->and($group->title)->not->toBeNull()
            ->and($private->participants()->admins()->first()->user_id)->toBe($alice->id)
            ->and(Conversation::query()->visibleTo($alice)->count())->toBe(2)
            ->and(Conversation::query()->visibleTo($bob)->count())->toBe(1)
            ->and(Conversation::query()->groups()->count())->toBe(1);
    });

    it('dénormalise la date du dernier message sans recopier son corps chiffré en clair (ADR-33, P8-01)', function (): void {
        $conversation = Conversation::factory()->create(['last_message_preview' => 'ancien aperçu']);
        $message = Message::factory()->in($conversation)->create(['body' => str_repeat('a', 200)]);

        $conversation->touchLastMessage($message);

        expect($conversation->last_message_at)->not->toBeNull()
            ->and($conversation->last_message_preview)->toBeNull();
    });
});

describe('conversation_participants', function (): void {
    it('n’admet qu’une participation par membre, gère sourdine, départ et messages non lus', function (): void {
        $participant = ConversationParticipant::factory()->create();
        $muted = ConversationParticipant::factory()->muted()->create(['conversation_id' => $participant->conversation_id]);
        $left = ConversationParticipant::factory()->left()->create(['conversation_id' => $participant->conversation_id]);

        Message::factory()->in($participant->conversation, $muted->user)->count(2)->create();

        expect($participant->role)->toBe('member')
            ->and($muted->isMuted())->toBeTrue()
            ->and($left->hasLeft())->toBeTrue()
            ->and($participant->unreadCount())->toBe(2)
            ->and($participant->conversation->participants()->active()->count())->toBe(2)
            ->and($participant->conversation->participants()->notifiable()->count())->toBe(1)
            ->and($participant->conversation->users()->count())->toBe(2);

        expect(fn () => ConversationParticipant::factory()->create([
            'conversation_id' => $participant->conversation_id,
            'user_id' => $participant->user_id,
        ]))->toThrow(QueryException::class);
    });
});

describe('messages', function (): void {
    it('chiffre le corps au repos et le relit en clair', function (): void {
        $message = Message::factory()->create(['body' => 'Rendez-vous demain à 15 h.']);

        $raw = DB::table('messages')->where('id', $message->id)->value('body');

        expect($raw)->not->toBe('Rendez-vous demain à 15 h.')
            ->and($message->fresh()->body)->toBe('Rendez-vous demain à 15 h.')
            ->and($message->client_uuid)->toHaveLength(36);
    });

    it('rejette un second envoi avec le même client_uuid dans la conversation (idempotence hors ligne)', function (): void {
        $message = Message::factory()->create();

        expect(Message::query()->withClientUuid($message->conversation_id, $message->client_uuid)->exists())->toBeTrue();

        expect(fn () => Message::factory()->in($message->conversation)->create(['client_uuid' => $message->client_uuid]))
            ->toThrow(QueryException::class);
    });

    it('filtre les messages visibles et non lus, et suit la suppression de la conversation', function (): void {
        $conversation = Conversation::factory()->create();
        Message::factory()->in($conversation)->create();
        Message::factory()->in($conversation)->read()->create();
        Message::factory()->in($conversation)->deletedForEveryone()->create();

        expect(Message::query()->visible()->count())->toBe(2)
            ->and(Message::query()->unread()->count())->toBe(2)
            ->and($conversation->messages()->count())->toBe(3);

        $conversation->delete();

        expect(Message::query()->count())->toBe(0);
    });
});
