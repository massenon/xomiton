<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DatabaseNotification;
use App\Models\Message;
use App\Notifications\NewMessage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Support\FeedFixtures;
use Tests\Support\MessagingFixtures as M;

/*
 * P8-01, docs/API.md §12.2 : envoi chiffré au repos (config xomiton.encrypted_columns.messages), rejeu idempotent par
 * `client_uuid` (deux rejeux du même message n'en créent qu'un, ADR-32), historique par curseur et sondage `after`,
 * blocage respecté, lecture et « Vu », notification « Nouveau message » sans extrait, suppression pour tout le monde,
 * pièce jointe en lien signé.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = M::member();
    $this->bea = M::member();
    $this->conversation = M::privateConversation($this->awa, $this->bea);
});

it('chiffre le corps au repos, ne recopie aucun aperçu en clair et marque le message lu par son autrice', function (): void {
    expect(config('xomiton.encrypted_columns.messages'))->toContain('body');

    $response = M::send($this->awa, $this->conversation, 'Je viendrai avec une amie.')
        ->assertCreated()
        ->assertJsonPath('data.body', 'Je viendrai avec une amie.')
        ->assertJsonPath('data.mine', true)
        ->assertJsonPath('data.status', 'sent')
        ->assertJsonPath('data.author.id', $this->awa->id)
        ->assertJsonPath('meta.duplicate', false);

    $raw = DB::table('messages')->where('id', $response->json('data.id'))->value('body');
    expect($raw)->not->toContain('amie')
        ->and(Crypt::decryptString($raw))->toBe('Je viendrai avec une amie.')
        ->and(DB::table('conversations')->where('id', $this->conversation->id)->value('last_message_preview'))->toBeNull()
        ->and($this->conversation->refresh()->last_message_at)->not->toBeNull()
        ->and(ConversationParticipant::query()->where('user_id', $this->awa->id)->value('last_read_message_id'))->toBe($response->json('data.id'));
});

it('rejoue un envoi de la boîte d’envoi sans doublon : même client_uuid → 200 et le message d’origine (corps ou en-tête)', function (): void {
    $uuid = (string) Str::uuid();

    $first = M::send($this->awa, $this->conversation, 'Bonjour', $uuid)->assertCreated();
    M::send($this->awa, $this->conversation, 'Bonjour', $uuid)->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('meta.duplicate', true);
    FeedFixtures::as($this->awa)->withHeader('Idempotency-Key', strtoupper($uuid))
        ->postJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages', ['body' => 'Bonjour'])
        ->assertOk()->assertJsonPath('data.client_uuid', $uuid);

    expect(Message::query()->count())->toBe(1)
        ->and(DatabaseNotification::query()->where('type', NewMessage::class)->count())->toBe(1);
});

it('refuse un client_uuid déjà pris par une autre personne (422, jamais 409) sans dévoiler son message', function (): void {
    $uuid = (string) Str::uuid();
    M::send($this->awa, $this->conversation, 'Secret', $uuid)->assertCreated();

    M::send($this->bea, $this->conversation, 'Autre', $uuid)->assertUnprocessable()
        ->assertJsonPath('code', 'client_uuid_conflict')->assertDontSee('Secret');
});

it('valide le corps : obligatoire sans pièce jointe, 1000 caractères au plus, client_uuid UUID', function (): void {
    M::send($this->awa, $this->conversation, '   ')->assertUnprocessable()->assertJsonValidationErrors('body');
    M::send($this->awa, $this->conversation, str_repeat('a', 1001))->assertUnprocessable()->assertJsonValidationErrors('body');
    M::send($this->awa, $this->conversation, 'ok', 'pas-un-uuid')->assertUnprocessable()->assertJsonValidationErrors('client_uuid');
    M::send(M::member(verified: false), $this->conversation)->assertForbidden();
    M::send(M::member(), $this->conversation)->assertNotFound();
});

it('pagine l’historique par curseur (plus récent d’abord) et sert le sondage court `after` (plus ancien d’abord)', function (): void {
    foreach (range(1, 35) as $i) {
        Message::factory()->in($this->conversation, $i % 2 ? $this->awa : $this->bea)->create(['body' => 'Message '.$i]);
    }
    $ids = Message::query()->orderBy('id')->pluck('id')->all();

    $page = FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages')
        ->assertOk()->assertJsonCount(30, 'data')
        ->assertJsonPath('data.0.body', 'Message 35')
        ->assertJsonPath('meta.poll_seconds', 4)
        ->assertHeader('Cache-Control', 'no-store, private');
    $next = $page->json('meta.next_cursor');
    expect($next)->toBeString();

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages?cursor='.$next)
        ->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.4.body', 'Message 1');

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages?after='.$ids[32])
        ->assertOk()->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.body', 'Message 34')
        ->assertJsonPath('meta.last_id', $ids[34])
        ->assertJsonPath('meta.has_more', false);
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages?after='.$ids[34])
        ->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.last_id', $ids[34]);
});

it('respecte le blocage dans les deux sens en privé (403 neutre) ; en groupe, ni l’une ni l’autre ne se lisent plus', function (): void {
    $cyn = M::member();
    $group = M::groupConversation($cyn, [$this->awa, $this->bea]);
    M::block($this->bea, $this->awa);

    foreach ([$this->awa, $this->bea] as $author) {
        M::send($author, $this->conversation)->assertForbidden()
            ->assertJsonPath('code', 'conversation_unavailable')
            ->assertJsonPath('message', __('community.messages.api.conversation_unavailable'));
    }
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid)
        ->assertOk()->assertJsonPath('data.can_send', false)->assertJsonPath('data.blocked_by_me', false);
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations/'.$this->conversation->ulid)
        ->assertOk()->assertJsonPath('data.can_send', false)->assertJsonPath('data.blocked_by_me', true);

    M::send($this->awa, $group, 'Message de Awa dans le groupe')->assertCreated();
    M::send($this->bea, $group, 'Message de Bea dans le groupe')->assertCreated();
    M::send($cyn, $group, 'Message de Cyn')->assertCreated();

    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations/'.$group->ulid.'/messages')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.body', 'Message de Cyn')
        ->assertDontSee('Message de Awa');
    // La personne bloquée ne lit plus celle qui l'a bloquée (liste, aperçu, non lus compris).
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$group->ulid.'/messages')
        ->assertOk()->assertJsonCount(2, 'data')->assertDontSee('Message de Bea');
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?filter=unread')->assertOk()
        ->assertJsonPath('data.0.unread_count', 1)->assertJsonPath('data.0.last_message.preview', 'Message de Cyn');
    FeedFixtures::as($cyn)->getJson('/api/v1/conversations/'.$group->ulid.'/messages')->assertOk()->assertJsonCount(3, 'data');
});

it('marque lu (jamais en arrière), rend le compteur de l’en-tête, la notification lue et « Vu » à l’autrice', function (): void {
    $first = M::send($this->bea, $this->conversation, 'Un')->json('data.id');
    $second = M::send($this->bea, $this->conversation, 'Deux')->json('data.id');

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonPath('data.0.unread_count', 2);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$this->conversation->ulid.'/read', ['message_id' => $first])
        ->assertOk()->assertJsonPath('data.last_read_message_id', $first)->assertJsonPath('data.unread_conversations', 1);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$this->conversation->ulid.'/read')
        ->assertOk()->assertJsonPath('data.last_read_message_id', $second)->assertJsonPath('data.unread_conversations', 0);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$this->conversation->ulid.'/read', ['message_id' => $first])
        ->assertOk()->assertJsonPath('data.last_read_message_id', $second);

    expect(DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->whereNull('read_at')->count())->toBe(0);
    FeedFixtures::as($this->bea)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages')
        ->assertOk()->assertJsonPath('meta.read_up_to', $second)->assertJsonPath('data.0.status', 'seen');
});

it('notifie « Nouveau message » sans extrait, une seule fois par conversation, ni en sourdine ni à qui a bloqué', function (): void {
    M::send($this->bea, $this->conversation, 'Le centre de dépistage est à Zogbo.')->assertCreated();
    M::send($this->bea, $this->conversation, 'Rendez-vous samedi.')->assertCreated();

    $notifications = DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->where('type', NewMessage::class)->get();
    expect($notifications)->toHaveCount(1)
        ->and($notifications->first()->data['body'])->toBe(__('notifications.new_message.body', [
            'name' => $this->bea->pseudo, 'preview' => trans_choice('notifications.new_message.count', 2, ['count' => 2]),
        ]))
        ->and(json_encode($notifications->first()->data))->not->toContain('Zogbo')
        ->and($notifications->first()->data['url'])->toContain('/messages?c='.$this->conversation->ulid)
        ->and(DatabaseNotification::query()->where('notifiable_id', $this->bea->id)->count())->toBe(0);

    // Au-delà du délai de regroupement : la notification est remplacée (une seule non lue).
    $this->travel(2)->minutes();
    M::send($this->bea, $this->conversation, 'Trois')->assertCreated();
    expect(DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->whereNull('read_at')->count())->toBe(1);

    DatabaseNotification::query()->delete();
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations/'.$this->conversation->ulid.'/mute', ['duration' => '8h'])->assertOk()
        ->assertJsonPath('data.muted', true);
    M::send($this->bea, $this->conversation, 'Quatre')->assertCreated();
    expect(DatabaseNotification::query()->count())->toBe(0);

    FeedFixtures::as($this->awa)->deleteJson('/api/v1/conversations/'.$this->conversation->ulid.'/mute')->assertOk()->assertJsonPath('data.muted', false);
    $cyn = M::member();
    $group = M::groupConversation($cyn, [$this->awa]);
    M::block($this->awa, $cyn);
    M::send($cyn, $group, 'Cinq')->assertCreated();
    expect(DatabaseNotification::query()->count())->toBe(0);
});

it('rend la notification neutre quand la personne masque ses aperçus', function (): void {
    $this->awa->settings()->updateOrCreate(['user_id' => $this->awa->id], ['mask_sensitive_notifications' => true]);

    M::send($this->bea, $this->conversation, 'Bonsoir')->assertCreated();

    $data = DatabaseNotification::query()->where('notifiable_id', $this->awa->id)->sole()->data;
    expect($data['body'])->toBe(__('notifications.new_message.discreet'))
        ->and(json_encode($data))->not->toContain($this->bea->pseudo);
});

it('supprime un message pour tout le monde : son autrice oui (corps effacé), l’autre personne non', function (): void {
    $id = M::send($this->awa, $this->conversation, 'À effacer')->json('data.id');
    $url = '/api/v1/conversations/'.$this->conversation->ulid.'/messages/'.$id;

    FeedFixtures::as($this->bea)->deleteJson($url)->assertForbidden();
    FeedFixtures::as($this->awa)->deleteJson($url)->assertOk()->assertJsonPath('data.deleted', true)->assertJsonPath('data.body', null);
    expect(Message::query()->find($id)->body)->toBe('');

    $other = M::privateConversation($this->awa, M::member());
    FeedFixtures::as($this->awa)->deleteJson('/api/v1/conversations/'.$other->ulid.'/messages/'.$id)->assertNotFound();
});

it('accepte une image ou un PDF en pièce jointe, servi en lien signé, et refuse un autre type', function (): void {
    Storage::fake('local');

    $response = FeedFixtures::as($this->awa)->post('/api/v1/conversations/'.$this->conversation->ulid.'/messages', [
        'client_uuid' => (string) Str::uuid(),
        'attachment' => UploadedFile::fake()->createWithContent('photo.jpg', FeedFixtures::jpegWithExif(200, 100)),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.attachment.kind', 'image');
    $stored = (string) Storage::disk('local')->get((string) Message::query()->find($response->json('data.id'))->media_path);
    expect($response->json('data.attachment.url'))->toContain('signature=')
        ->and($response->json('data.body'))->toBe('')
        ->and($stored)->not->toBe('')
        ->and(str_contains($stored, 'Exif'))->toBeFalse() // ré-encodée par MediaUploader : EXIF (position, appareil) retiré
        ->and(str_contains($stored, 'Xomiton Test'))->toBeFalse();

    FeedFixtures::as($this->awa)->post('/api/v1/conversations/'.$this->conversation->ulid.'/messages', [
        'client_uuid' => (string) Str::uuid(),
        'attachment' => UploadedFile::fake()->createWithContent('script.txt', 'echo 1'),
    ], ['Accept' => 'application/json'])->assertUnprocessable()->assertJsonValidationErrors('attachment');

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertJsonPath('data.0.last_message.preview', __('community.messages.attachment'));
});

it('limite le débit des envois (messages_write) sans bloquer la lecture', function (): void {
    config()->set('xomiton.rate_limits.messages_write_per_minute', 3); // la création de la conversation compte

    M::send($this->awa, $this->conversation)->assertCreated();
    M::send($this->awa, $this->conversation)->assertCreated();
    M::send($this->awa, $this->conversation)->assertTooManyRequests();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$this->conversation->ulid.'/messages')->assertOk();
    expect(Conversation::query()->count())->toBe(1);
});
