<?php

declare(strict_types=1);

use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\MailTransport;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Mail\TemplateMail;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\DirectoryContact;
use App\Models\MailLog;
use App\Models\MailOutbox;
use App\Models\OnCallShift;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Notifications\FakePushSender;
use Tests\Support\Notifications\FakeSmsSender;

/*
 * P5-03 : alerte routée (signalement, besoin), réattribution, rappel 24 h et escalade : cloche et push dans la requête,
 * e-mail `alerte-routee` au seul contact qui doit agir, déposé dans la boîte d'envoi en requête (aucun SMTP), envoyé
 * directement au cron ; jamais de SMS hors SOS.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class]);
    $this->push = new FakePushSender;
    $this->sms = new FakeSmsSender;
    $this->app->instance(PushSender::class, $this->push);
    $this->app->instance(FcmSender::class, $this->push);
    $this->app->instance(SmsSender::class, $this->sms);
    $this->zone = Zone::query()->firstOrCreate(['slug' => 'littoral'], ['name' => 'Littoral']);
    $this->admin = User::factory()->withProfile()->create();
    $this->admin->assignRole('admin');
    PushSubscription::factory()->forUser($this->admin)->create();
    OnCallShift::factory()->admin()->forUser($this->admin)->create();
    config(['mail.default' => 'array']);
    Mail::fake();
});

function routedReport(DirectoryContact $contact, AlertType $type = AlertType::Report): Alert
{
    $alert = Alert::factory()->ofType($type)->routedTo($contact)->create(['zone_id' => test()->zone->id]);
    AlertCreated::dispatch((int) $alert->id, $type->value, $alert->priority->value, $alert->zone_id, null, $contact->id, 'role_zone', new DateTimeImmutable);

    return $alert;
}

it('dépose l’e-mail alerte-routee dans la boîte d’envoi, sans SMTP, avec le lien signé pour une fiche sans compte', function (): void {
    $contact = DirectoryContact::factory()->inZone($this->zone)->create(['name' => 'HOUNSOU Awa', 'email' => 'juriste@exemple.bj']);

    $alert = routedReport($contact);

    $mail = AlertNotification::query()->forAlert($alert)->onChannel(NotificationChannel::Mail)->first();
    $outbox = MailOutbox::query()->first();

    expect($mail->status)->toBe(DeliveryStatus::Sent)
        ->and($mail->provider)->toBe('outbox')
        ->and($mail->recipient)->toBe('contact:'.$contact->id)
        ->and($outbox->template_slug)->toBe('alerte-routee')
        ->and($outbox->to)->toBe('juriste@exemple.bj')
        ->and($outbox->html)->toContain('HOUNSOU Awa')
        ->and($outbox->html)->toContain('/alertes/'.$alert->ulid.'/accuser')
        ->and(MailLog::query()->value('transport'))->toBe(MailTransport::Outbox)
        ->and(AlertNotification::query()->forAlert($alert)->onChannel(NotificationChannel::Sms)->exists())->toBeFalse();

    Mail::assertNothingSent();
});

it('prévient les admins de garde par cloche et push, sans e-mail', function (): void {
    $contact = DirectoryContact::factory()->inZone($this->zone)->create(['email' => 'juriste@exemple.bj']);

    $alert = routedReport($contact);

    $adminRows = AlertNotification::query()->forAlert($alert)->where('user_id', $this->admin->id)->get();

    expect($adminRows->pluck('channel')->map->value->sort()->values()->all())->toBe(['database', 'push'])
        ->and($adminRows->every(fn (AlertNotification $row): bool => $row->purpose === NotificationPurpose::AlertRouted && $row->status === DeliveryStatus::Sent))->toBeTrue()
        ->and(MailOutbox::query()->count())->toBe(1);
});

it('abandonne l’e-mail d’une fiche sans compte ni adresse et le journalise', function (): void {
    $contact = DirectoryContact::factory()->inZone($this->zone)->create(['email' => null]);
    Log::spy();

    $alert = routedReport($contact);

    $mail = AlertNotification::query()->forAlert($alert)->onChannel(NotificationChannel::Mail)->first();

    expect($mail->status)->toBe(DeliveryStatus::Skipped)->and($mail->last_error)->toBe(AlertNotification::SKIP_NO_ADDRESS);
    Log::shouldHaveReceived('warning')->with('alerts.contact_unreachable', Mockery::on(fn (array $c): bool => $c['contact'] === $contact->id && $c['channel'] === 'mail'));
});

it('respecte les préférences « alertes » du compte lié à la fiche', function (): void {
    $user = User::factory()->withProfile([], ['notification_prefs' => ['alertes' => ['in_app' => true, 'email' => false, 'push' => false]]])->create();
    PushSubscription::factory()->forUser($user)->create();
    $contact = DirectoryContact::factory()->linkedTo($user)->inZone($this->zone)->create();

    $alert = routedReport($contact);

    $channels = AlertNotification::query()->forAlert($alert)->where('user_id', $user->id)->pluck('channel')->map->value->all();

    expect($channels)->toBe(['database'])
        ->and(MailOutbox::query()->count())->toBe(0);
});

it('notifie le seul nouveau contact d’une réattribution', function (): void {
    $first = DirectoryContact::factory()->inZone($this->zone)->create(['email' => 'premier@exemple.bj']);
    $alert = routedReport($first);
    $second = DirectoryContact::factory()->inZone($this->zone)->create(['email' => 'second@exemple.bj']);
    $alert->update(['routed_to' => $second->id]);

    AlertReassigned::dispatch((int) $alert->id, $alert->type->value, $first->id, $second->id, $this->admin->id, new DateTimeImmutable);

    expect(MailOutbox::query()->pluck('to')->all())->toBe(['premier@exemple.bj', 'second@exemple.bj'])
        ->and(AlertNotification::query()->forAlert($alert)->where('recipient', 'contact:'.$second->id)->value('purpose'))->toBe(NotificationPurpose::AlertRouted);
});

it('envoie le rappel 24 h directement au cron, titre « Rappel »', function (): void {
    $user = User::factory()->withProfile()->create(['email' => 'pair@exemple.bj']);
    $contact = DirectoryContact::factory()->linkedTo($user)->inZone($this->zone)->create();
    $alert = Alert::factory()->routedTo($contact)->create(['zone_id' => $this->zone->id]);

    AlertReminderDue::dispatch((int) $alert->id, $alert->type->value, $contact->id, [$this->admin->id], 1440, new DateTimeImmutable);

    $rows = AlertNotification::query()->forAlert($alert)->ofPurpose(NotificationPurpose::AlertReminder)->get();

    expect($rows->where('channel', NotificationChannel::Mail)->first()?->status)->toBe(DeliveryStatus::Sent)
        ->and(MailOutbox::query()->count())->toBe(0)
        ->and($user->notifications()->first()->data['title'])->toBe(__('notifications.alert_routed.reminder_title'))
        ->and(MailLog::query()->value('context'))->toBe('alerte_rappel');

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo('pair@exemple.bj'));
});

it('escalade un SOS au point focal national : cloche, push, SMS et e-mail direct ; un signalement sans SMS', function (): void {
    $nationalUser = User::factory()->withProfile()->create(['email' => 'national@exemple.bj', 'phone' => '+229 01 21 00 00 00']);
    PushSubscription::factory()->forUser($nationalUser)->create();
    $national = DirectoryContact::factory()->focalNational()->linkedTo($nationalUser)->create();
    $sos = Alert::factory()->sos()->create(['zone_id' => $this->zone->id]);

    AlertEscalated::dispatch((int) $sos->id, 'sos', null, $national->id, [], 'no_ack_5min', 6, new DateTimeImmutable);

    $channels = AlertNotification::query()->forAlert($sos)->ofPurpose(NotificationPurpose::SosUnacknowledged)->where('user_id', $nationalUser->id)->get();

    expect($channels->pluck('channel')->map->value->sort()->values()->all())->toBe(['database', 'mail', 'push', 'sms'])
        ->and($channels->every(fn (AlertNotification $row): bool => $row->status === DeliveryStatus::Sent))->toBeTrue()
        ->and($this->sms->calls[0]['text'])->toContain('6 min');

    Mail::assertSent(TemplateMail::class, fn (TemplateMail $mail): bool => $mail->hasTo('national@exemple.bj'));

    // Signalement à 48 h, sans point focal national : les admins déjà prévenus à la création le sont à nouveau, sans SMS.
    $report = routedReport(DirectoryContact::factory()->inZone($this->zone)->create());
    AlertEscalated::dispatch((int) $report->id, 'denonciation', $report->routed_to, null, [$this->admin->id], 'no_ack_48h', 2880, new DateTimeImmutable);

    $escalated = AlertNotification::query()->forAlert($report)->ofPurpose(NotificationPurpose::AlertEscalated)->get();

    expect($escalated->pluck('recipient')->all())->toContain('user:'.$this->admin->id)
        ->and($escalated->every(fn (AlertNotification $row): bool => $row->status === DeliveryStatus::Sent))->toBeTrue()
        ->and(AlertNotification::query()->forAlert($report)->onChannel(NotificationChannel::Sms)->exists())->toBeFalse();
});
