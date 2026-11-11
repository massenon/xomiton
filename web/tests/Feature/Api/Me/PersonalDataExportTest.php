<?php

declare(strict_types=1);

use App\Application\Account\PersonalDataExport;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Alert;
use App\Models\AlertAttachment;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\Follow;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Page;
use App\Models\PageFollower;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\PostSave;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserWarning;

/*
 * P6-06 (lot api-me, revue) : contenu de l'export RGPD, une vérification par section — ce que la personne a fourni ou
 * fait sur la plateforme, identifiants publics (ULID) seulement, jamais les messages d'autrui ni l'identité de qui
 * l'a avertie.
 */

beforeEach(function (): void {
    $this->user = User::factory()->withProfile(['avatar_path' => 'avatars/awa.webp', 'cover_path' => 'covers/awa.webp'])->create(['pseudo' => 'Awa_S']);
});

function exportOf(User $user): array
{
    return app(PersonalDataExport::class)->collect($user->fresh());
}

it('exporte la section :dataset', function (Closure $seed, Closure $check): void {
    $context = $seed($this->user);

    $check(exportOf($this->user), $context);
})->with([
    'profil (avatar, couverture)' => [
        fn (User $user) => null,
        fn (array $export) => expect($export['profile']['avatar_path'])->toBe('avatars/awa.webp')->and($export['profile']['cover_path'])->toBe('covers/awa.webp'),
    ],
    'publications et médias' => [
        fn (User $user) => PostMedia::factory()->for(Post::factory()->create(['user_id' => $user->id]))->create(['path' => 'posts/awa-1.jpg', 'alt' => 'Plage']),
        fn (array $export, PostMedia $media) => expect($export['posts'][0]['id'])->toBe($media->post->ulid)
            ->and($export['posts'][0]['media'][0])->toBe(['kind' => 'image', 'path' => 'posts/awa-1.jpg', 'alt' => 'Plage']),
    ],
    'commentaires (ULID du post)' => [
        fn (User $user) => Comment::factory()->create(['user_id' => $user->id, 'body' => 'Courage à toi']),
        fn (array $export, Comment $comment) => expect($export['comments'][0]['post_id'])->toBe($comment->post->ulid)
            ->and($export['comments'][0]['post_id'])->not->toBe($comment->post_id)
            ->and($export['comments'][0]['body'])->toBe('Courage à toi'),
    ],
    'mentions j’aime' => [
        fn (User $user) => PostLike::factory()->create(['user_id' => $user->id]),
        fn (array $export, PostLike $like) => expect($export['likes'][0]['post_id'])->toBe($like->post->ulid),
    ],
    'enregistrements' => [
        fn (User $user) => PostSave::factory()->create(['user_id' => $user->id]),
        fn (array $export, PostSave $save) => expect($export['saves'][0]['post_id'])->toBe($save->post->ulid),
    ],
    'votes' => [
        fn (User $user) => PollVote::factory()->create(['user_id' => $user->id]),
        fn (array $export, PollVote $vote) => expect($export['poll_votes'][0]['option'])->toBe($vote->option->label)
            ->and($export['poll_votes'][0]['post_id'])->toBe($vote->poll->post?->ulid),
    ],
    'abonnements (comptes suivis)' => [
        fn (User $user) => Follow::factory()->create(['follower_id' => $user->id, 'followed_id' => User::factory()->create(['pseudo' => 'Mariam_A'])->id]),
        fn (array $export) => expect($export['following'][0]['pseudo'])->toBe('Mariam_A'),
    ],
    'pages suivies' => [
        fn (User $user) => PageFollower::factory()->create(['user_id' => $user->id, 'page_id' => Page::factory()->create(['name' => 'Collectif Cotonou'])->id]),
        fn (array $export) => expect($export['pages_followed'][0]['page'])->toBe('Collectif Cotonou'),
    ],
    'groupes' => [
        fn (User $user) => GroupMember::factory()->create(['user_id' => $user->id, 'group_id' => Group::factory()->create(['name' => 'Cercle de parole'])->id]),
        fn (array $export) => expect($export['groups'][0]['group'])->toBe('Cercle de parole'),
    ],
    'participations aux événements' => [
        fn (User $user) => EventRsvp::factory()->create(['user_id' => $user->id, 'event_id' => Event::factory()->create(['title' => 'Atelier santé'])->id]),
        fn (array $export, EventRsvp $rsvp) => expect($export['events'][0]['title'])->toBe('Atelier santé')
            ->and($export['events'][0]['event_id'])->toBe($rsvp->event->ulid),
    ],
    'invitations envoyées et reçues' => [
        function (User $user): void {
            Invitation::factory()->create(['from_user_id' => $user->id, 'to_user_id' => User::factory()->create(['pseudo' => 'Inès_K'])->id]);
            Invitation::factory()->create(['to_user_id' => $user->id, 'from_user_id' => User::factory()->create(['pseudo' => 'Yasmine_T'])->id]);
        },
        fn (array $export) => expect(array_column($export['invitations'], 'with', 'direction'))->toBe(['sent' => 'Inès_K', 'received' => 'Yasmine_T']),
    ],
    'conversations et messages écrits (jamais ceux d’autrui)' => [
        function (User $user): Conversation {
            $conversation = Conversation::factory()->create();
            ConversationParticipant::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id]);
            Message::factory()->create(['conversation_id' => $conversation->id, 'user_id' => $user->id, 'body' => 'Mon message']);
            Message::factory()->create(['conversation_id' => $conversation->id, 'body' => 'Message d’une autre personne']);

            return $conversation;
        },
        fn (array $export, Conversation $conversation) => expect($export['conversations'][0]['conversation_id'])->toBe($conversation->ulid)
            ->and(array_column($export['messages'], 'body'))->toBe(['Mon message'])
            ->and($export['messages'][0]['conversation_id'])->toBe($conversation->ulid),
    ],
    'sujets de forum' => [
        fn (User $user) => ForumTopic::factory()->create(['user_id' => $user->id, 'title' => 'Comment en parler ?', 'is_anonymous' => true]),
        fn (array $export, ForumTopic $topic) => expect($export['forum_topics'][0])->toMatchArray(['id' => $topic->ulid, 'title' => 'Comment en parler ?', 'anonymous' => true]),
    ],
    'réponses de forum' => [
        fn (User $user) => ForumReply::factory()->create(['user_id' => $user->id, 'body' => 'Je te comprends']),
        fn (array $export, ForumReply $reply) => expect($export['forum_replies'][0])->toMatchArray(['topic_id' => $reply->topic->ulid, 'body' => 'Je te comprends']),
    ],
    'conversations avec l’assistant (sans indicateur de modération)' => [
        fn (User $user) => AssistantMessage::factory()->create([
            'conversation_id' => AssistantConversation::factory()->create(['user_id' => $user->id, 'is_flagged' => true, 'flag_reason' => 'interne'])->id,
            'body' => 'Où trouver de l’aide ?',
        ]),
        fn (array $export) => expect($export['assistant_conversations'][0]['messages'][0]['body'])->toBe('Où trouver de l’aide ?')
            ->and($export['assistant_conversations'][0])->not->toHaveKey('flag_reason')
            ->and(json_encode($export))->not->toContain('interne'),
    ],
    'albums et médias' => [
        fn (User $user) => AlbumMedia::factory()->create(['album_id' => Album::factory()->create(['user_id' => $user->id, 'title' => 'Souvenirs'])->id, 'path' => 'albums/awa.jpg']),
        fn (array $export) => expect($export['albums'][0]['title'])->toBe('Souvenirs')->and($export['albums'][0]['media'][0]['path'])->toBe('albums/awa.jpg'),
    ],
    'blocages' => [
        fn (User $user) => UserBlock::factory()->create(['user_id' => $user->id, 'blocked_user_id' => User::factory()->create(['pseudo' => 'Bloqué_X'])->id]),
        fn (array $export) => expect($export['blocks'][0]['pseudo'])->toBe('Bloqué_X'),
    ],
    'avertissements (sans l’identité de qui avertit)' => [
        fn (User $user) => UserWarning::factory()->create(['user_id' => $user->id, 'reason' => 'Propos blessants', 'issued_by' => User::factory()->create(['pseudo' => 'Modo_Z'])->id]),
        fn (array $export) => expect($export['warnings'][0])->toBe(['reason' => 'Propos blessants', 'created_at' => $export['warnings'][0]['created_at']])
            ->and(json_encode($export))->not->toContain('Modo_Z'),
    ],
    'signalements de modération déposés' => [
        fn (User $user) => ModerationReport::factory()->create(['reporter_user_id' => $user->id, 'details' => 'Message insistant']),
        fn (array $export) => expect($export['reports_filed'][0]['details'])->toBe('Message insistant'),
    ],
    'alertes : lieu, contact, besoins et pièces jointes' => [
        function (User $user): Alert {
            $alert = Alert::factory()->create(['user_id' => $user->id, 'is_anonymous' => false, 'lat' => '6.3654', 'lng' => '2.4183', 'contact' => '+229 01 97 00 00 00', 'needs' => ['ecoute']]);
            AlertAttachment::factory()->create(['alert_id' => $alert->id, 'kind' => 'images', 'mime' => 'image/webp']);

            return $alert;
        },
        fn (array $export) => expect($export['alerts'][0])->toMatchArray(['lat' => '6.3654', 'lng' => '2.4183', 'contact' => '+229 01 97 00 00 00', 'needs' => ['ecoute']])
            ->and($export['alerts'][0]['attachments'][0])->toMatchArray(['kind' => 'images', 'mime' => 'image/webp']),
    ],
]);

it('n’inclut ni les publications d’autrui ni les alertes anonymes, et donne le format courant', function (): void {
    Post::factory()->create();
    Alert::factory()->create(['user_id' => null, 'is_anonymous' => true]);

    $export = exportOf($this->user);

    expect($export['format_version'])->toBe(PersonalDataExport::FORMAT_VERSION)
        ->and($export['posts'])->toBe([])
        ->and($export['alerts'])->toBe([]);
});
