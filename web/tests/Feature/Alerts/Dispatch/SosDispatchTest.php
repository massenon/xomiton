<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Alerts\Dispatch\Recipients\SosRecipients;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertCreated;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Events\SosCancelled;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Domain\Alerts\Events\SosTriggered;
use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\SmsText;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\AuditLog;
use App\Models\DatabaseNotification;
use App\Models\DirectoryContact;
use App\Models\PushSubscription;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\Support\SosScenario;

/*
 * P5-03 : un SOS notifie dans la requête le contact de confiance (SMS), le point focal (routé, de zone) et l'admin de
 * garde (cloche, push, SMS de secours) ; chaque remise est consignée dans alert_notifications, sans numéro ; un
 * fournisseur lent ou en échec ne bloque pas la requête et laisse un rejeu planifié.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->freezeSecond();
});

function triggerSos(Alert $alert): void
{
    SosTriggered::dispatch((int) $alert->id, $alert->user_id, $alert->zone_id, $alert->commune_id, true, $alert->routed_to, new DateTimeImmutable);
}

/**
 * @return array<string, AlertNotification>
 */
function rowsOf(Alert $alert): array
{
    return AlertNotification::query()->forAlert($alert)->get()
        ->keyBy(static fn (AlertNotification $row): string => $row->channel->value.'|'.$row->recipient)
        ->all();
}

it('planifie et remet chaque canal attendu, consigné sans numéro', function (): void {
    Mail::fake();
    $scene = SosScenario::make(focalWithAccount: true);

    triggerSos($scene->alert);

    $rows = rowsOf($scene->alert);
    $focalRef = 'user:'.$scene->focalUser->id;
    $adminRef = 'user:'.$scene->admin->id;
    $subscription = $scene->focalUser->pushSubscriptions()->value('id');

    expect(array_keys($rows))->toEqualCanonicalizing([
        'sms|trusted:'.$scene->member->id,
        'database|'.$focalRef,
        'push|subscription:'.$subscription,
        'database|'.$adminRef,
        'sms|'.$adminRef,
    ])->and(collect($rows)->every(fn (AlertNotification $row): bool => $row->status === DeliveryStatus::Sent && $row->attempts === 1))->toBeTrue()
        ->and($rows['database|'.$focalRef]->provider)->toBe('database')
        ->and($rows['push|subscription:'.$subscription]->provider)->toBe('log')
        ->and($rows['push|subscription:'.$subscription]->user_id)->toBe($scene->focalUser->id)
        ->and($rows['sms|trusted:'.$scene->member->id]->provider)->toBe('log')
        ->and(AlertNotification::query()->where('recipient', 'like', '%+229%')->exists())->toBeFalse();

    // Contact de confiance : texte « appelez-la », sans lien d'administration ; l'admin sans abonnement reçoit le SMS de secours.
    $texts = collect($scene->sms->calls)->keyBy('to');
    expect($texts[SosScenario::TRUSTED_PHONE]['text'])->toContain($scene->member->pseudo)->not->toContain('/admin')
        ->and($texts['+229 01 90 00 00 01']['text'])->toContain($scene->alert->displayCode())
        ->and(count($scene->push->calls))->toBe(1);

    // Cloche : catégorie SOS (verrouillée), sensible ; la personne en danger n'est jamais notifiée.
    expect(DB::table('notifications')->where('notifiable_id', $scene->focalUser->id)->value('category'))->toBe('sos')
        ->and((bool) DB::table('notifications')->where('notifiable_id', $scene->focalUser->id)->value('is_sensitive'))->toBeTrue()
        ->and(DB::table('notifications')->where('notifiable_id', $scene->member->id)->exists())->toBeFalse()
        ->and(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Mail)->exists())->toBeFalse()
        ->and(AuditLog::query()->ofAction('alerts.notified')->forSubject($scene->alert)->value('detail'))->toContain('purpose=sos_triggered')
        ->and(AuditLog::query()->ofAction('alerts.notified')->forSubject($scene->alert)->value('ip'))->toBeNull();

    Mail::assertNothingSent();
    Mail::assertNothingQueued();
});

