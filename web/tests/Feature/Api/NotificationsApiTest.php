<?php

declare(strict_types=1);

use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\InvitationReceived;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
 * P6-07 (lot api-me) : cloche de la personne connectée — liste paginée (`filter=all|unread|read`, `category`),
 * compteurs par filtre et par catégorie, lecture, tout marquer comme lu, suppression ; 404 pour la notification
 * d'un autre compte ; texte neutralisé à la lecture si la personne masque les aperçus.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->user = User::factory()->withProfile([], ['mask_sensitive_notifications' => false])->create();
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

function bell(User $user, string $category, bool $read = false, int $minutesAgo = 0): DatabaseNotification
{
    $factory = DatabaseNotification::factory()->forUser($user)->inCategory($category);

    return ($read ? $factory->read() : $factory)->create(['created_at' => now()->subMinutes($minutesAgo)]);
}

it('répond 401 sans jeton', function (string $method, string $uri): void {
    $this->json($method, $uri)->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
})->with([
    ['GET', '/api/v1/notifications'], ['GET', '/api/v1/notifications/unread-count'], ['POST', '/api/v1/notifications/read-all'],
]);

it('liste mes notifications, les plus récentes d’abord, paginées par page avec le nombre de non lues', function (): void {
    $old = bell($this->user, 'messages', minutesAgo: 30);
    $recent = bell($this->user, 'communaute', minutesAgo: 1);
    bell($this->user, 'evenements', read: true, minutesAgo: 10);
    bell(User::factory()->create(), 'messages');

    $response = $this->withToken($this->token)->getJson('/api/v1/notifications?per_page=2')->assertOk();

    $response->assertJsonPath('data.0.id', $recent->id)
        ->assertJsonPath('data.0.read', false)
        ->assertJsonPath('data.0.category', 'communaute')
        ->assertJsonStructure(['data' => [['id', 'type', 'category', 'title', 'body', 'url', 'icon', 'sensitive', 'read', 'read_at', 'created_at', 'data']]])
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.last_page', 2)
        ->assertJsonPath('meta.unread', 2)
        ->assertJsonPath('meta.filter', 'all');

    expect($response->json('links.next'))->toContain('per_page=2');

    $this->withToken($this->token)->getJson('/api/v1/notifications?filter=unread')
        ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.1.id', $old->id);
    $this->withToken($this->token)->getJson('/api/v1/notifications?filter=read')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.read', true);
    $this->withToken($this->token)->getJson('/api/v1/notifications?filter=unread&category=messages')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.category', 'messages');
});

it('refuse un filtre ou une catégorie inconnus (422)', function (): void {
    $this->withToken($this->token)->getJson('/api/v1/notifications?filter=archived&category=stock')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['filter', 'category']);
});

it('compte les notifications par filtre et par catégorie', function (): void {
    bell($this->user, 'messages');
    bell($this->user, 'messages', read: true);
    bell($this->user, 'sos');

    $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.all', 3)
        ->assertJsonPath('data.unread', 2)
        ->assertJsonPath('data.read', 1)
        ->assertJsonPath('data.categories.messages', ['all' => 2, 'unread' => 1])
        ->assertJsonPath('data.categories.sos', ['all' => 1, 'unread' => 1])
        ->assertJsonPath('data.categories.communaute', ['all' => 0, 'unread' => 0]);
});

it('marque une notification comme lue (idempotent) et renvoie le nombre de non lues', function (): void {
    $first = bell($this->user, 'messages');
    bell($this->user, 'messages');

    $this->withToken($this->token)->postJson("/api/v1/notifications/{$first->id}/read")
        ->assertOk()
        ->assertJsonPath('data.read', true)
        ->assertJsonPath('meta.unread', 1);

    $readAt = $first->fresh()->read_at;
    $this->travel(5)->minutes();

    $this->withToken($this->token)->postJson("/api/v1/notifications/{$first->id}/read")->assertOk();
    expect($first->fresh()->read_at?->equalTo($readAt))->toBeTrue();
});

it('marque tout comme lu, éventuellement pour une seule catégorie', function (): void {
    bell($this->user, 'messages');
    bell($this->user, 'communaute');
    bell($this->user, 'communaute');
    $foreign = bell(User::factory()->create(), 'communaute');

    $this->withToken($this->token)->postJson('/api/v1/notifications/read-all', ['category' => 'communaute'])
        ->assertOk()->assertExactJson(['data' => ['marked' => 2, 'unread' => 1]]);

    $this->withToken($this->token)->postJson('/api/v1/notifications/read-all')
        ->assertOk()->assertExactJson(['data' => ['marked' => 1, 'unread' => 0]]);

    expect($foreign->fresh()->read_at)->toBeNull();
});

it('supprime une notification (204) ; celle d’un autre compte est introuvable (404)', function (): void {
    $mine = bell($this->user, 'messages');
    $foreign = bell(User::factory()->create(), 'messages');

    $this->withToken($this->token)->deleteJson("/api/v1/notifications/{$mine->id}")->assertNoContent();
    expect(DatabaseNotification::query()->find($mine->id))->toBeNull();

    $this->withToken($this->token)->deleteJson("/api/v1/notifications/{$foreign->id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    $this->withToken($this->token)->postJson("/api/v1/notifications/{$foreign->id}/read")->assertNotFound();
    $this->withToken($this->token)->postJson('/api/v1/notifications/pas-un-uuid/read')->assertNotFound();

    expect($foreign->fresh())->not->toBeNull()->and($foreign->fresh()->read_at)->toBeNull();
});

it('refuse par la policy toute action sur la notification d’autrui', function (): void {
    $foreign = bell(User::factory()->create(), 'messages');

    expect($this->user->can('view', $foreign))->toBeFalse()
        ->and($this->user->can('update', $foreign))->toBeFalse()
        ->and($this->user->can('delete', $foreign))->toBeFalse()
        ->and($foreign->notifiable->can('delete', $foreign))->toBeTrue();
});

it('neutralise à la lecture une notification sensible quand la personne masque les aperçus', function (): void {
    $sensitive = DatabaseNotification::factory()->forUser($this->user)->sensitive()->create([
        'data' => ['title' => 'Chantal t’a écrit', 'body' => 'Je suis devant la clinique', 'url' => '/messages/4', 'icon' => 'chat', 'id' => 4],
    ]);

    $this->withToken($this->token)->getJson('/api/v1/notifications')
        ->assertJsonPath('data.0.title', 'Chantal t’a écrit')
        ->assertJsonPath('data.0.data.id', 4);

    $this->user->settings->forceFill(['mask_sensitive_notifications' => true])->save();
    app('auth')->forgetGuards();

    $response = $this->withToken($this->token)->getJson('/api/v1/notifications')->assertOk();

    $response->assertJsonPath('data.0.id', $sensitive->id)
        ->assertJsonPath('data.0.title', __('notifications.discreet.title'))
        ->assertJsonPath('data.0.body', __('notifications.discreet.body'))
        ->assertJsonPath('data.0.sensitive', true);

    expect($response->getContent())->not->toContain('clinique');
});

it('expose une vraie notification Xomiton avec son type court (même valeur que la charge push)', function (): void {
    $this->user->notify(new InvitationReceived(7, 'Mariam A.'));

    $this->withToken($this->token)->getJson('/api/v1/notifications')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'invitation_received')
        ->assertJsonPath('data.0.category', 'communaute');
});
