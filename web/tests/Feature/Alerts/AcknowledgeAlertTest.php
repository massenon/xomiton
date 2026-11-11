<?php

declare(strict_types=1);

use App\Application\Alerts\AcknowledgeAlert;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Exceptions\AcknowledgeLinkInvalid;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Tests\Support\AlertFixtures;

/*
 * P5-02 : AcknowledgeAlert — par compte (AlertPolicy::take) ou par fiche d'annuaire (lien signé) ; nouveau →
 * pris_en_charge, statut avancé conservé, idempotent, alerte close sans accusé.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([AlertAcknowledged::class, AlertStatusChanged::class]);
});

it('accuse réception par un compte admin : horodatage, auteur·rice, statut et historique', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->sos()->create();

    $result = app(AcknowledgeAlert::class)->byUser($alert, $admin);
    $fresh = $alert->fresh();

    expect($result->acknowledgedNow())->toBeTrue()
        ->and($fresh->acknowledged_at)->not->toBeNull()
        ->and($fresh->acknowledged_by)->toBe($admin->id)
        ->and($fresh->status)->toBe(AlertStatus::Acknowledged)
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->where('to_status', 'pris_en_charge')->value('user_id'))->toBe($admin->id)
        ->and(AuditLog::query()->ofAction('alerts.acknowledged')->forSubject($alert)->value('user_id'))->toBe($admin->id);

    Event::assertDispatched(AlertAcknowledged::class, fn (AlertAcknowledged $e): bool => $e->alertId === $alert->id && $e->byUserId === $admin->id && $e->via === 'account');
    Event::assertDispatched(AlertStatusChanged::class, fn (AlertStatusChanged $e): bool => $e->from === 'nouveau' && $e->to === 'pris_en_charge');
});

it('refuse un·e pair·e vers qui l’alerte n’est pas routée et accepte celle ou celui dont la fiche est routée', function (): void {
    $peer = AlertFixtures::member('pair');
    $other = Alert::factory()->create();
    $mine = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($peer)->create())->create();

    expect(fn () => app(AcknowledgeAlert::class)->byUser($other, $peer))->toThrow(AuthorizationException::class)
        ->and(app(AcknowledgeAlert::class)->byUser($mine, $peer)->acknowledgedNow())->toBeTrue();
});

it('n’écrit rien pour une alerte déjà accusée', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->acknowledged()->create(['acknowledged_at' => now()->subHour()]);
    $before = $alert->fresh()->acknowledged_at;

    $result = app(AcknowledgeAlert::class)->byUser($alert, $admin);

    expect($result->alreadyAcknowledged)->toBeTrue()
        ->and($alert->fresh()->acknowledged_at->equalTo($before))->toBeTrue()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->count())->toBe(0);

    Event::assertNotDispatched(AlertAcknowledged::class);
});

it('garde un statut plus avancé et ne prend pas en charge une alerte close ou annulée', function (): void {
    $admin = AlertFixtures::admin();
    $inProgress = Alert::factory()->withStatus(AlertStatus::InProgress)->create();
    $cancelled = Alert::factory()->sos()->cancelled()->create();

    $result = app(AcknowledgeAlert::class)->byUser($inProgress, $admin);

    expect($result->alert->status)->toBe(AlertStatus::InProgress)
        ->and($result->alert->acknowledged_at)->not->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $inProgress->id)->count())->toBe(0)
        ->and(app(AcknowledgeAlert::class)->byUser($cancelled, $admin)->closed)->toBeTrue()
        ->and($cancelled->fresh()->acknowledged_at)->toBeNull();

    Event::assertNotDispatched(AlertStatusChanged::class);
});

it('accuse réception par la fiche routée ou cible d’escalade, jamais par une autre fiche', function (): void {
    $routed = DirectoryContact::factory()->create();
    $national = DirectoryContact::factory()->focalNational()->create();
    $stranger = DirectoryContact::factory()->create();
    $alert = Alert::factory()->sos()->routedTo($routed)->create();
    AlertEscalation::factory()->create(['alert_id' => $alert->id, 'from_contact_id' => $routed->id, 'to_contact_id' => $national->id, 'reason' => 'no_ack_5min']);

    expect(fn () => app(AcknowledgeAlert::class)->bySignedLink($alert, $stranger))->toThrow(AcknowledgeLinkInvalid::class);

    $result = app(AcknowledgeAlert::class)->bySignedLink($alert, $national);
    $log = AuditLog::query()->ofAction('alerts.acknowledged')->forSubject($alert)->sole();

    expect($result->acknowledgedNow())->toBeTrue()
        ->and($result->alert->acknowledged_by)->toBeNull()
        ->and($log->ip)->toBeNull()
        ->and($log->detail)->toContain('via=signed_link')->toContain('fiche=#'.$national->id);

    Event::assertDispatched(AlertAcknowledged::class, fn (AlertAcknowledged $e): bool => $e->contactId === $national->id && $e->viaSignedLink());
});