it('envoie un SMS direct à une fiche sans compte, avec le lien signé d’accusé', function (): void {
    $scene = SosScenario::make();

    triggerSos($scene->alert);

    $row = AlertNotification::query()->forAlert($scene->alert)->where('recipient', 'contact:'.$scene->focal->id)->first();
    $text = collect($scene->sms->calls)->firstWhere('to', SosScenario::FOCAL_PHONE)['text'];

    expect($row->channel)->toBe(NotificationChannel::Sms)
        ->and($row->status)->toBe(DeliveryStatus::Sent)
        ->and($text)->toContain('/alertes/'.$scene->alert->ulid.'/accuser')
        ->and($text)->toContain('signature=');
});

it('envoie au point focal de zone prévenu en copie un SMS court sans « Accusez réception » (Q57)', function (): void {
    $scene = SosScenario::make();
    $scene->national->update(['phone' => '+229 01 21 00 00 00']);
    $scene->alert->update(['routed_to' => $scene->national->id]);

    triggerSos($scene->alert->fresh());

    $texts = collect($scene->sms->calls)->keyBy('to');
    $copy = $texts[SosScenario::FOCAL_PHONE]['text'];

    expect($copy)->toContain($scene->alert->displayCode(), 'restez joignable')
        ->not->toContain('Accusez', 'http')
        ->and(SmsText::isGsm7($copy))->toBeTrue()
        ->and(SmsText::segments($copy))->toBe(1)
        ->and($texts['+229 01 21 00 00 00']['text'])->toContain('Accusez réception', '/accuser');
});

it('écrit toutes les lignes en attente avant la première remise', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    $seen = null;
    $scene->sms->beforeSend(function () use (&$seen, $scene): void {
        $seen ??= AlertNotification::query()->forAlert($scene->alert)->where('status', DeliveryStatus::Pending)->count();
    });

    triggerSos($scene->alert);

    expect($seen)->toBeGreaterThanOrEqual(3);
});

it('ne prévient qu’une fois une personne routée et focal de zone, ou admin et fiche liée', function (): void {
    $scene = SosScenario::make();
    $scene->focal->update(['user_id' => $scene->admin->id]);
    $alert = $scene->alert->fresh(['zone', 'routedContact.user', 'user.profile']);

    $targets = app(SosRecipients::class)->for($alert);
    $references = array_map(static fn ($t): string => $t->reference, $targets);

    expect($references)->toBe(['trusted:'.$scene->member->id, 'user:'.$scene->admin->id])
        ->and($targets[1]->contact?->id)->toBe($scene->focal->id);
});

it('n’a ni contact de confiance pour un SOS anonyme, ni l’auteur·e parmi les admins', function (): void {
    $scene = SosScenario::make(anonymous: true);
    $alert = $scene->alert->fresh(['zone', 'routedContact.user', 'user.profile']);

    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($alert));
    expect($references)->not->toContain('trusted:'.$scene->member->id);

    $own = Alert::factory()->sos()->fromMember($scene->admin)->routedTo($scene->focal)->create(['zone_id' => $scene->zone->id]);
    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($own->fresh(['zone', 'routedContact.user', 'user.profile'])));
    expect($references)->not->toContain('user:'.$scene->admin->id);
});

it('prévient le point focal national quand ni contact routé ni focal de zone n’existent, sinon les admins seuls', function (): void {
    $scene = SosScenario::make();
    $alert = Alert::factory()->sos()->create(['zone_id' => null, 'routed_to' => null]);

    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($alert->fresh()));
    expect($references)->toBe(['contact:'.$scene->national->id, 'user:'.$scene->admin->id]);

    Log::spy();
    DirectoryContact::query()->update(['is_active' => false]);
    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($alert->fresh()));
    expect($references)->toBe(['user:'.$scene->admin->id]);
    Log::shouldHaveReceived('warning')->with('alerts.unrouted', Mockery::any());
});

