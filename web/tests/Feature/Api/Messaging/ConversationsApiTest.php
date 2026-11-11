<?php

declare(strict_types=1);

use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Follow;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\Support\FeedFixtures;
use Tests\Support\MessagingFixtures as M;

/*
 * P8-01, docs/API.md §12.1 : GET / POST conversations, GET / PATCH conversations/{ulid}, GET conversations/recipients —
 * une conversation privée par paire, groupe avec titre et admin, destinataire injoignable en réponse neutre, 404 pour qui
 * n'y participe pas, recherche sans le corps des messages (ADR-33), aperçu calculé à la lecture et masqué en mode discret.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->awa = M::member();
    $this->bea = M::member();
});

it('crée une conversation privée (201) puis rend la même à la seconde demande (200), d’un côté comme de l’autre', function (): void {
    $first = FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['user_id' => $this->bea->id])
        ->assertCreated()
        ->assertJsonPath('data.kind', 'private')
        ->assertJsonPath('data.name', $this->bea->pseudo)
        ->assertJsonPath('data.other.id', $this->bea->id)
        ->assertJsonPath('data.participants_count', 2)
        ->assertJsonPath('data.can_send', true)
        ->assertJsonPath('data.unread_count', 0)
        ->assertJsonPath('data.last_message', null)
        ->assertJsonMissingPath('data.other.email')
        ->assertHeader('Location');

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['user_id' => $this->bea->id])
        ->assertOk()->assertJsonPath('data.id', $first->json('data.id'));
    FeedFixtures::as($this->bea)->postJson('/api/v1/conversations', ['kind' => 'private', 'user_id' => $this->awa->id])
        ->assertOk()->assertJsonPath('data.id', $first->json('data.id'))->assertJsonPath('data.name', $this->awa->pseudo);

    expect(Conversation::query()->count())->toBe(1)
        ->and(ConversationParticipant::query()->count())->toBe(2);
});

it('crée une conversation de groupe : titre obligatoire, créatrice admin, participant·es distinct·es, plafond', function (): void {
    $cyn = M::member();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['kind' => 'group', 'user_ids' => [$this->bea->id]])
        ->assertUnprocessable()->assertJsonValidationErrors('title');

    $response = FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', [
        'kind' => 'group', 'title' => 'Cercle des sœurs', 'user_ids' => [$this->bea->id, $cyn->id],
    ])->assertCreated()->assertJsonPath('data.kind', 'group')->assertJsonPath('data.name', 'Cercle des sœurs')
        ->assertJsonPath('data.participants_count', 3)->assertJsonPath('data.can_manage', true)->assertJsonPath('data.role', 'admin');

    $conversation = Conversation::query()->where('ulid', $response->json('data.id'))->sole();
    expect($conversation->kind)->toBe(ConversationKind::Group)
        ->and($conversation->participants()->admins()->pluck('user_id')->all())->toBe([$this->awa->id]);

    config()->set('xomiton.messaging.group_max_participants', 2);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['kind' => 'group', 'title' => 'Trop', 'user_ids' => [$this->bea->id, $cyn->id]])
        ->assertUnprocessable();
});

it('refuse d’un même message neutre un compte bloqué (dans les deux sens), privé, suspendu, inconnu ou soi-même', function (): void {
    $blocked = M::member();
    $blocker = M::member();
    $private = M::member();
    $suspended = User::factory()->withProfile()->suspended()->create();
    $suspended->assignRole('membre');
    M::block($this->awa, $blocked);
    M::block($blocker, $this->awa);
    M::privateProfile($private);
    $expected = __('community.messages.api.recipient_unavailable');

    foreach ([$blocked->id, $blocker->id, $private->id, $suspended->id, 999999, $this->awa->id] as $id) {
        FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['user_id' => $id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'recipient_unavailable')
            ->assertJsonPath('errors.user_id.0', $expected);
    }

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['kind' => 'group', 'title' => 'Groupe', 'user_ids' => [$this->bea->id, $blocked->id]])
        ->assertUnprocessable()->assertJsonPath('code', 'recipient_unavailable');
    expect(Conversation::query()->count())->toBe(0);
});

it('laisse écrire à un profil « abonné·es » seulement à ses abonné·es', function (): void {
    $this->bea->profile->forceFill(['visibility' => 'followers'])->save();

    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['user_id' => $this->bea->id])->assertUnprocessable();
    Follow::query()->create(['follower_id' => $this->awa->id, 'followed_id' => $this->bea->id]);
    FeedFixtures::as($this->awa)->postJson('/api/v1/conversations', ['user_id' => $this->bea->id])->assertCreated();
});

it('exige un e-mail confirmé pour ouvrir une conversation, et une session', function (): void {
    $unverified = M::member(verified: false);

    FeedFixtures::as($unverified)->postJson('/api/v1/conversations', ['user_id' => $this->bea->id])->assertForbidden();
    app('auth')->forgetGuards();
    $this->withHeaders(['Authorization' => ''])->getJson('/api/v1/conversations')->assertUnauthorized();
});

it('cache une conversation à qui n’y participe pas (404), même à l’administration', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    $admin = M::member('admin');

    foreach ([M::member(), $admin] as $outsider) {
        FeedFixtures::as($outsider)->getJson('/api/v1/conversations/'.$conversation->ulid)->assertNotFound();
        FeedFixtures::as($outsider)->getJson('/api/v1/conversations/'.$conversation->ulid.'/messages')->assertNotFound();
        M::send($outsider, $conversation)->assertNotFound();
        FeedFixtures::as($outsider)->getJson('/api/v1/conversations')->assertOk()->assertJsonCount(0, 'data');
    }

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/'.$conversation->ulid)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
});

it('liste par dernière activité avec non lus, aperçu déchiffré, recherche par pseudonyme et filtre « non lues »', function (): void {
    $cyn = M::member();
    $older = M::privateConversation($this->awa, $this->bea);
    $newer = M::privateConversation($this->awa, $cyn);
    M::send($this->bea, $older, 'Merci pour ton témoignage d’hier.')->assertCreated();
    $this->travel(1)->minutes();
    M::send($this->awa, $newer, 'Tu as pu parler à la juriste ?')->assertCreated();

    $list = FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertOk()
        ->assertJsonPath('meta.poll_seconds', 15)
        ->assertJsonPath('data.0.id', $newer->ulid)
        ->assertJsonPath('data.0.last_message.mine', true)
        ->assertJsonPath('data.0.last_message.preview', 'Tu as pu parler à la juriste ?')
        ->assertJsonPath('data.0.unread_count', 0)
        ->assertJsonPath('data.1.id', $older->ulid)
        ->assertJsonPath('data.1.unread_count', 1)
        ->assertJsonPath('data.1.last_message.preview', 'Merci pour ton témoignage d’hier.');
    expect($list->json('data.1.last_message.preview_hidden'))->toBeFalse();

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?q='.urlencode(mb_substr($cyn->pseudo, 0, 4)))
        ->assertOk()->assertJsonFragment(['id' => $newer->ulid]);
    // ADR-33 : le corps chiffré n'est jamais cherché.
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?q=juriste')->assertOk()->assertJsonCount(0, 'data');
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?filter=unread')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $older->ulid);
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations?filter=nope')->assertUnprocessable();
});

it('masque l’aperçu en mode discret ou quand la personne masque ses aperçus', function (): void {
    $conversation = M::privateConversation($this->awa, $this->bea);
    M::send($this->bea, $conversation, 'Le centre de dépistage est à Zogbo.')->assertCreated();

    $this->awa->profile->forceFill(['discreet_mode' => true])->save();
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertOk()
        ->assertJsonPath('data.0.last_message.preview', null)
        ->assertJsonPath('data.0.last_message.preview_hidden', true)
        ->assertDontSee('Zogbo');

    $this->awa->profile->forceFill(['discreet_mode' => false])->save();
    $this->awa->settings()->updateOrCreate(['user_id' => $this->awa->id], ['mask_sensitive_notifications' => true]);
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations')->assertOk()->assertJsonPath('data.0.last_message.preview_hidden', true);
});

it('renomme un groupe (gestion seulement) et refuse de renommer une conversation privée', function (): void {
    $group = M::groupConversation($this->awa, [$this->bea]);
    $private = M::privateConversation($this->awa, $this->bea);

    FeedFixtures::as($this->bea)->patchJson('/api/v1/conversations/'.$group->ulid, ['title' => 'Nouveau nom'])->assertForbidden();
    FeedFixtures::as($this->awa)->patchJson('/api/v1/conversations/'.$group->ulid, ['title' => 'Nouveau nom'])
        ->assertOk()->assertJsonPath('data.name', 'Nouveau nom');
    FeedFixtures::as($this->awa)->patchJson('/api/v1/conversations/'.$private->ulid, ['title' => 'Nom'])
        ->assertUnprocessable()->assertJsonPath('code', 'conversation_group_only');
});

it('cherche des destinataires par début de pseudonyme, sans comptes bloqués, introuvables ni privés ; connexions sans saisie', function (): void {
    $prefix = 'zx'.mb_strtolower(Str::random(4));
    $found = M::member();
    $found->forceFill(['pseudo' => $prefix.'-awa'])->save();
    $hidden = M::member();
    $hidden->forceFill(['pseudo' => $prefix.'-cache'])->save();
    $hidden->profile->forceFill(['searchable' => false])->save();
    $blocked = M::member();
    $blocked->forceFill(['pseudo' => $prefix.'-bloque'])->save();
    M::block($blocked, $this->awa);
    $private = M::member();
    $private->forceFill(['pseudo' => $prefix.'-prive'])->save();
    M::privateProfile($private);

    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/recipients?q='.$prefix)->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $found->id)->assertJsonMissingPath('data.0.email');

    FeedFixtures::follow($this->awa, $this->bea);
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/recipients')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $this->bea->id);
    FeedFixtures::as($this->awa)->getJson('/api/v1/conversations/recipients?q=a')->assertUnprocessable();
});
