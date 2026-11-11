<?php

declare(strict_types=1);

use App\Application\Member\MemberShell;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\ModerationReport;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures;
use Tests\Support\MessagingFixtures as M;

/*
 * P8-01, revue du lot messagerie : consentement et preuve.
 *  - conversation privée : aucun droit de gestion, la personne qui l'a ouverte ne supprime pas les messages de l'autre ;
 *  - groupe : l'admin supprime le message d'une membre, une membre non ; aucune entrée avec une personne bloquée (dans
 *    un sens ou dans l'autre), jamais de réintégration après un départ volontaire, historique lu à partir de l'entrée ;
 *  - message signalé puis supprimé par son autrice : masqué pour tout le monde, corps chiffré et pièce jointe gardés ;
 *  - sondage : `deleted_ids`, empreinte de la liste (`meta.unchanged`), participant·es dans `show` seulement ;
 *  - non lus : `POST read` rend le vrai reste, l'en-tête suit la liste (comptes bloqués exclus) ;
 *  - `?with=` ne crée rien, conversation vide invisible chez l'autre ; limiteurs et `verified` des routes.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = M::member();
    $this->bea = M::member();
    $this->cyn = M::member();
});

function messagesOf($user, Conversation $conversation): array
{
    return FeedFixtures::as($user)->getJson('/api/v1/conversations/'.$conversation->ulid.'/messages')->assertOk()->json('data');
}

it('privée : la créatrice ne supprime pas le message de l’autre (403), ni ne gère la conversation', function (): void {
    $private = M::privateConversation($this->awa, $this->bea);
    $id = M::send($this->bea, $private, 'Mon message')->json('data.id');

    FeedFixtures::as($this->awa)->deleteJson('/api/v1/conversations/'.$private->ulid.'/messages/'.$id)->assertForbidden();
    expect(Message::query()->find($id)->body)->toBe('Mon message')
        ->and(Message::query()->find($id)->deleted_for_everyone_at)->toBeNull()
        ->and($this->awa->can('manage', $private))->toBeFalse();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$private->ulid)->assertJsonPath('data.can_manage', false);
});

it('groupe : l’admin supprime le message d’une membre, une membre ne supprime pas celui d’une autre', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea, $this->cyn]);
    $id = M::send($this->bea, $group, 'Message à modérer')->json('data.id');
    $url = '/api/v1/conversations/'.$group->ulid.'/messages/'.$id;

    FeedFixtures::as($this->cyn)->deleteJson($url)->assertForbidden();
    FeedFixtures::as($this->awa)->deleteJson($url)->assertOk()->assertJsonPath('data.deleted', true)->assertJsonPath('data.body', null);
    expect(Message::query()->find($id)->body)->toBe('');
});

it('garde la preuve d’un message signalé que son autrice supprime ensuite (corps chiffré et pièce jointe)', function (): void {
    Storage::fake('local');
    $private = M::privateConversation($this->awa, $this->bea);
    $id = FeedFixtures::as($this->bea)->post('/api/v1/conversations/'.$private->ulid.'/messages', [
        'body' => 'Message menaçant', 'client_uuid' => (string) Str::uuid(),
        'attachment' => UploadedFile::fake()->createWithContent('photo.jpg', FeedFixtures::jpegWithExif(200, 100)),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
    $path = Message::query()->find($id)->media_path;

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$private->ulid.'/report', ['reason' => 'harcelement', 'message_id' => $id])->assertCreated();
    FeedFixtures::as($this->bea)->deleteJson('/api/v1/conversations/'.$private->ulid.'/messages/'.$id)
        ->assertOk()->assertJsonPath('data.deleted', true)->assertJsonPath('data.body', null)->assertJsonPath('data.attachment', null);

    $message = Message::query()->find($id);
    expect($message->deleted_for_everyone_at)->not->toBeNull()
        ->and($message->body)->toBe('Message menaçant')
        ->and(DB::table('messages')->where('id', $id)->value('body'))->not->toContain('menaçant')
        ->and($message->media_path)->toBe($path)
        ->and(Storage::disk('local')->exists($path))->toBeTrue()
        ->and(ModerationReport::query()->sole()->excerpt)->toBeNull();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')
        ->assertJsonPath('data.0.last_message.deleted', true)->assertJsonPath('data.0.last_message.attachment', false)
        ->assertDontSee('menaçant');
});

it('refuse de mettre dans un groupe deux personnes dont l’une a bloqué l’autre, même sans l’admin', function (): void {
    M::block($this->bea, $this->cyn);

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['kind' => 'group', 'title' => 'Cercle', 'user_ids' => [$this->bea->id, $this->cyn->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'recipient_unavailable');

    $group = M::groupConversation($this->awa, [$this->bea]);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$group->ulid.'/participants', ['user_ids' => [$this->cyn->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'recipient_unavailable');
    expect(ConversationParticipant::query()->where('conversation_id', $group->id)->where('user_id', $this->cyn->id)->exists())->toBeFalse();
});

it('ne réintègre jamais une personne partie d’elle-même ; une personne retirée peut être ajoutée de nouveau', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea, $this->cyn]);
    $url = '/api/v1/conversations/'.$group->ulid.'/participants';

    FeedFixtures::as($this->bea)->postJson('/api/v1/conversations/'.$group->ulid.'/leave')->assertOk();
    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$this->bea->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'recipient_unavailable');
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations/'.$group->ulid)->assertNotFound();

    FeedFixtures::as($this->awa)->deleteJson($url.'/'.$this->cyn->id)->assertOk();
    expect(ConversationParticipant::query()->where('user_id', $this->cyn->id)->value('left_reason'))->toBe(ConversationParticipant::LEFT_REMOVED);
    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$this->cyn->id]])->assertOk();
});

it('ne montre à une personne ajoutée ou réintégrée que les messages postérieurs à son entrée, sans non-lus d’avant', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea]);
    M::send($this->awa, $group, 'Avant Cyn')->assertCreated();
    $url = '/api/v1/conversations/'.$group->ulid.'/participants';

    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$this->cyn->id]])->assertOk();
    expect(messagesOf($this->cyn, $group))->toBe([]);
    FeedFixtures::as($this->cyn)->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 0)->assertJsonPath('data.0.last_message', null);

    M::send($this->awa, $group, 'Bienvenue Cyn')->assertCreated();
    FeedFixtures::as($this->awa)->deleteJson($url.'/'.$this->cyn->id)->assertOk();
    M::send($this->awa, $group, 'Pendant son absence')->assertCreated();
    FeedFixtures::as($this->awa)->postJson($url, ['user_ids' => [$this->cyn->id]])->assertOk();
    M::send($this->bea, $group, 'Après son retour')->assertCreated();

    expect(array_column(messagesOf($this->cyn, $group), 'body'))->toBe(['Après son retour'])
        ->and(app(MemberShell::class)->counters($this->cyn)['messages'])->toBe(1);
    FeedFixtures::as($this->cyn)->postJson('/api/v1/conversations/'.$group->ulid.'/report', ['reason' => 'harcelement', 'message_id' => Message::query()->where('id', '<', Message::query()->max('id'))->max('id')])
        ->assertUnprocessable();
});

it('compte dans l’en-tête les mêmes non-lus que la liste : rien d’un compte bloqué dans un groupe', function (): void {
    $group = M::groupConversation($this->cyn, [$this->awa, $this->bea]);
    M::block($this->awa, $this->bea);
    M::send($this->bea, $group, 'De Bea')->assertCreated();

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 0);
    expect(app(MemberShell::class)->counters($this->awa)['messages'])->toBe(0);
});

it('rend le vrai reste de non-lus après une lecture partielle', function (): void {
    $private = M::privateConversation($this->awa, $this->bea);
    $first = M::send($this->bea, $private, 'Un')->json('data.id');
    M::send($this->bea, $private, 'Deux')->assertCreated();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$private->ulid.'/read', ['message_id' => $first])
        ->assertOk()->assertJsonPath('data.unread_count', 1)->assertJsonPath('data.unread_conversations', 1);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$private->ulid.'/read')
        ->assertOk()->assertJsonPath('data.unread_count', 0)->assertJsonPath('data.unread_conversations', 0);
});

it('rapporte au sondage ?after= les messages déjà livrés supprimés pour tout le monde (deleted_ids)', function (): void {
    $private = M::privateConversation($this->awa, $this->bea);
    $id = M::send($this->bea, $private, 'Oups')->json('data.id');
    $base = '/api/v1/conversations/'.$private->ulid.'/messages?after='.$id;

    $checkedAt = FeedFixtures::as($this->awa)->getJson($base)->assertOk()->assertJsonPath('meta.deleted_ids', [])->json('meta.checked_at');
    FeedFixtures::as($this->bea)->deleteJson('/api/v1/conversations/'.$private->ulid.'/messages/'.$id)->assertOk();

    FeedFixtures::as($this->awa)->getJson($base.'&since='.urlencode((string) $checkedAt))->assertOk()
        ->assertJsonCount(0, 'data')->assertJsonPath('meta.deleted_ids', [$id]);
    FeedFixtures::as($this->awa)->getJson($base.'&since='.urlencode(now()->addMinute()->toIso8601String()))->assertJsonPath('meta.deleted_ids', []);
});

it('allège le sondage de la liste : empreinte inchangée → data vide, participant·es dans show seulement', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea, $this->cyn]);
    M::send($this->bea, $group, 'Salut')->assertCreated();

    $list = FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertOk()
        ->assertJsonPath('meta.unchanged', false)->assertJsonPath('data.0.participants_count', 3)->assertJsonMissingPath('data.0.participants');
    $fingerprint = (string) $list->json('meta.fingerprint');
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$group->ulid)->assertJsonCount(3, 'data.participants');

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?since='.$fingerprint)->assertOk()
        ->assertJsonPath('meta.unchanged', true)->assertJsonCount(0, 'data')->assertHeader('Cache-Control', 'no-store, private');

    M::send($this->cyn, $group, 'Nouveau')->assertCreated();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?since='.$fingerprint)->assertOk()
        ->assertJsonPath('meta.unchanged', false)->assertJsonPath('data.0.last_message.preview', 'Nouveau');
});

it('?with= ne crée aucune conversation et une conversation vide n’apparaît pas chez l’autre personne', function (): void {
    $this->actingAs($this->awa)->get('/messages?with='.$this->bea->id)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('with.id', $this->bea->id)->where('with.pseudo', $this->bea->pseudo)
            ->where('withUnavailable', false)->where('selected', null));
    expect(Conversation::query()->count())->toBe(0);

    $private = M::privateConversation($this->awa, $this->bea);
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations')->assertJsonCount(0, 'data');
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonCount(1, 'data');
    M::send($this->awa, $private, 'Bonjour')->assertCreated();
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations')->assertJsonCount(1, 'data');

    $this->actingAs($this->awa)->get('/messages?with='.$this->bea->id)
        ->assertInertia(fn (Assert $page) => $page->where('with', null)->where('selected', $private->ulid));

    M::block($this->cyn, $this->awa);
    $this->actingAs($this->awa)->get('/messages?with='.$this->cyn->id)
        ->assertInertia(fn (Assert $page) => $page->where('with', null)->where('withUnavailable', true));
});

it('envoie par idempotent:…,limit=messages_write et exige un e-mail confirmé pour gérer un groupe', function (): void {
    $middleware = fn (string $name): array => Route::getRoutes()->getByName($name)->gatherMiddleware();

    expect($middleware('api.conversations.messages.store'))->toContain('verified', 'idempotent:optional,limit=messages_write')
        ->not->toContain('throttle:messages_write')
        ->and($middleware('api.conversations.participants.store'))->toContain('verified', 'throttle:messages_write')
        ->and($middleware('api.conversations.participants.destroy'))->toContain('verified')
        ->and($middleware('api.conversations.update'))->toContain('verified')
        ->and($middleware('api.conversations.messages.destroy'))->toContain('throttle:messages_write');

    $uuid = (string) Str::uuid();
    $private = M::privateConversation($this->awa, $this->bea);
    config()->set('xomiton.rate_limits.messages_write_per_minute', 2); // la création de la conversation compte
    $send = fn () => FeedFixtures::as($this->awa)->withHeader('Idempotency-Key', $uuid)
        ->postJson('/api/v1/conversations/'.$private->ulid.'/messages', ['body' => 'Hors ligne', 'client_uuid' => $uuid]);

    $send()->assertCreated();
    $send()->assertCreated()->assertHeader('Idempotent-Replayed', 'true'); // rejeu de la boîte d'envoi : pas de 429
    $this->flushHeaders();
    M::send($this->awa, $private, 'Autre')->assertTooManyRequests();
    expect(Message::query()->count())->toBe(1);
});