it('ne prévient d’abord que l’admin de garde (D-MOB-10) ; sans garde, repli sur les admins habilités', function (): void {
    $scene = SosScenario::make();
    $night = User::factory()->withProfile()->create();
    $this->travelTo(now()->setTime(3, 0));
    App\Models\OnCallShift::query()->delete();
    App\Models\OnCallShift::factory()->admin()->night()->forUser($night)->create();

    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($scene->alert->fresh(['zone', 'routedContact.user', 'user.profile'])));
    expect($references)->toContain('user:'.$night->id)->not->toContain('user:'.$scene->admin->id);

    App\Models\OnCallShift::query()->delete();
    $references = array_map(static fn ($t): string => $t->reference, app(SosRecipients::class)->for($scene->alert->fresh(['zone', 'routedContact.user', 'user.profile'])));
    expect($references)->toContain('user:'.$scene->admin->id);
});

it('consigne un push en échec, planifie son rejeu et envoie le SMS de repli dans la même requête', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    $scene->push->willReturn(DeliveryResult::failed('HTTP 500'));

    triggerSos($scene->alert);

    $push = AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Push)->first();
    $sms = AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Sms)->where('recipient', 'user:'.$scene->focalUser->id)->first();

    expect($push->status)->toBe(DeliveryStatus::Failed)
        ->and($push->attempts)->toBe(1)
        ->and($push->last_error)->toBe('HTTP 500')
        ->and($push->provider)->toBe('log')
        ->and($push->next_retry_at->equalTo(now()->addMinute()))->toBeTrue()
        ->and($sms?->status)->toBe(DeliveryStatus::Sent)
        ->and(collect($scene->sms->calls)->pluck('to'))->toContain($scene->focalUser->phone);
});

it('abandonne la ligne d’un abonnement disparu, supprime l’abonnement et replie sur le SMS', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    $scene->push->willReturn(DeliveryResult::gone('HTTP 410'));
    $subscription = $scene->focalUser->pushSubscriptions()->first();

    triggerSos($scene->alert);

    $push = AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Push)->first();

    expect($push->status)->toBe(DeliveryStatus::Skipped)
        ->and($push->last_error)->toBe(AlertNotification::SKIP_GONE)
        ->and(PushSubscription::query()->find($subscription->id))->toBeNull()
        ->and($push->fresh()->push_subscription_id)->toBeNull()
        ->and(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Sms)->where('recipient', 'user:'.$scene->focalUser->id)->value('status'))->toBe(DeliveryStatus::Sent);
});

it('laisse en attente les remises d’un fournisseur lent au-delà de son délai (requête bornée)', function (): void {
    config(['xomiton.alerts.provider_timeout_seconds' => 0.25, 'xomiton.alerts.dispatch_budget_seconds' => 1.0]);
    $scene = SosScenario::make(focalWithAccount: true);
    PushSubscription::factory()->count(2)->forUser($scene->focalUser)->create();
    $scene->push->sleepSeconds(0.3);

    $started = microtime(true);
    triggerSos($scene->alert);
    $elapsed = microtime(true) - $started;

    $push = AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Push)->get();

    expect($elapsed)->toBeLessThan(1.0)
        ->and($push->where('status', DeliveryStatus::Sent)->count())->toBe(1)
        ->and($push->where('status', DeliveryStatus::Pending)->count())->toBe(2)
        ->and($push->where('status', DeliveryStatus::Pending)->every(fn (AlertNotification $row): bool => $row->attempts === 0 && $row->next_retry_at->lessThanOrEqualTo(now())))->toBeTrue();
});

