<?php

declare(strict_types=1);

use App\Application\Alerts\Listeners\NotifyAlertRouted;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Moderation\Events\ContentReported;
use App\Domain\Newsletter\Events\CampaignSent;
use App\Domain\Sync\Events\SyncConflictDetected;
use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\ModerationReport;
use App\Models\NewsletterCampaign;
use App\Models\SyncConflict;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\AlertRouted;
use App\Notifications\ContentReported as ContentReportedNotification;
use App\Notifications\SosTriggered as SosTriggeredNotification;
use App\Notifications\SyncConflict as SyncConflictNotification;
use App\Providers\AppServiceProvider;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

/*
 * P3-08 : chaque événement de domaine est écouté une seule fois (AppServiceProvider::DOMAIN_LISTENERS, hors découverte
 * automatique) et déclenche les notifications de P3-02 et le journal d'audit.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
});

function adminOnCall(): User
{
    $admin = User::factory()->withProfile()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('alerts.take');

    return $admin;
}

it('enregistre chaque écouteur de domaine exactement une fois', function (): void {
    $raw = app('events')->getRawListeners();

    foreach (AppServiceProvider::DOMAIN_LISTENERS as $event => $listeners) {
        expect($raw[$event] ?? [])->toBe($listeners, $event);
    }

    expect($raw[AlertCreated::class])->toBe([NotifyAlertRouted::class]);
});

it('AlertCreated → AlertRouted au compte du contact routé et aux admins de garde, une fois par personne, avec audit', function (): void {
    $admin = adminOnCall();
    $contactUser = User::factory()->withProfile()->create();
    $zone = Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral']);
    $contact = DirectoryContact::factory()->linkedTo($contactUser)->inZone($zone)->create();
    $alert = Alert::factory()->routedTo($contact)->create(['zone_id' => $zone->id]);

    AlertCreated::dispatch((int) $alert->id, $alert->type->value, $alert->priority->value, $zone->id, null, $contact->id, 'role_zone', new DateTimeImmutable);

    Notification::assertSentTo($contactUser, AlertRouted::class, fn (AlertRouted $n): bool => $n->alertId === $alert->id && $n->zoneLabel === 'Littoral');
    Notification::assertSentTo($admin, AlertRouted::class);
    Notification::assertSentToTimes($contactUser, AlertRouted::class, 1);
    expect(AuditLog::query()->ofAction('alerts.created')->forSubject($alert)->exists())->toBeTrue();

    // Un SOS n'emprunte pas ce chemin.
    Notification::fake();
    AlertCreated::dispatch((int) $alert->id, 'sos', 'critique', $zone->id, null, $contact->id, null, new DateTimeImmutable);
    Notification::assertNothingSent();
});

it('SosTriggered → cloche des admins de garde, SMS du contact de confiance, jamais la personne elle-même, avec audit', function (): void {
    $admin = adminOnCall();
    $member = User::factory()->withProfile(['trusted_contact_phone' => '+229 01 97 38 88 47'])->create();
    $zone = Zone::factory()->create(['name' => 'Littoral', 'slug' => 'littoral']);
    $alert = Alert::factory()->sos()->fromMember($member)->located()->create(['zone_id' => $zone->id]);

    SosTriggered::dispatch((int) $alert->id, $member->id, $zone->id, null, true, null, new DateTimeImmutable);

    Notification::assertSentTo($admin, SosTriggeredNotification::class, fn (SosTriggeredNotification $n): bool => $n->alertId === $alert->id
        && $n->pseudo === $member->pseudo
        && $n->zoneLabel === 'Littoral'
        && $n->place !== null);
    Notification::assertNotSentTo($member, SosTriggeredNotification::class);
    Notification::assertSentOnDemand(SosTriggeredNotification::class, fn (SosTriggeredNotification $n, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['sms'] === '+229 01 97 38 88 47' && $channels === ['sms']);
    expect(AuditLog::query()->ofAction('alerts.sos_triggered')->forSubject($alert)->value('detail'))->toContain('anonyme=non');
});

it('ContentReported → cloche des modérateur·rices (moderation.manage) sans l’extrait, avec audit', function (): void {
    $moderator = User::factory()->withProfile()->create();
    $moderator->givePermissionTo('moderation.manage');
    $member = User::factory()->withProfile()->create();
    $report = ModerationReport::factory()->create(['reason' => 'Insultes', 'excerpt' => 'Extrait confidentiel']);

    ContentReported::dispatch((int) $report->id, $report->kind->value, 'Insultes', null, new DateTimeImmutable);

    Notification::assertSentTo($moderator, ContentReportedNotification::class, fn (ContentReportedNotification $n): bool => $n->reportId === $report->id
        && $n->reason === 'Insultes'
        && ! str_contains(json_encode($n->toArray($moderator)), 'Extrait confidentiel'));
    Notification::assertNotSentTo($member, ContentReportedNotification::class);
    expect(AuditLog::query()->ofAction('moderation.reported')->forSubject($report)->value('detail'))->toContain('motif=Insultes')->not->toContain('Extrait');
});

it('SyncConflictDetected → cloche des admins (sync.manage) sans les valeurs, avec audit', function (): void {
    $admin = User::factory()->withProfile()->create();
    $admin->givePermissionTo('sync.manage');
    $conflict = SyncConflict::factory()->create(['field' => 'status', 'server_value' => 'valeur serveur', 'device_value' => 'valeur appareil']);

    SyncConflictDetected::dispatch((int) $conflict->id, (int) $conflict->device_id, $conflict->subject_type, (int) $conflict->subject_id, 'status', new DateTimeImmutable);

    Notification::assertSentTo($admin, SyncConflictNotification::class, fn (SyncConflictNotification $n): bool => $n->conflictId === $conflict->id
        && $n->field === 'status'
        && ! str_contains(json_encode($n->toArray($admin)), 'valeur serveur'));
    expect(AuditLog::query()->ofAction('sync.conflict_detected')->forSubject($conflict)->value('detail'))->toContain('champ=status')->not->toContain('valeur');
});

it('CampaignSent → journal d’audit avec les compteurs', function (): void {
    $campaign = NewsletterCampaign::factory()->sent()->create();

    CampaignSent::dispatch((int) $campaign->id, 120, 118, new DateTimeImmutable('2026-10-18 15:00:00'));

    expect(AuditLog::query()->ofAction('newsletter.campaign_sent')->forSubject($campaign)->value('detail'))
        ->toContain('destinataires=120')->toContain('envoyes=118')->toContain('echecs=2');
    Notification::assertNothingSent();
});
