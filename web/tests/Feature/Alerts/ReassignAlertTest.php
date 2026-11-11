<?php

declare(strict_types=1);

use App\Application\Alerts\ReassignAlert;
use App\Domain\Alerts\Events\AlertReassigned;
use App\Domain\Alerts\Exceptions\AlertClosed;
use App\Domain\Alerts\Exceptions\ContactUnavailable;
use App\Enums\RoutingFallback;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Tests\Support\AlertFixtures;

/*
 * P5-02 : ReassignAlert — contact actif, `manual`, trace `alert_escalations` (manual), accusé et escalade conservés,
 * autorisation `reassign` (alerts.manage), événement et journal.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Event::fake([AlertReassigned::class]);
});

it('réattribue à un autre contact en conservant l’accusé', function (): void {
    $admin = AlertFixtures::admin();
    $from = DirectoryContact::factory()->create();
    $to = DirectoryContact::factory()->create();
    $alert = Alert::factory()->routedTo($from)->acknowledged($admin)->create();
    $acknowledgedAt = $alert->fresh()->acknowledged_at;

    $fresh = app(ReassignAlert::class)($alert, $to, $admin);

    expect($fresh->routed_to)->toBe($to->id)
        ->and($fresh->routing_fallback)->toBe(RoutingFallback::Manual)
        ->and($fresh->acknowledged_at->equalTo($acknowledgedAt))->toBeTrue()
        ->and(AlertEscalation::query()->where('alert_id', $alert->id)->sole()->only(['from_contact_id', 'to_contact_id', 'reason']))
        ->toBe(['from_contact_id' => $from->id, 'to_contact_id' => $to->id, 'reason' => 'manual'])
        ->and(AuditLog::query()->ofAction('alerts.reassigned')->forSubject($alert)->value('detail'))->toBe("de=#{$from->id} ; vers=#{$to->id}");

    Event::assertDispatched(AlertReassigned::class, fn (AlertReassigned $e): bool => $e->fromContactId === $from->id && $e->toContactId === $to->id);
});

it('refuse un contact inactif ou supprimé, une alerte close et un compte sans alerts.manage', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->create();

    expect(fn () => app(ReassignAlert::class)($alert, DirectoryContact::factory()->inactive()->create(), $admin))->toThrow(ContactUnavailable::class)
        ->and(fn () => app(ReassignAlert::class)($alert, tap(DirectoryContact::factory()->create(), fn (DirectoryContact $c) => $c->delete()), $admin))->toThrow(ContactUnavailable::class)
        ->and(fn () => app(ReassignAlert::class)(Alert::factory()->sos()->cancelled()->create(), DirectoryContact::factory()->create(), $admin))->toThrow(AlertClosed::class)
        ->and(fn () => app(ReassignAlert::class)($alert, DirectoryContact::factory()->create(), AlertFixtures::member('pair')))->toThrow(AuthorizationException::class);

    expect(AlertEscalation::query()->count())->toBe(0);
});
