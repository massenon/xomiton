<?php

declare(strict_types=1);

use App\Application\Alerts\ChangeAlertStatus;
use App\Domain\Alerts\Events\AlertAcknowledged;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Exceptions\InvalidStatusTransition;
use App\Enums\AlertStatus;
use App\Http\Requests\Alerts\ChangeAlertStatusRequest;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Tests\Support\AlertFixtures;

/*
 * P5-02 : ChangeAlertStatus — transitions fermées, historique, `closed_at`, prise en charge = accusé, autorisation
 * `take` / `close`, événement et journal.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([AlertStatusChanged::class, AlertAcknowledged::class]);
});

it('passe de nouveau à en cours avec historique, accusé et journal', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->create();

    $fresh = app(ChangeAlertStatus::class)($alert, AlertStatus::InProgress, $admin);

    expect($fresh->status)->toBe(AlertStatus::InProgress)
        ->and($fresh->acknowledged_at)->not->toBeNull()
        ->and($fresh->acknowledged_by)->toBe($admin->id)
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->sole()->only(['from_status', 'to_status', 'user_id']))
        ->toEqual(['from_status' => AlertStatus::New, 'to_status' => AlertStatus::InProgress, 'user_id' => $admin->id])
        ->and(AuditLog::query()->ofAction('alerts.status_changed')->forSubject($alert)->value('detail'))->toBe('de=nouveau ; vers=en_cours');

    Event::assertDispatched(AlertStatusChanged::class, fn (AlertStatusChanged $e): bool => $e->from === 'nouveau' && $e->to === 'en_cours' && $e->byUserId === $admin->id);
    Event::assertDispatched(AlertAcknowledged::class);
});

it('pose l’accusé au passage à pris en charge et closed_at au passage à clos', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->create();

    $taken = app(ChangeAlertStatus::class)($alert, AlertStatus::Acknowledged, $admin);
    $closed = app(ChangeAlertStatus::class)($taken, AlertStatus::Closed, $admin);

    expect($taken->acknowledged_at)->not->toBeNull()
        ->and($closed->closed_at)->not->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->count())->toBe(2);
});

it('refuse une transition hors matrice, sans écrire', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->withStatus(AlertStatus::Closed)->create();

    expect(fn () => app(ChangeAlertStatus::class)($alert, AlertStatus::InProgress, $admin))
        ->toThrow(fn (InvalidStatusTransition $e) => expect($e->status())->toBe(422)->and($e->errorCode())->toBe('invalid_transition'));

    expect($alert->fresh()->status)->toBe(AlertStatus::Closed)
        ->and(AlertStatusChange::query()->count())->toBe(0);

    Event::assertNotDispatched(AlertStatusChanged::class);
});

it('exige take pour prendre en charge et close pour clore, sur une alerte visible', function (): void {
    $member = AlertFixtures::member();
    $peer = AlertFixtures::member('pair');
    $alert = Alert::factory()->create();
    $mine = Alert::factory()->routedTo(DirectoryContact::factory()->linkedTo($peer)->create())->create();

    expect(fn () => app(ChangeAlertStatus::class)($alert, AlertStatus::Closed, $member))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeAlertStatus::class)($alert, AlertStatus::Closed, $peer))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeAlertStatus::class)($alert, AlertStatus::InProgress, $peer))->toThrow(AuthorizationException::class)
        ->and(app(ChangeAlertStatus::class)($mine, AlertStatus::Closed, $peer)->status)->toBe(AlertStatus::Closed)
        ->and(ChangeAlertStatus::ability(AlertStatus::Handled))->toBe('close')
        ->and(ChangeAlertStatus::ability(AlertStatus::Acknowledged))->toBe('take');
});

it('autorise la requête HTTP selon le statut visé', function (): void {
    Route::post('/_test/alertes/{alert:ulid}/statut', fn (ChangeAlertStatusRequest $request, Alert $alert) => response()->json(['status' => $request->status()->value]))->middleware('web');
    $alert = Alert::factory()->create();

    $this->actingAs(AlertFixtures::member())->postJson("/_test/alertes/{$alert->ulid}/statut", ['status' => 'clos'])->assertForbidden();
    $this->actingAs(AlertFixtures::admin())->postJson("/_test/alertes/{$alert->ulid}/statut", ['status' => 'clos'])->assertOk()->assertJson(['status' => 'clos']);
    $this->postJson("/_test/alertes/{$alert->ulid}/statut", ['status' => 'inconnu'])->assertJsonValidationErrors('status');
});
