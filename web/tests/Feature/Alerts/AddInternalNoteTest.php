<?php

declare(strict_types=1);

use App\Application\Alerts\AddInternalNote;
use App\Http\Requests\Alerts\AddAlertNoteRequest;
use App\Models\Alert;
use App\Models\AuditLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Tests\Support\AlertFixtures;

/*
 * P5-02 : AddInternalNote — corps chiffré, journal sans le corps, 1000 caractères au plus, alerts.manage.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('ajoute une note chiffrée, journalisée sans son contenu', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->create();
    $body = 'Appelée à 14 h, rappel prévu demain matin.';

    $note = app(AddInternalNote::class)($alert, $admin, "  {$body}  ");

    expect(DB::table('alert_notes')->where('id', $note->id)->value('body'))->not->toBe($body)->not->toContain('rappel')
        ->and($note->fresh()->body)->toBe($body)
        ->and($note->user_id)->toBe($admin->id)
        ->and(AuditLog::query()->ofAction('alerts.note_added')->forSubject($alert)->value('detail'))->not->toContain('rappel');
});

it('refuse une note vide ou trop longue et un compte sans alerts.manage', function (): void {
    $admin = AlertFixtures::admin();
    $alert = Alert::factory()->create();

    expect(fn () => app(AddInternalNote::class)($alert, $admin, str_repeat('a', 1001)))->toThrow(ValidationException::class)
        ->and(fn () => app(AddInternalNote::class)($alert, $admin, '   '))->toThrow(ValidationException::class)
        ->and(fn () => app(AddInternalNote::class)($alert, AlertFixtures::member('pair'), 'Note'))->toThrow(AuthorizationException::class);
});

it('valide la requête : 1000 caractères au plus et alerts.manage', function (): void {
    Route::post('/_test/alertes/{alert:ulid}/notes', fn (AddAlertNoteRequest $request, Alert $alert) => response()->json(['ok' => true]))->middleware('web');
    $alert = Alert::factory()->create();

    $this->actingAs(AlertFixtures::member('pair'))->postJson("/_test/alertes/{$alert->ulid}/notes", ['body' => 'Note'])->assertForbidden();
    $this->actingAs(AlertFixtures::admin())->postJson("/_test/alertes/{$alert->ulid}/notes", ['body' => str_repeat('a', 1001)])->assertJsonValidationErrors('body');
    $this->postJson("/_test/alertes/{$alert->ulid}/notes", ['body' => 'Note'])->assertOk();
});
