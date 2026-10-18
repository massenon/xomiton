<?php

declare(strict_types=1);

use App\Application\Alerts\MarkSafe;
use App\Application\Alerts\Support\SosOwnerToken;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\NotAnSos;
use App\Enums\AlertStatus;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\DirectoryContact;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

/*
 * P5-02 : MarkSafe — « Je suis en sécurité » : `safe_at`, statut traité (jamais clos), historique, idempotent,
 * refusé sur un SOS annulé ; l'escalade s'arrête.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::listen(AlertEscalated::class, static fn (): null => null);
    Event::listen(AlertReminderDue::class, static fn (): null => null);
    Event::fake([SosMarkedSafe::class, AlertEscalated::class]);
});

it('indique que la personne est en sécurité, une seule fois', function (): void {
    $alert = Alert::factory()->sos()->withIdempotencyKey()->create();

    expect(fn () => app(MarkSafe::class)($alert, null))->toThrow(AuthorizationException::class);

    $fresh = app(MarkSafe::class)($alert, null, SosOwnerToken::for($alert));
    app(MarkSafe::class)($fresh, null, SosOwnerToken::for($alert));

    expect($fresh->safe_at)->not->toBeNull()
        ->and($fresh->status)->toBe(AlertStatus::Handled)
        ->and($fresh->closed_at)->toBeNull()
        ->and(AlertStatusChange::query()->where('alert_id', $alert->id)->count())->toBe(1);

    Event::assertDispatchedTimes(SosMarkedSafe::class, 1);
});

it('refuse un SOS annulé et une alerte qui n’est pas un SOS', function (): void {
    $cancelled = Alert::factory()->sos()->cancelled()->withIdempotencyKey()->create();

    expect(fn () => app(MarkSafe::class)($cancelled, null, SosOwnerToken::for($cancelled)))->toThrow(AlertClosed::class)
        ->and(fn () => app(MarkSafe::class)(Alert::factory()->create(), null))->toThrow(NotAnSos::class);
});

it('arrête l’escalade RG-05', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $alert = Alert::factory()->sos()->withIdempotencyKey()->create();
    app(MarkSafe::class)($alert, null, SosOwnerToken::for($alert));

    $this->travel(6)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect($alert->fresh()->escalated_at)->toBeNull();
    Event::assertNotDispatched(AlertEscalated::class);
});

it('n’inscrit pas dans l’historique le compte qui met en sécurité un SOS anonyme', function (): void {
    $alert = Alert::factory()->sos()->withIdempotencyKey()->create();

    app(MarkSafe::class)($alert, User::factory()->create(), SosOwnerToken::for($alert));

    expect(AlertStatusChange::query()->where('alert_id', $alert->id)->sole()->user_id)->toBeNull();
});
