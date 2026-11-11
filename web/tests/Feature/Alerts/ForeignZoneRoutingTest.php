<?php

declare(strict_types=1);

use App\Application\Alerts\RouteAlert;
use App\Application\Alerts\TriggerSos;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Models\AlertEscalation;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Tests\Support\AlertFixtures;

/*
 * Zones hors Bénin (décision client du 11/11/2026) : le routage du SOS par zone puis national (RG-04) et l'escalade ne
 * dépendent pas du pays de la zone. Une zone camerounaise est routée vers son point focal, sinon vers le point focal national ;
 * le point focal d'une zone béninoise ne reçoit jamais l'alerte d'une zone camerounaise.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
});

it('route le SOS d’une zone hors Bénin vers le point focal de cette zone', function (): void {
    $douala = Zone::factory()->inCountry('CM')->create(['name' => 'Littoral camerounais', 'slug' => 'littoral-cm']);
    $focalCm = DirectoryContact::factory()->focalZone($douala)->create();
    DirectoryContact::factory()->focalZone(Zone::query()->where('slug', 'littoral')->firstOrFail())->create();
    DirectoryContact::factory()->focalNational()->create();

    $decision = app(RouteAlert::class)($douala->id, []);
    expect($decision->contactId)->toBe($focalCm->id);

    $alert = app(TriggerSos::class)(AlertFixtures::sos(['zoneId' => $douala->id]))->alert;
    expect($alert->zone_id)->toBe($douala->id)->and($alert->routed_to)->toBe($focalCm->id);
});

it('replie sur le point focal national quand la zone hors Bénin n’a aucun point focal, jamais sur celui d’une zone béninoise', function (): void {
    $douala = Zone::factory()->inCountry('CM')->create();
    $beninFocal = DirectoryContact::factory()->focalZone(Zone::query()->where('slug', 'littoral')->firstOrFail())->create();
    $national = DirectoryContact::factory()->focalNational()->create();

    $decision = app(RouteAlert::class)($douala->id, []);

    expect($decision->contactId)->toBe($national->id)->and($decision->contactId)->not->toBe($beninFocal->id)->and($decision->isFallback())->toBeTrue();
});

it('escalade le SOS d’une zone hors Bénin sans accusé à 5 min vers le point focal national', function (): void {
    Notification::fake();
    Event::listen(AlertEscalated::class, static fn (): null => null);
    Event::listen(AlertReminderDue::class, static fn (): null => null);
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);
    $this->freezeSecond();

    $douala = Zone::factory()->inCountry('CM')->create();
    $focalCm = DirectoryContact::factory()->focalZone($douala)->create();
    $national = DirectoryContact::factory()->focalNational()->create();
    $member = User::factory()->create(['zone_id' => $douala->id]);

    $alert = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $member->id, 'zoneId' => $member->zone_id]))->alert;
    expect($alert->routed_to)->toBe($focalCm->id);

    $this->travel(6)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    $escalation = AlertEscalation::query()->where('alert_id', $alert->id)->sole();
    expect($escalation->from_contact_id)->toBe($focalCm->id)->and($escalation->to_contact_id)->toBe($national->id)
        ->and($alert->fresh()->escalated_at)->not->toBeNull();
});
