<?php

declare(strict_types=1);

use App\Models\DatabaseNotification;
use App\Models\Event;
use App\Models\EventRsvp;
use App\Models\User;
use App\Models\UserBlock;
use App\Notifications\EventReminder;

/*
 * P8-04 : `events:remind` envoie le rappel de la veille (cloche, push, e-mail `rappel-evenement`) aux participant·es
 * confirmé·es qui l'ont gardé, une seule fois par événement ; jamais l'adresse exacte ni le lien de connexion.
 */

function participant(Event $event, bool $reminder = true, string $status = 'going', ?User $user = null): User
{
    $user ??= User::factory()->withProfile()->create();
    EventRsvp::factory()->create(['event_id' => $event->id, 'user_id' => $user->id, 'status' => $status, 'reminder_opt_in' => $reminder]);

    return $user;
}

it('rappelle la veille aux participant·es qui l’ont gardé, une seule fois, sans l’adresse exacte', function (): void {
    $event = Event::factory()->withAddress('Maison des jeunes, salle 3')->create([
        'title' => 'Atelier « Dire non »', 'place' => 'Porto-Novo', 'starts_at' => now()->addHours(20),
    ]);
    $going = participant($event);
    participant($event, reminder: false);
    participant($event, status: 'interested');
    $later = Event::factory()->create(['starts_at' => now()->addDays(3)]);
    participant($later);
    $draft = Event::factory()->draft()->create(['starts_at' => now()->addHours(5)]);
    participant($draft);

    $this->artisan('events:remind')->expectsOutputToContain('1 événement rappelé, 1 rappel envoyé')->assertSuccessful();

    $bell = DatabaseNotification::query()->where('type', EventReminder::class)->sole();
    expect($bell->notifiable_id)->toBe($going->id)
        ->and($bell->category)->toBe(DatabaseNotification::CATEGORY_EVENTS)
        ->and($bell->data['title'])->toBe(__('notifications.event_reminder.title', ['title' => 'Atelier « Dire non »']))
        ->and($bell->data['body'])->toContain('Porto-Novo')->not->toContain('Maison des jeunes')
        ->and($bell->data['url'])->toEndWith('/evenements/'.$event->ulid)
        ->and($event->fresh()->reminder_sent_at)->not->toBeNull()
        ->and($later->fresh()->reminder_sent_at)->toBeNull();

    $this->artisan('events:remind')->expectsOutputToContain('0 événement rappelé, 0 rappel envoyé')->assertSuccessful();
    expect(DatabaseNotification::query()->where('type', EventReminder::class)->count())->toBe(1);
});

it('rappelle un événement en ligne sans lien de connexion et refuse un lot invalide', function (): void {
    $event = Event::factory()->create(['place' => null, 'is_online' => true, 'online_url' => 'https://visio.example.org/x', 'starts_at' => now()->addHours(2)]);
    participant($event);

    $this->artisan('events:remind', ['--chunk' => 0])->assertExitCode(2);
    $this->artisan('events:remind')->assertSuccessful();

    $bell = DatabaseNotification::query()->where('type', EventReminder::class)->sole();
    expect($bell->data['body'])->toContain('en ligne')->not->toContain('visio.example.org');
});

it('ne rappelle pas une participante qui bloque l’organisatrice ou qu’elle bloque', function (): void {
    $event = Event::factory()->create(['starts_at' => now()->addHours(6)]);
    $kept = participant($event);
    $blocking = participant($event);
    $blocked = participant($event);
    UserBlock::factory()->create(['user_id' => $blocking->id, 'blocked_user_id' => $event->organizer_id]);
    UserBlock::factory()->create(['user_id' => $event->organizer_id, 'blocked_user_id' => $blocked->id]);

    $this->artisan('events:remind')->expectsOutputToContain('1 rappel envoyé')->assertSuccessful();

    expect(DatabaseNotification::query()->where('type', EventReminder::class)->pluck('notifiable_id')->all())->toBe([$kept->id]);
});
