<?php

declare(strict_types=1);

use App\Application\Alerts\AcknowledgeAlert;
use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Events\SosCancelled;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Domain\Alerts\Events\SosTriggered;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\OnCallShift;
use App\Models\User;
use App\Notifications\AlertStatusChanged as AlertStatusChangedNotification;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Support\SosScenario;

/*
 * P5-03 : suites d'un SOS. « Fausse alerte » et « en sécurité » aux personnes déjà prévenues (SMS au contact de
 * confiance) ; l'accusé de réception pose « vu » et prévient l'auteur·e ; changement de statut à l'auteur·e non
 * anonyme ; alerts:escalate réussit une fois les écouteurs branchés (P5-07).
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class]);
    $this->freezeSecond();
});

function sosTriggered(Alert $alert): void
{
    SosTriggered::dispatch((int) $alert->id, $alert->user_id, $alert->zone_id, $alert->commune_id, true, $alert->routed_to, new DateTimeImmutable);
}

it('annonce la fausse alerte aux personnes prévenues et abandonne les envois SOS en attente', function (): void {
    $scene = SosScenario::make(focalWithAccount: true);
    sosTriggered($scene->alert);
    $pending = AlertNotification::factory()->forAlert($scene->alert)->toContact($scene->national)->create();
    $scene->sms->calls = [];

    SosCancelled::dispatch((int) $scene->alert->id, $scene->member->id, new DateTimeImmutable);

    $cancelled = AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosCancelled)->get();

    expect($pending->fresh()->status)->toBe(DeliveryStatus::Skipped)
        ->and($pending->fresh()->last_error)->toBe(AlertNotification::SKIP_CANCELLED)
        ->and($cancelled->pluck('recipient')->all())->toContain('trusted:'.$scene->member->id, 'user:'.$scene->focalUser->id)
        ->and($cancelled->pluck('recipient')->all())->not->toContain('contact:'.$scene->national->id)
        ->and($cancelled->where('recipient', 'user:'.$scene->focalUser->id)->pluck('channel')->map->value->all())->not->toContain('sms')
        ->and(collect($scene->sms->calls)->firstWhere('to', SosScenario::TRUSTED_PHONE)['text'])->toContain('Fausse alerte');
});

it('annonce aussi par SMS la fausse alerte au compte joint par SMS, pas à l’admin que le plafond en a privé', function (): void {
    config(['xomiton.alerts.sos_admin_sms_max' => 1]);
    $scene = SosScenario::make();
    $second = User::factory()->withProfile()->create(['phone' => '+229 01 90 00 00 02']);
    OnCallShift::factory()->admin()->forUser($second)->create();
    sosTriggered($scene->alert);
    $admins = ['user:'.$scene->admin->id, 'user:'.$second->id];
    $reached = AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosTriggered)->onChannel(NotificationChannel::Sms)->whereIn('recipient', $admins)->value('recipient');
    $other = array_values(array_diff($admins, [$reached]))[0];
    $scene->sms->calls = [];

    SosCancelled::dispatch((int) $scene->alert->id, $scene->member->id, new DateTimeImmutable);

    $sms = AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosCancelled)->onChannel(NotificationChannel::Sms)->get()->keyBy('recipient');

    expect($sms->get($reached)?->status)->toBe(DeliveryStatus::Sent)
        ->and($sms->has($other))->toBeFalse()
        ->and(AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosCancelled)->onChannel(NotificationChannel::Database)->where('recipient', $other)->exists())->toBeTrue()
        ->and(collect($scene->sms->calls)->pluck('text')->filter(fn (string $text): bool => str_contains($text, 'Fausse alerte'))->count())->toBe(3);
});

it('annonce « en sécurité » par SMS au compte qui a reçu le SOS par SMS', function (): void {
    $scene = SosScenario::make();
    sosTriggered($scene->alert);
    $scene->sms->calls = [];

    SosMarkedSafe::dispatch((int) $scene->alert->id, $scene->member->id, new DateTimeImmutable);

    expect(AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosSafe)->onChannel(NotificationChannel::Sms)->where('recipient', 'user:'.$scene->admin->id)->value('status'))->toBe(DeliveryStatus::Sent)
        ->and(collect($scene->sms->calls)->firstWhere('to', '+229 01 90 00 00 01')['text'])->toContain('en sécurité');
});

it('annonce « en sécurité » sans arrêter les envois SOS en attente', function (): void {
    $scene = SosScenario::make();
    sosTriggered($scene->alert);
    $pending = AlertNotification::factory()->forAlert($scene->alert)->toContact($scene->national)->create();

    SosMarkedSafe::dispatch((int) $scene->alert->id, $scene->member->id, new DateTimeImmutable);

    expect($pending->fresh()->status)->toBe(DeliveryStatus::Pending)
        ->and(AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosSafe)->where('recipient', 'trusted:'.$scene->member->id)->value('status'))->toBe(DeliveryStatus::Sent);
});

it('pose « vu » sur les lignes de la personne qui accuse et prévient l’auteur·e par son libellé public', function (): void {
    $scene = SosScenario::make();
    sosTriggered($scene->alert);
    $scene->alert->update(['acknowledged_at' => now(), 'acknowledged_by_contact_id' => $scene->focal->id]);

    AlertAcknowledged::dispatch((int) $scene->alert->id, 'sos', null, $scene->focal->id, AlertAcknowledged::VIA_SIGNED_LINK, new DateTimeImmutable);

    $focalRow = AlertNotification::query()->forAlert($scene->alert)->where('recipient', 'contact:'.$scene->focal->id)->first();
    $toAuthor = AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosAcknowledged)->where('user_id', $scene->member->id)->get();

    expect($focalRow->seen_at)->not->toBeNull()
        ->and(AlertNotification::query()->forAlert($scene->alert)->where('recipient', 'user:'.$scene->admin->id)->value('seen_at'))->toBeNull()
        ->and($toAuthor->pluck('channel')->map->value->sort()->values()->all())->toBe(['database', 'push', 'push'])
        ->and($scene->member->notifications()->first()->data['body'])->toBe('Mireille TOGNIFODE a bien reçu ton alerte et va t’appeler.');

    $scene->focal->update(['is_public' => false]);
    $other = Alert::factory()->sos()->fromMember($scene->member)->routedTo($scene->focal)->create(['zone_id' => $scene->zone->id]);
    AlertAcknowledged::dispatch((int) $other->id, 'sos', null, $scene->focal->id, AlertAcknowledged::VIA_SIGNED_LINK, new DateTimeImmutable);

    expect($scene->member->notifications()->latest('created_at')->get()->pluck('data.body'))->toContain('Point focal a bien reçu ton alerte et va t’appeler.');
});

it('ne prévient qu’une fois l’auteur·e de la prise en charge de son SOS : l’accusé, pas aussi « statut changé »', function (): void {
    $scene = SosScenario::make();
    sosTriggered($scene->alert);
    $scene->push->calls = [];

    app(AcknowledgeAlert::class)->bySignedLink($scene->alert->fresh(), $scene->focal);

    expect($scene->member->notifications()->count())->toBe(1)
        ->and($scene->member->notifications()->first()->data['body'])->toContain('a bien reçu ton alerte')
        ->and($scene->push->calls)->toHaveCount(2);

    Notification::fake();
    AlertStatusChanged::dispatch((int) $scene->alert->id, 'sos', 'nouveau', 'en_cours', $scene->admin->id, $scene->member->id, new DateTimeImmutable);
    AlertStatusChanged::dispatch((int) $scene->alert->id, 'sos', 'pris_en_charge', 'en_cours', $scene->admin->id, $scene->member->id, new DateTimeImmutable);

    Notification::assertSentTimes(AlertStatusChangedNotification::class, 1);
});

it('prévient l’auteur·e non anonyme d’un changement de statut, jamais une alerte anonyme', function (): void {
    Notification::fake();
    $author = User::factory()->withProfile()->create();
    $report = Alert::factory()->ofType(AlertType::Report)->fromMember($author)->create(['status' => AlertStatus::InProgress]);
    $anonymous = Alert::factory()->ofType(AlertType::Report)->create(['status' => AlertStatus::InProgress]);

    AlertStatusChanged::dispatch((int) $report->id, 'denonciation', 'nouveau', 'en_cours', null, $author->id, new DateTimeImmutable);
    AlertStatusChanged::dispatch((int) $anonymous->id, 'denonciation', 'nouveau', 'en_cours', null, null, new DateTimeImmutable);

    Notification::assertSentTo($author, AlertStatusChangedNotification::class, fn (AlertStatusChangedNotification $n): bool => $n->statusLabel === AlertStatus::InProgress->label());
    Notification::assertSentTimes(AlertStatusChangedNotification::class, 1);
});

it('laisse alerts:escalate réussir et prévenir le point focal national une fois les écouteurs branchés (P5-07)', function (): void {
    Mail::fake();
    $scene = SosScenario::make();
    $this->travel(6)->minutes();

    $this->artisan('alerts:escalate')->assertSuccessful();

    expect(AlertNotification::query()->forAlert($scene->alert)->ofPurpose(NotificationPurpose::SosUnacknowledged)->where('recipient', 'contact:'.$scene->national->id)->pluck('channel')->map->value->sort()->values()->all())->toBe(['mail', 'sms'])
        ->and(Cache::has(EscalateAlertsCommand::SUCCESS_MARKER))->toBeTrue()
        ->and(AlertNotification::query()->forAlert($scene->alert)->onChannel(NotificationChannel::Sms)->where('recipient', 'contact:'.$scene->national->id)->value('status'))->toBe(DeliveryStatus::Sent);
});