it('ne commence pas un second fournisseur lent une fois le budget de la requête épuisé', function (): void {
    config(['xomiton.alerts.provider_timeout_seconds' => 0.4, 'xomiton.alerts.dispatch_budget_seconds' => 0.5]);
    $scene = SosScenario::make(focalWithAccount: true);
    $scene->sms->sleepSeconds(0.6);
    Log::spy();

    triggerSos($scene->alert);

    expect(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Push)->value('status'))->toBe(DeliveryStatus::Pending);
    Log::shouldHaveReceived('warning')->with('alerts.dispatch.provider_budget_exceeded', Mockery::on(fn (array $context): bool => $context['reason'] === 'request'));
});

it('tient un fournisseur lent sous 4 s avec les délais réels', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    PushSubscription::factory()->count(3)->forUser($scene->focalUser)->create();
    $scene->push->sleepSeconds(1.1);

    $started = microtime(true);
    triggerSos($scene->alert);

    expect(microtime(true) - $started)->toBeLessThan(4.0)
        ->and(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Push)->where('status', DeliveryStatus::Pending)->count())->toBeGreaterThan(0);
})->group('slow');

it('ne renvoie rien quand le même SOS est notifié deux fois, et refuse un doublon en base', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);

    triggerSos($scene->alert);
    $count = AlertNotification::query()->count();
    $calls = count($scene->sms->calls) + count($scene->push->calls);

    triggerSos($scene->alert);

    expect(AlertNotification::query()->count())->toBe($count)
        ->and(count($scene->sms->calls) + count($scene->push->calls))->toBe($calls);

    $row = AlertNotification::query()->first();
    expect(fn () => AlertNotification::query()->create($row->only(['alert_id', 'purpose', 'channel', 'recipient'])))->toThrow(UniqueConstraintViolationException::class);
});

it('plafonne les SMS de repli des admins de garde par SOS', function (): void {
    config(['xomiton.alerts.sos_admin_sms_max' => 1]);
    $scene = SosScenario::make();
    $second = User::factory()->withProfile()->create(['phone' => '+229 01 90 00 00 02']);
    App\Models\OnCallShift::factory()->admin()->forUser($second)->create();

    triggerSos($scene->alert);

    expect(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Sms)->whereIn('recipient', ['user:'.$scene->admin->id, 'user:'.$second->id])->count())->toBe(1)
        ->and(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Database)->count())->toBe(2);
});

it('compte le plafond des SMS d’admins en personnes sur tous les objets du SOS', function (): void {
    config(['xomiton.alerts.sos_admin_sms_max' => 1]);
    $scene = SosScenario::make();
    $second = User::factory()->withProfile()->create(['phone' => '+229 01 90 00 00 02']);
    triggerSos($scene->alert);

    // Le second admin d'abord : un plafond compté par objet lui donnerait la place restante.
    $targets = [RecipientTarget::account($second, RecipientKind::Team), RecipientTarget::account($scene->admin, RecipientKind::Team)];
    app(AlertDispatcher::class)->dispatch($scene->alert->fresh(), NotificationPurpose::SosUnacknowledged, $targets, DispatchContext::request(), ['minutes' => 5]);

    $sms = AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Sms)->where('recipient', 'like', 'user:%')->get();

    expect($sms->pluck('recipient')->unique()->values()->all())->toBe(['user:'.$scene->admin->id])
        ->and($sms->pluck('purpose')->map->value->sort()->values()->all())->toBe(['sos_triggered', 'sos_unacknowledged']);
});

it('neutralise la cloche et le push d’une personne qui masque le contenu sensible', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    $scene->focalUser->settings->update(['mask_sensitive_notifications' => true]);

    triggerSos($scene->alert);

    $data = json_decode((string) DB::table('notifications')->where('notifiable_id', $scene->focalUser->id)->value('data'), true);

    expect($data['title'])->toBe(__('notifications.discreet.title'))
        ->and($scene->push->calls[0]['message']->title)->toBe(__('notifications.discreet.title'))
        ->and($scene->push->calls[0]['message']->body)->not->toContain($scene->member->pseudo);
});

