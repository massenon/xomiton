<?php

declare(strict_types=1);

use App\Application\Alerts\TriggerSos;
use App\Console\Commands\Alerts\EscalateAlertsCommand;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\OnCallShift;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\Support\AlertFixtures;

/*
 * P5-07 : alerts:escalate avec travel() — SOS sans accusé à 5 min vers le point focal national, une seule fois ;
 * rappel à 24 h puis escalade à 48 h des signalements ; repli sur les admins de garde ; --dry-run, --limit,
 * erreurs isolées et marqueur de succès pour la supervision.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
    // Écouteurs P5-03 simulés : sans eux, la commande refuse de consommer les échéances.
    Event::listen(AlertEscalated::class, static fn (): null => null);
    Event::listen(AlertReminderDue::class, static fn (): null => null);
    Event::fake([AlertEscalated::class, AlertReminderDue::class]);
    $this->freezeSecond();
});

it('échoue sans rien écrire ni poser le marqueur tant qu’aucun envoi n’écoute l’escalade (P5-03)', function (): void {
    // EventFake::forget() est sans effet : on retire les écouteurs du répartiteur réel qu'il enveloppe.
    Event::getFacadeRoot()->dispatcher->forget(AlertEscalated::class);
    Event::getFacadeRoot()->dispatcher->forget(AlertReminderDue::class);
    DirectoryContact::factory()->focalNational()->create();
    $alert = escalationSos();

    $this->travel(6)->minutes();
    $this->artisan('alerts:escalate')->assertFailed();

    expect($alert->fresh()->escalated_at)->toBeNull()
        ->and(Cache::has(EscalateAlertsCommand::SUCCESS_MARKER))->toBeFalse();
});

function escalationSos(?DirectoryContact $routed = null): Alert
{
    $alert = app(TriggerSos::class)(AlertFixtures::sos())->alert;

    if ($routed !== null) {
        $alert->update(['routed_to' => $routed->id]);
    }

    return $alert->fresh();
}

it('escalade un SOS sans accusé à 5 min, pas avant, vers le point focal national', function (): void {
    $routed = DirectoryContact::factory()->focalZone()->create();
    $national = DirectoryContact::factory()->focalNational()->create();
    $alert = escalationSos($routed);

    $this->travel(4)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();
    expect($alert->fresh()->escalated_at)->toBeNull();

    $this->travel(1)->minute();
    $this->artisan('alerts:escalate')->expectsOutputToContain('1 alerte examinée, 1 escaladée')->assertSuccessful();

    $escalation = AlertEscalation::query()->where('alert_id', $alert->id)->sole();
    $log = AuditLog::query()->ofAction('alerts.escalated')->forSubject($alert)->sole();

    expect($alert->fresh()->escalated_at)->not->toBeNull()
        ->and($escalation->only(['from_contact_id', 'to_contact_id', 'reason']))->toBe(['from_contact_id' => $routed->id, 'to_contact_id' => $national->id, 'reason' => 'no_ack_5min'])
        ->and($log->actor)->toBe(AuditLog::ACTOR_SYSTEM)
        ->and($log->ip)->toBeNull()
        ->and($log->user_id)->toBeNull()
        ->and($log->detail)->toContain('motif=no_ack_5min')->toContain('apres=5 min');

    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->alertId === $alert->id && $e->toContactId === $national->id
        && $e->fromContactId === $routed->id && $e->minutesSinceCreation === 5 && $e->reason === 'no_ack_5min' && ! $e->targetsAdmins());
});

it('n’escalade jamais deux fois un même SOS', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $alert = escalationSos(DirectoryContact::factory()->focalZone()->create());

    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();
    $this->artisan('alerts:escalate')->assertSuccessful();
    $this->travel(1)->minute();
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect(AlertEscalation::query()->where('alert_id', $alert->id)->count())->toBe(1);
    Event::assertDispatchedTimes(AlertEscalated::class, 1);
});

it('n’escalade pas un SOS accusé, annulé, en sécurité ou déjà escaladé', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $acknowledged = escalationSos();
    $cancelled = escalationSos();
    $safe = escalationSos();
    $escalated = escalationSos();

    $this->travel(2)->minutes();
    $acknowledged->update(['acknowledged_at' => now()]);
    $cancelled->update(['cancelled_at' => now(), 'status' => 'clos', 'closed_at' => now()]);
    $safe->update(['safe_at' => now(), 'status' => 'traite']);
    $escalated->update(['escalated_at' => now()]);

    $this->travel(3)->minutes();
    $this->artisan('alerts:escalate')->expectsOutputToContain('0 alerte examinée')->assertSuccessful();

    expect(AlertEscalation::query()->count())->toBe(0);
    Event::assertNotDispatched(AlertEscalated::class);
});

it('escalade aux admins de garde un SOS déjà routé vers le point focal national', function (): void {
    $national = DirectoryContact::factory()->focalNational()->create();
    $admin = AlertFixtures::admin();
    OnCallShift::factory()->forUser($admin)->admin()->between(now()->subHour(), now()->addHours(12))->create();
    $alert = escalationSos($national);

    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect($alert->fresh()->escalated_at)->not->toBeNull()
        ->and(AlertEscalation::query()->where('alert_id', $alert->id)->sole()->to_contact_id)->toBeNull();

    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->targetsAdmins() && $e->adminIds === [$admin->id]);
});

it('se replie sur les admins habilités sans point focal national, et prévient quand il n’y a personne', function (): void {
    $admin = AlertFixtures::admin();
    $first = escalationSos();

    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->alertId === $first->id && $e->adminIds === [$admin->id]);

    $admin->removeRole('admin');
    Log::spy();
    $second = escalationSos();
    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->alertId === $second->id && $e->adminIds === []);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'ni point focal national'))->once();
});

it('rappelle un signalement à 24 h puis l’escalade à 48 h', function (): void {
    $routed = DirectoryContact::factory()->create();
    $national = DirectoryContact::factory()->focalNational()->create();
    $report = Alert::factory()->ofType(AlertType::Report)->routedTo($routed)->create();

    $this->travel(23 * 60 + 59)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();
    expect($report->fresh()->reminded_at)->toBeNull();

    $this->travel(1)->minute();
    $this->artisan('alerts:escalate')->expectsOutputToContain('1 rappelée')->assertSuccessful();
    expect($report->fresh()->reminded_at)->not->toBeNull()
        ->and($report->fresh()->escalated_at)->toBeNull()
        ->and(AuditLog::query()->ofAction('alerts.reminded')->forSubject($report)->sole()->actor)->toBe(AuditLog::ACTOR_SYSTEM);
    Event::assertDispatched(AlertReminderDue::class, fn (AlertReminderDue $e): bool => $e->routedContactId === $routed->id && $e->minutesSinceCreation === 1440);

    $this->travel(60)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();
    Event::assertDispatchedTimes(AlertReminderDue::class, 1);

    $this->travel(23)->hours();
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect(AlertEscalation::query()->where('alert_id', $report->id)->sole()->only(['to_contact_id', 'reason']))->toBe(['to_contact_id' => $national->id, 'reason' => 'no_ack_48h']);
    Event::assertDispatched(AlertEscalated::class, fn (AlertEscalated $e): bool => $e->reason === 'no_ack_48h');
});

it('n’escalade ni ne rappelle une alerte historique importée, vieille de 30 jours', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $old = [
        Alert::factory()->ofType(AlertType::Report)->createdMinutesAgo(30 * 24 * 60)->create(),
        Alert::factory()->ofType(AlertType::Need)->createdMinutesAgo(30 * 24 * 60)->create(),
        Alert::factory()->sos()->createdMinutesAgo(30 * 24 * 60)->create(),
    ];

    $this->artisan('alerts:escalate')->expectsOutputToContain('0 alerte examinée')->assertSuccessful();

    foreach ($old as $alert) {
        expect($alert->fresh()->only(['escalated_at', 'reminded_at']))->toBe(['escalated_at' => null, 'reminded_at' => null]);
    }

    Event::assertNotDispatched(AlertEscalated::class);
    Event::assertNotDispatched(AlertReminderDue::class);
});

it('traite un SOS échu avant les rappels de signalements plus anciens', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    Alert::factory()->count(3)->ofType(AlertType::Report)->createdMinutesAgo(25 * 60)->create();
    $sos = Alert::factory()->sos()->createdMinutesAgo(6)->create();

    $this->artisan('alerts:escalate', ['--limit' => 1])->assertSuccessful();

    expect($sos->fresh()->escalated_at)->not->toBeNull()
        ->and(Alert::query()->whereNotNull('reminded_at')->count())->toBe(0);
});

it('applique les délais surchargés par la configuration', function (): void {
    config(['xomiton.alerts.escalation.sos.escalate_after_minutes' => 2]);
    DirectoryContact::factory()->focalNational()->create();
    $alert = escalationSos();

    $this->travel(2)->minutes();
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect($alert->fresh()->escalated_at)->not->toBeNull();
});

it('simule sans rien écrire avec --dry-run', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $alert = escalationSos();

    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate', ['--dry-run' => true])
        ->expectsOutputToContain('[simulation] Escalade '.$alert->displayCode())
        ->assertSuccessful();

    expect($alert->fresh()->escalated_at)->toBeNull()
        ->and(AlertEscalation::query()->count())->toBe(0)
        ->and(Cache::has(EscalateAlertsCommand::SUCCESS_MARKER))->toBeFalse();
    Event::assertNotDispatched(AlertEscalated::class);
});

it('borne le nombre d’alertes par exécution avec --limit', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $alerts = [escalationSos(), escalationSos(), escalationSos()];

    $this->travel(5)->minutes();
    $this->artisan('alerts:escalate', ['--limit' => 1])->assertSuccessful();
    expect(Alert::query()->whereNotNull('escalated_at')->count())->toBe(1);

    $this->artisan('alerts:escalate', ['--limit' => 1])->assertSuccessful();
    $this->artisan('alerts:escalate', ['--limit' => 1])->assertSuccessful();
    expect(Alert::query()->whereNotNull('escalated_at')->count())->toBe(count($alerts));

    $this->artisan('alerts:escalate', ['--limit' => 0])->assertExitCode(2);
});

it('isole une erreur, échoue sans poser le marqueur de succès, puis le pose quand tout passe', function (): void {
    DirectoryContact::factory()->focalNational()->create();
    $broken = escalationSos();
    escalationSos();
    $this->travel(5)->minutes();

    $failing = true;
    Alert::updating(function (Alert $alert) use (&$failing, $broken): void {
        if ($failing && $alert->is($broken)) {
            throw new RuntimeException('base indisponible');
        }
    });

    $this->artisan('alerts:escalate')->expectsOutputToContain('1 erreur')->assertFailed();

    expect(Alert::query()->whereNotNull('escalated_at')->count())->toBe(1)
        ->and($broken->fresh()->escalated_at)->toBeNull()
        ->and(AlertEscalation::query()->where('alert_id', $broken->id)->exists())->toBeFalse()
        ->and(Cache::has(EscalateAlertsCommand::SUCCESS_MARKER))->toBeFalse();

    $failing = false;
    $this->artisan('alerts:escalate')->assertSuccessful();

    expect(Alert::query()->whereNotNull('escalated_at')->count())->toBe(2)
        ->and(Cache::get(EscalateAlertsCommand::SUCCESS_MARKER))->toBe(now()->toIso8601String());
});
