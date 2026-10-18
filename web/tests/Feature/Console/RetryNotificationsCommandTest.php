<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\RetryAlertNotifications;
use App\Console\Commands\Alerts\RetryNotificationsCommand;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\PushSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Tests\Support\Notifications\FakePushSender;
use Tests\Support\Notifications\FakeSmsSender;

/*
 * `notifications:retry` (P5-03, D7) : rejoue chaque minute les remises en attente ou échouées, SOS d'abord, avec des
 * délais croissants et un plafond ; abandonne ce qui n'a plus de sens (alerte prise en charge, annulée, close) ;
 * SMS de repli au rejeu ; idempotent ; marqueur de succès pour la supervision.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->push = new FakePushSender;
    $this->sms = new FakeSmsSender;
    $this->app->instance(PushSender::class, $this->push);
    $this->app->instance(FcmSender::class, $this->push);
    $this->app->instance(SmsSender::class, $this->sms);
    $this->freezeSecond();
    $this->contact = DirectoryContact::factory()->focalZone()->create(['phone' => '+229 01 64 40 40 40']);
    $this->sos = Alert::factory()->sos()->routedTo($this->contact)->create();
});

function failedSms(Alert $alert, DirectoryContact $contact, int $attempts = 1): AlertNotification
{
    return AlertNotification::factory()->forAlert($alert)->toContact($contact)->failed('HTTP 500', $attempts)->create();
}

it('rejoue une ligne échue, laisse une ligne future et l’affiche dans le bilan', function (): void {
    $due = failedSms($this->sos, $this->contact);
    $later = AlertNotification::factory()->forAlert($this->sos)->toContact(DirectoryContact::factory()->create())->failed()->create(['next_retry_at' => now()->addMinutes(5)]);

    $this->artisan('notifications:retry')
        ->expectsOutputToContain('1 ligne examinée, 1 envoyée')
        ->assertSuccessful();

    expect($due->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($due->fresh()->attempts)->toBe(2)
        ->and($due->fresh()->sent_at)->not->toBeNull()
        ->and($due->fresh()->provider)->toBe('log')
        ->and($later->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($this->sms->calls[0]['to'])->toBe('+229 01 64 40 40 40');
});

it('replanifie un échec selon les délais croissants', function (): void {
    $this->sms->willReturn(DeliveryResult::failed('HTTP 503'));
    $row = failedSms($this->sos, $this->contact, attempts: 2);

    $this->artisan('notifications:retry')->assertSuccessful();
    expect($row->fresh()->attempts)->toBe(3)
        ->and($row->fresh()->next_retry_at->equalTo(now()->addMinutes(2)))->toBeTrue();

    $this->travel(1)->minutes();
    $this->artisan('notifications:retry')->assertSuccessful();
    expect($row->fresh()->attempts)->toBe(3);

    $this->travel(1)->minutes();
    $this->artisan('notifications:retry')->assertSuccessful();
    expect($row->fresh()->attempts)->toBe(4)
        ->and($row->fresh()->next_retry_at->equalTo(now()->addMinutes(5)))->toBeTrue();
});

it('abandonne au plafond et le journalise sans IP, acteur système', function (): void {
    $this->sms->willReturn(DeliveryResult::failed('HTTP 503'));
    $row = failedSms($this->sos, $this->contact, attempts: 9);

    $this->artisan('notifications:retry')->expectsOutputToContain('#'.$row->id.' (sms) abandonnée')->assertSuccessful();

    $audit = AuditLog::query()->ofAction('alerts.notification_exhausted')->first();

    expect($row->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($row->fresh()->last_error)->toBe(AlertNotification::SKIP_EXHAUSTED)
        ->and($row->fresh()->attempts)->toBe(10)
        ->and($audit->detail)->toBe('canal=sms ; destinataire=fiche#'.$this->contact->id.' ; tentatives=10')
        ->and($audit->actor)->toBe(AuditLog::ACTOR_SYSTEM)
        ->and($audit->ip)->toBeNull();
});

it('abandonne sans envoyer quand l’alerte est prise en charge, annulée ou close, et ignore les objets non rejouables', function (): void {
    $acknowledged = failedSms(Alert::factory()->sos()->acknowledged()->create(), $this->contact);
    $cancelled = failedSms(Alert::factory()->sos()->cancelled()->create(), $this->contact);
    $closed = failedSms(Alert::factory()->sos()->create(['status' => AlertStatus::Closed]), $this->contact);
    $notRetryable = AlertNotification::factory()->forAlert($this->sos)->toContact($this->contact)->purpose(NotificationPurpose::SosCancelled)->failed()->create();

    $this->artisan('notifications:retry')->assertSuccessful();

    expect($acknowledged->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($acknowledged->fresh()->last_error)->toBe(AlertNotification::SKIP_HANDLED)
        ->and($cancelled->fresh()->last_error)->toBe(AlertNotification::SKIP_CANCELLED)
        ->and($closed->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($notRetryable->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($this->sms->calls)->toBe([]);
});

it('ajoute le SMS de repli d’un compte dont les push ont échoué deux fois, sous le plafond des admins', function (): void {
    config(['xomiton.alerts.sos_admin_sms_max' => 1]);
    $admins = User::factory()->count(2)->withProfile()->create();

    foreach ($admins as $admin) {
        foreach (PushSubscription::factory()->count(2)->forUser($admin)->create() as $subscription) {
            AlertNotification::factory()->forAlert($this->sos)->toSubscription($subscription)->failed('HTTP 500', 2)->create(['next_retry_at' => now()->addMinutes(5)]);
        }
    }

    $this->artisan('notifications:retry')->expectsOutputToContain('1 SMS de repli')->assertSuccessful();

    $sms = AlertNotification::query()->forAlert($this->sos)->onChannel(NotificationChannel::Sms)->where('recipient', 'like', 'user:%')->get();

    expect($sms)->toHaveCount(1)
        ->and($sms->first()->status)->toBe(DeliveryStatus::Sent)
        ->and($sms->first()->recipient)->toBe('user:'.$admins[0]->id);
});

it('supprime l’abonnement disparu au rejeu et abandonne sa ligne', function (): void {
    $this->push->willReturn(DeliveryResult::gone('HTTP 410'));
    $subscription = PushSubscription::factory()->create();
    $row = AlertNotification::factory()->forAlert($this->sos)->toSubscription($subscription)->failed()->create();

    $this->artisan('notifications:retry')->assertSuccessful();

    expect($row->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and(PushSubscription::query()->find($subscription->id))->toBeNull();
});

it('replie sur le SMS au rejeu quand le dernier push d’un compte vise un abonnement disparu (§7.3 n° 37)', function (): void {
    $this->push->willReturn(DeliveryResult::gone('HTTP 410'));
    $admin = User::factory()->withProfile()->create(['phone' => '+229 01 90 00 00 07']);
    $subscription = PushSubscription::factory()->forUser($admin)->create();
    $row = AlertNotification::factory()->forAlert($this->sos)->toSubscription($subscription)->failed()->create();

    $this->artisan('notifications:retry')->assertSuccessful();

    $sms = AlertNotification::query()->forAlert($this->sos)->onChannel(NotificationChannel::Sms)->where('recipient', 'user:'.$admin->id)->first();

    expect($row->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($row->fresh()->last_error)->toBe(AlertNotification::SKIP_GONE)
        ->and($row->fresh()->lastErrorLabel())->toBe('Abonnement supprimé')
        ->and($sms?->status)->toBe(DeliveryStatus::Sent)
        ->and(collect($this->sms->calls)->pluck('to'))->toContain('+229 01 90 00 00 07');
});

it('compte au rejeu le plafond des SMS d’admins sur tous les objets du SOS', function (): void {
    config(['xomiton.alerts.sos_admin_sms_max' => 1]);
    [$first, $second] = User::factory()->count(2)->withProfile()->create()->all();
    AlertNotification::factory()->forAlert($this->sos)->toUser($first, NotificationChannel::Sms)->sent()->create();

    foreach ([$second, $first] as $admin) {
        foreach (PushSubscription::factory()->count(2)->forUser($admin)->create() as $subscription) {
            AlertNotification::factory()->forAlert($this->sos)->purpose(NotificationPurpose::SosUnacknowledged)->toSubscription($subscription)->failed('HTTP 500', 2)->create(['next_retry_at' => now()->addMinutes(5)]);
        }
    }

    $this->artisan('notifications:retry')->assertSuccessful();

    $unacknowledged = AlertNotification::query()->forAlert($this->sos)->ofPurpose(NotificationPurpose::SosUnacknowledged)->onChannel(NotificationChannel::Sms)->pluck('recipient')->all();

    expect($unacknowledged)->toBe(['user:'.$first->id]);
});

it('ne renvoie rien deux fois, simule sans écrire et refuse une limite invalide', function (): void {
    $row = failedSms($this->sos, $this->contact);

    $this->artisan('notifications:retry', ['--dry-run' => true])->expectsOutputToContain('[simulation] #'.$row->id.' (sms) à rejouer')->assertSuccessful();
    expect($row->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and(Cache::has(RetryNotificationsCommand::SUCCESS_MARKER))->toBeFalse();

    $this->artisan('notifications:retry')->assertSuccessful();
    $this->artisan('notifications:retry')->assertSuccessful();

    expect($this->sms->calls)->toHaveCount(1);

    $this->artisan('notifications:retry', ['--limit' => 0])->assertExitCode(2);
});

it('traite les SOS d’abord', function (): void {
    $report = Alert::factory()->ofType(AlertType::Report)->routedTo($this->contact)->create();
    $older = AlertNotification::factory()->forAlert($report)->toContact($this->contact, NotificationChannel::Mail)->purpose(NotificationPurpose::AlertRouted)->failed()->create(['created_at' => now()->subHour()]);
    $sos = failedSms($this->sos, $this->contact);

    $this->artisan('notifications:retry', ['--limit' => 1])->assertSuccessful();

    expect($sos->fresh()->status)->toBe(DeliveryStatus::Sent)
        ->and($older->fresh()->status)->toBe(DeliveryStatus::Failed);
});

it('pose le marqueur de succès, et pas après une erreur imprévue', function (): void {
    failedSms($this->sos, $this->contact);

    $this->artisan('notifications:retry')->assertSuccessful();
    expect(Cache::get(RetryNotificationsCommand::SUCCESS_MARKER))->toBe(now()->toIso8601String());

    Cache::forget(RetryNotificationsCommand::SUCCESS_MARKER);
    AlertNotification::updating(static fn () => throw new RuntimeException('base indisponible'));
    failedSms(Alert::factory()->sos()->routedTo($this->contact)->create(), $this->contact);

    $this->artisan('notifications:retry')->expectsOutputToContain('1 erreur')->assertFailed();
    expect(Cache::has(RetryNotificationsCommand::SUCCESS_MARKER))->toBeFalse();
});

it('refuse le rejeu depuis l’administration sans le droit de réattribuer l’alerte', function (): void {
    $member = User::factory()->withProfile()->create();
    $row = AlertNotification::factory()->forAlert($this->sos)->toContact($this->contact)->failed()->create(['next_retry_at' => now()->addHour()]);

    expect(fn () => app(RetryAlertNotifications::class)->retryOne($row, $member))->toThrow(AuthorizationException::class);

    expect($row->fresh()->status)->toBe(DeliveryStatus::Failed)
        ->and($row->fresh()->attempts)->toBe(1)
        ->and($this->sms->calls)->toBe([])
        ->and(AuditLog::query()->ofAction('alerts.notification_retried')->exists())->toBeFalse();
});

it('traduit les raisons d’abandon à l’affichage et garde tel quel un message de fournisseur', function (): void {
    expect(AlertNotification::reasonLabel(AlertNotification::SKIP_NO_PHONE))->toBe('Aucun numéro')
        ->and(AlertNotification::reasonLabel(AlertNotification::ERROR_NO_RESPONSE))->toBe('Aucune réponse du fournisseur')
        ->and(AlertNotification::reasonLabel('HTTP 500'))->toBe('HTTP 500')
        ->and(AlertNotification::reasonLabel(null))->toBeNull();
});

it('rejoue une ligne depuis l’administration et le journalise avec l’acteur', function (): void {
    $admin = User::factory()->withProfile()->create();
    $admin->assignRole('admin');
    $admin->givePermissionTo('alerts.manage');
    $row = AlertNotification::factory()->forAlert($this->sos)->toContact($this->contact)->failed()->create(['next_retry_at' => now()->addHour()]);

    $fresh = app(RetryAlertNotifications::class)->retryOne($row, $admin);

    expect($fresh->status)->toBe(DeliveryStatus::Sent)
        ->and(AuditLog::query()->ofAction('alerts.notification_retried')->value('detail'))->toBe('canal=sms ; resultat=sent');
});
