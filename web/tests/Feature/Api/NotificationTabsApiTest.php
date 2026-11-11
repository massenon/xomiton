<?php

declare(strict_types=1);

use App\Application\Notifications\NotificationTab;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Notifications\EventCreatedInMyGroup;
use App\Notifications\EventReminder;
use App\Notifications\ForumReplyPosted;
use App\Notifications\GroupJoinRequested;
use App\Notifications\InvitationReceived;
use App\Notifications\NewMessage;
use App\Notifications\SosTriggered;
use App\Notifications\SyncConflict;
use Database\Seeders\RolesAndPermissionsSeeder;

/*
 * P8-10 (lot parametres-notifications) : filtres de la page /notifications sur l'API de la cloche (P6-07) — `?tab=`
 * (invitations, events, exchanges, system), compteurs `tabs` de `unread-count`, `tab` de chaque notification,
 * `read-all` limité à un filtre. Règle unique NotificationTab : le filtre SQL et le classement en PHP concordent.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->user = User::factory()->withProfile()->create();
    $this->user->assignRole('membre');
    $this->token = $this->user->createToken('Pixel 7')->plainTextToken;
});

function tabbed(User $user, string $type, string $category, bool $read = false): DatabaseNotification
{
    $factory = DatabaseNotification::factory()->forUser($user)->inCategory($category);

    return ($read ? $factory->read() : $factory)->create(['type' => $type]);
}

/** Une notification de chaque sorte : 2 invitations, 2 événements, 2 échanges, 2 système (dont une lue). */
function mixedInbox(User $user): void
{
    tabbed($user, InvitationReceived::class, 'communaute');
    tabbed($user, GroupJoinRequested::class, 'communaute', read: true);
    tabbed($user, EventReminder::class, 'evenements');
    tabbed($user, EventCreatedInMyGroup::class, 'communaute');
    tabbed($user, ForumReplyPosted::class, 'communaute');
    tabbed($user, NewMessage::class, 'messages');
    tabbed($user, SosTriggered::class, 'sos');
    tabbed($user, SyncConflict::class, 'systeme', read: true);
}

it('classe chaque notification dans un seul filtre, en SQL comme en PHP', function (): void {
    mixedInbox($this->user);

    foreach ([NotificationTab::Invitations, NotificationTab::Events, NotificationTab::Exchanges, NotificationTab::System] as $tab) {
        $response = $this->withToken($this->token)->getJson('/api/v1/notifications?tab='.$tab->value)->assertOk()
            ->assertJsonPath('meta.tab', $tab->value)
            ->assertJsonCount(2, 'data');

        expect(collect($response->json('data'))->pluck('tab')->unique()->all())->toBe([$tab->value]);
    }

    $this->withToken($this->token)->getJson('/api/v1/notifications?tab=all')->assertOk()->assertJsonCount(8, 'data')->assertJsonPath('meta.tab', 'all');
    $this->withToken($this->token)->getJson('/api/v1/notifications?tab=secret')->assertUnprocessable()->assertJsonValidationErrors('tab');
});

it('compte les non lues par filtre dans unread-count', function (): void {
    mixedInbox($this->user);
    tabbed(User::factory()->create(), InvitationReceived::class, 'communaute');

    $this->withToken($this->token)->getJson('/api/v1/notifications/unread-count')
        ->assertOk()
        ->assertJsonPath('data.tabs', [
            'all' => ['all' => 8, 'unread' => 6],
            'invitations' => ['all' => 2, 'unread' => 1],
            'events' => ['all' => 2, 'unread' => 2],
            'exchanges' => ['all' => 2, 'unread' => 2],
            'system' => ['all' => 2, 'unread' => 1],
        ]);
});

it('marque comme lues les seules notifications d’un filtre et renvoie les compteurs par filtre à la lecture', function (): void {
    mixedInbox($this->user);

    $this->withToken($this->token)->postJson('/api/v1/notifications/read-all', ['tab' => 'events'])
        ->assertOk()->assertJsonPath('data.marked', 2)->assertJsonPath('data.unread', 4);

    $message = DatabaseNotification::query()->where('type', NewMessage::class)->firstOrFail();
    $this->withToken($this->token)->postJson('/api/v1/notifications/'.$message->id.'/read')
        ->assertOk()
        ->assertJsonPath('data.tab', 'exchanges')
        ->assertJsonPath('meta.unread', 3)
        ->assertJsonPath('meta.tabs.exchanges', ['all' => 2, 'unread' => 1]);
});
