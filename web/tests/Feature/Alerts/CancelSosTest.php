<?php

declare(strict_types=1);

use App\Application\Alerts\CancelSos;
use App\Application\Alerts\Support\SosOwnerToken;
use App\Application\Alerts\TriggerSos;
use App\Domain\Alerts\Events\AlertStatusChanged;
use App\Domain\Alerts\Events\SosCancelled;
use App\Domain\Alerts\Exceptions\CancelWindowExpired;
use App\Domain\Alerts\Exceptions\NotAnSos;
use App\Enums\AlertStatus;
use App\Http\Requests\Alerts\CancelSosRequest;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\AuditLog;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\Support\AlertFixtures;

/*
 * P5-02 / Q39 : CancelSos — repentir pendant 60 s après l'envoi, statut clos, historique, « fausse alerte »,
 * idempotent ; propriétaire seulement (SOS anonyme : jeton X-Sos-Token, Q52 b ; l'ULID seul ne suffit pas).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

it('annule un SOS dans la fenêtre de 60 s', function (): void {
    Event::fake([SosCancelled::class, AlertStatusChanged::class]);
    $created = app(TriggerSos::class)(AlertFixtures::sos());
    $alert = $created->alert;
    $this->travel(30)->seconds();

    expect($created->sosToken)->toBe(SosOwnerToken::for($alert))->not->toBeNull()
        ->and(fn () => app(CancelSos::class)($alert, null))->toThrow(AuthorizationException::class);

    $fresh = app(CancelSos::class)($alert, null, $created->sosToken);

    expect($fresh->cancelled_at)->not->toBeNull()
        ->and($fresh->status)->toBe(AlertStatus::Closed)
        ->and($fresh->closed_at)->not->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->pluck('to_status')->map->value->all())->toBe(['nouveau', 'clos'])
        ->and(AuditLog::query()->ofAction('alerts.cancelled')->forSubject($alert)->sole()->ip)->toBeNull();

    Event::assertDispatched(SosCancelled::class, fn (SosCancelled $e): bool => $e->alertId === $alert->id && $e->byUserId === null);
    Event::assertDispatched(AlertStatusChanged::class, fn (AlertStatusChanged $e): bool => $e->to === 'clos');
});

it('refuse l’annulation après 60 s, reste idempotent et ne concerne que les SOS', function (): void {
    $this->freezeSecond();
    $late = Alert::factory()->sos()->withIdempotencyKey()->create(['created_at' => now()->subSeconds(61)]);
    $edge = Alert::factory()->sos()->withIdempotencyKey()->create(['created_at' => now()->subSeconds(60)]);
    $report = Alert::factory()->create();

    expect(fn () => app(CancelSos::class)($late, null, SosOwnerToken::for($late)))->toThrow(CancelWindowExpired::class)
        ->and(app(CancelSos::class)($edge, null, SosOwnerToken::for($edge))->isCancelled())->toBeTrue()
        ->and(app(CancelSos::class)($edge->fresh(), null, SosOwnerToken::for($edge))->isCancelled())->toBeTrue()
        ->and(AlertStatusChange::query()->where('alert_id', $edge->id)->count())->toBe(1)
        ->and(fn () => app(CancelSos::class)($report, null))->toThrow(NotAnSos::class)
        ->and($late->fresh()->isCancelled())->toBeFalse();
});

it('réserve l’annulation d’un SOS identifié à son ou sa propriétaire', function (): void {
    $owner = AlertFixtures::member();
    $alert = Alert::factory()->sos()->fromMember($owner)->create();

    expect(fn () => app(CancelSos::class)($alert, AlertFixtures::member()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelSos::class)($alert, null))->toThrow(AuthorizationException::class)
        ->and(app(CancelSos::class)($alert, $owner)->isCancelled())->toBeTrue()
        ->and(AuditLog::query()->ofAction('alerts.cancelled')->forSubject($alert)->value('user_id'))->toBe($owner->id);
});

it('autorise la requête du ou de la propriétaire, ou du jeton X-Sos-Token d’un SOS anonyme (l’ULID seul ne suffit pas)', function (): void {
    Route::post('/_test/sos/{alert:ulid}/cancel', fn (CancelSosRequest $request, Alert $alert) => response()->json(['id' => $request->alert()->ulid]))->middleware('web');
    $owner = AlertFixtures::member();
    $identified = Alert::factory()->sos()->fromMember($owner)->withIdempotencyKey()->create();
    $anonymous = Alert::factory()->sos()->withIdempotencyKey()->create();
    $imported = Alert::factory()->sos()->create();

    expect(SosOwnerToken::for($identified))->toBeNull()
        ->and(SosOwnerToken::for($imported))->toBeNull();

    $this->postJson("/_test/sos/{$anonymous->ulid}/cancel")->assertForbidden();
    $this->withHeader('X-Sos-Token', 'deviné')->postJson("/_test/sos/{$anonymous->ulid}/cancel")->assertForbidden();
    $this->withHeader('X-Sos-Token', (string) SosOwnerToken::for($anonymous))->postJson("/_test/sos/{$imported->ulid}/cancel")->assertForbidden();
    $this->withHeader('X-Sos-Token', (string) SosOwnerToken::for($anonymous))->postJson("/_test/sos/{$anonymous->ulid}/cancel")->assertOk();
    $this->flushHeaders();
    $this->postJson("/_test/sos/{$identified->ulid}/cancel")->assertForbidden();
    $this->actingAs(AlertFixtures::member())->postJson("/_test/sos/{$identified->ulid}/cancel")->assertForbidden();
    $this->actingAs($owner)->postJson("/_test/sos/{$identified->ulid}/cancel")->assertOk()->assertJson(['id' => $identified->ulid]);
});

it('n’inscrit pas dans l’historique le compte qui annule un SOS anonyme', function (): void {
    $alert = Alert::factory()->sos()->withIdempotencyKey()->create();

    app(CancelSos::class)($alert, AlertFixtures::member(), SosOwnerToken::for($alert));

    expect(AlertStatusChange::query()->where('alert_id', $alert->id)->where('to_status', 'clos')->sole()->user_id)->toBeNull();
});