it('résiste à une exception de l’envoi dans chaque écouteur : l’événement n’échoue pas, l’erreur est journalisée sans message', function (string $listener): void {
    $scene = SosScenario::make();
    $author = User::factory()->withProfile()->create();
    $report = Alert::factory()->ofType(AlertType::Report)->fromMember($author)->routedTo($scene->focal)->create(['zone_id' => $scene->zone->id, 'status' => AlertStatus::InProgress]);

    if (in_array($listener, ['NotifySosCancelled', 'NotifySosSafe', 'MarkAlertNotificationsSeen'], true)) {
        triggerSos($scene->alert);
    }

    AlertNotification::creating(static fn () => throw new RuntimeException('panne +229 01 97 38 88 47'));
    DatabaseNotification::creating(static fn () => throw new RuntimeException('panne +229 01 97 38 88 47'));
    Log::spy();

    $now = new DateTimeImmutable;
    $sos = (int) $scene->alert->id;

    match ($listener) {
        'NotifySosTriggered' => triggerSos($scene->alert),
        'NotifyAlertRouted' => AlertCreated::dispatch((int) $report->id, 'denonciation', $report->priority->value, $report->zone_id, null, $scene->focal->id, 'role_zone', $now),
        'NotifyAlertReassigned' => AlertReassigned::dispatch((int) $report->id, 'denonciation', $scene->focal->id, $scene->national->id, $scene->admin->id, $now),
        'NotifyAlertReminder' => AlertReminderDue::dispatch((int) $report->id, 'denonciation', $scene->focal->id, [$scene->admin->id], 1440, $now),
        'NotifyAlertEscalated' => AlertEscalated::dispatch($sos, 'sos', null, $scene->national->id, [], 'no_ack_5min', 6, $now),
        'NotifySosCancelled' => SosCancelled::dispatch($sos, $scene->member->id, $now),
        'NotifySosSafe' => SosMarkedSafe::dispatch($sos, $scene->member->id, $now),
        'MarkAlertNotificationsSeen' => AlertAcknowledged::dispatch($sos, 'sos', null, $scene->focal->id, AlertAcknowledged::VIA_SIGNED_LINK, $now),
        'NotifyAuthorOfStatusChange' => AlertStatusChanged::dispatch((int) $report->id, 'denonciation', 'en_cours', 'traite', null, $author->id, $now),
    };

    Log::shouldHaveReceived('critical')->with('alerts.dispatch_failed', Mockery::on(fn (array $context): bool => $context['listener'] === $listener
        && $context['exception'] === RuntimeException::class
        && ! isset($context['message'])));
})->with([
    'NotifySosTriggered', 'NotifyAlertRouted', 'NotifyAlertReassigned', 'NotifyAlertReminder', 'NotifyAlertEscalated',
    'NotifySosCancelled', 'NotifySosSafe', 'MarkAlertNotificationsSeen', 'NotifyAuthorOfStatusChange',
]);

it('sort sans erreur quand l’alerte a disparu', function (): void {
    Log::spy();

    SosTriggered::dispatch(999999, null, null, null, false, null, new DateTimeImmutable);

    Log::shouldHaveReceived('warning')->with('alerts.dispatch_missing_alert', Mockery::any());
    expect(AlertNotification::query()->count())->toBe(0);
});

it('journalise l’absence de destinataire', function (): void {
    $alert = Alert::factory()->sos()->create(['zone_id' => null]);
    Log::spy();

    $result = app(AlertDispatcher::class)->dispatch($alert, NotificationPurpose::SosTriggered, [], DispatchContext::request());

    expect($result->recipients)->toBe(0)
        ->and(AuditLog::query()->ofAction('alerts.no_recipient')->forSubject($alert)->exists())->toBeTrue();
    Log::shouldHaveReceived('critical')->with('alerts.no_recipient', Mockery::any());
});
