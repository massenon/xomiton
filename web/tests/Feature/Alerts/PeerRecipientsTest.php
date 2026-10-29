<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\Recipients\SosRecipients;
use App\Application\Alerts\OnCallRoster;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Alerts\Events\SosTriggered;
use App\Enums\AlertStatus;
use App\Enums\NotificationPurpose;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\User;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Support\FeedFixtures as F;
use Tests\Support\SosScenario;

/*
 * Décision client du 2026-10-29 : les SOS et alertes préviennent les ADMINS et les PAIRS ÉDUCATEUR·RICES (pas seulement
 * l'admin de garde). Le pair prévenu peut ouvrir l'alerte, accuser réception et la prendre en charge ; l'accusé arrête
 * l'escalade pour toute l'équipe ; un pair jamais prévenu reste exclu (cloisonnement).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class]);
    $this->freezeSecond();
});

function peerFor(array $attributes = []): User
{
    $peer = User::factory()->withProfile()->create($attributes);
    $peer->syncRoles(['membre', 'pair']);

    return $peer;
}

function sosEvent(Alert $alert): void
{
    SosTriggered::dispatch((int) $alert->id, $alert->user_id, $alert->zone_id, $alert->commune_id, true, $alert->routed_to, new DateTimeImmutable);
}

it('prévient tous les admins et tous les pairs actifs d’un SOS, jamais un membre, un pair suspendu ou l’auteur·e', function (): void {
    $scene = SosScenario::make();
    $secondAdmin = F::member('admin');
    $secondAdmin->givePermissionTo('alerts.take');
    $peer = peerFor();
    $otherPeer = peerFor();
    $suspendedPeer = peerFor(['status' => UserStatus::Suspended]);
    $authorPeer = $scene->member;
    $authorPeer->syncRoles(['membre', 'pair']);
    $plainMember = F::member('membre');

    $references = collect(app(SosRecipients::class)->for($scene->alert))->map->reference->all();

    expect($references)->toContain('user:'.$scene->admin->id, 'user:'.$secondAdmin->id, 'user:'.$peer->id, 'user:'.$otherPeer->id)
        ->and($references)->not->toContain('user:'.$suspendedPeer->id, 'user:'.$plainMember->id, 'user:'.$authorPeer->id);
});

it('écrit cloche et push pour chaque pair prévenu d’un SOS', function (): void {
    $scene = SosScenario::make();
    $peer = peerFor();
    App\Models\PushSubscription::factory()->forUser($peer)->create();
    sosEvent($scene->alert);

    $rows = AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosTriggered)->where('user_id', $peer->id)->get();

    expect($rows->pluck('channel')->map->value->all())->toContain('database', 'push');
    expect($scene->push->calls)->not->toBeEmpty();
});

it('ne prévient que les pairs du département avec peer_recipients = zone, et se replie sur tous les pairs s’il n’y en a pas', function (): void {
    $scene = SosScenario::make();
    $inZone = peerFor(['zone_id' => $scene->zone->id]);
    $elsewhere = peerFor(['zone_id' => App\Models\Zone::factory()->create()->id]);

    config(['xomiton.alerts.peer_recipients' => 'all']);
    expect(app(OnCallRoster::class)->alertTeam(null, $scene->alert)->pluck('id')->all())->toContain($inZone->id, $elsewhere->id);

    config(['xomiton.alerts.peer_recipients' => 'zone']);
    $ids = app(OnCallRoster::class)->alertTeam(null, $scene->alert)->pluck('id')->all();
    expect($ids)->toContain($inZone->id)->not->toContain($elsewhere->id);

    $inZone->update(['zone_id' => null]);
    $ids = app(OnCallRoster::class)->alertTeam(null, $scene->alert)->pluck('id')->all();
    expect($ids)->toContain($inZone->id, $elsewhere->id);
});

it('laisse le pair prévenu ouvrir l’alerte, accuser réception et la prendre en charge, via l’API pair', function (): void {
    $scene = SosScenario::make();
    $peer = peerFor();
    sosEvent($scene->alert);

    $list = F::as($peer)->getJson('/api/v1/pair/alerts')->assertOk();
    expect(array_column($list->json('data'), 'id'))->toContain($scene->alert->ulid);

    F::as($peer)->getJson('/api/v1/pair/alerts/'.$scene->alert->ulid)->assertOk()->assertJsonPath('data.can.acknowledge', true);
    F::as($peer)->postJson('/api/v1/pair/alerts/'.$scene->alert->ulid.'/acknowledge')->assertOk()->assertJsonPath('meta.acknowledged_now', true);
    F::as($peer)->postJson('/api/v1/pair/alerts/'.$scene->alert->ulid.'/status', ['status' => 'en_cours'])->assertOk();

    expect($scene->alert->fresh()->acknowledged_by)->toBe($peer->id)
        ->and($scene->alert->fresh()->status)->toBe(AlertStatus::InProgress);
});

it('ferme l’alerte au pair qui n’a pas été prévenu (cloisonnement conservé) et à un membre', function (): void {
    $scene = SosScenario::make();
    $notified = peerFor();
    sosEvent($scene->alert);
    // Pair créé après l'envoi : jamais prévenu de ce SOS.
    $late = peerFor();

    F::as($late)->getJson('/api/v1/pair/alerts')->assertOk()->assertJsonCount(0, 'data');
    F::as($late)->getJson('/api/v1/pair/alerts/'.$scene->alert->ulid)->assertForbidden();
    F::as($late)->postJson('/api/v1/pair/alerts/'.$scene->alert->ulid.'/acknowledge')->assertForbidden();
    F::as(F::member('membre'))->getJson('/api/v1/pair/alerts')->assertForbidden();

    expect($scene->alert->fresh()->acknowledged_at)->toBeNull();
    F::as($notified)->getJson('/api/v1/pair/alerts/'.$scene->alert->ulid)->assertOk();
});

it('n’ouvre pas à un pair les alertes où il n’a été prévenu qu’en tant qu’auteur·e', function (): void {
    $scene = SosScenario::make();
    $authorPeer = $scene->member;
    $authorPeer->syncRoles(['membre', 'pair']);
    AlertNotification::factory()->forAlert($scene->alert)->create([
        'user_id' => $authorPeer->id,
        'recipient' => 'user:'.$authorPeer->id,
        'purpose' => NotificationPurpose::SosAcknowledged->value,
        'channel' => 'database',
    ]);

    F::as($authorPeer)->getJson('/api/v1/pair/alerts/'.$scene->alert->ulid)->assertForbidden();
});

it('escalade vers le point focal national et l’équipe (admins + pairs) ; l’accusé d’un pair arrête l’escalade', function (): void {
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);
    Event::listen(AlertEscalated::class, static fn (): null => null);
    Event::listen(AlertReminderDue::class, static fn (): null => null);
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);

    $scene = SosScenario::make();
    $peer = peerFor();
    $alert = $scene->alert;
    $alert->forceFill(['created_at' => now()->subMinutes(6)])->save();

    $this->artisan('alerts:escalate')->assertSuccessful();

    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->alertId === $alert->id
        && $e->toContactId === $scene->national->id
        && in_array($peer->id, $e->adminIds, true)
        && in_array($scene->admin->id, $e->adminIds, true));
});

it('n’escalade pas un SOS dont un pair a accusé réception', function (): void {
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);
    Event::listen(AlertEscalated::class, static fn (): null => null);
    Event::listen(AlertReminderDue::class, static fn (): null => null);
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);

    $scene = SosScenario::make();
    $peer = peerFor();
    sosEvent($scene->alert);
    $scene->alert->forceFill(['created_at' => now()->subMinutes(6)])->save();

    F::as($peer)->postJson('/api/v1/pair/alerts/'.$scene->alert->ulid.'/acknowledge')->assertOk();

    $this->artisan('alerts:escalate')->assertSuccessful();

    Event::assertNotDispatched(AlertEscalated::class);
});
