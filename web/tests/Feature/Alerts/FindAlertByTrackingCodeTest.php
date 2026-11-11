<?php

declare(strict_types=1);

use App\Application\Alerts\Queries\FindAlertByTrackingCode;
use App\Enums\AlertStatus;
use App\Http\Resources\Alerts\AlertResource;
use App\Http\Resources\Alerts\AlertTrackingResource;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\DirectoryContact;
use Illuminate\Http\Request;

/*
 * P5-02 / FR-ALT-01 : suivi sans compte par code — saisie tolérante, alerte annulée ou close depuis plus de 90 jours
 * introuvable ; sorties sans données sensibles (rôle seul du contact routé).
 */

it('retrouve une alerte par son code saisi en minuscules avec le préfixe', function (): void {
    $alert = Alert::factory()->create();

    expect(app(FindAlertByTrackingCode::class)('sig-'.strtolower($alert->tracking_code))?->is($alert))->toBeTrue()
        ->and(app(FindAlertByTrackingCode::class)('SIG-AAAAAAAAAA'))->toBeNull()
        ->and(app(FindAlertByTrackingCode::class)('n’importe quoi'))->toBeNull();
});

it('cache les alertes annulées et celles closes depuis plus de 90 jours', function (): void {
    $old = Alert::factory()->withStatus(AlertStatus::Closed)->create(['closed_at' => now()->subDays(91)]);
    $recent = Alert::factory()->withStatus(AlertStatus::Closed)->create(['closed_at' => now()->subDays(10)]);
    $cancelled = Alert::factory()->sos()->cancelled()->create();

    expect(app(FindAlertByTrackingCode::class)($old->tracking_code))->toBeNull()
        ->and(app(FindAlertByTrackingCode::class)($recent->tracking_code)?->is($recent))->toBeTrue()
        ->and(app(FindAlertByTrackingCode::class)($cancelled->tracking_code))->toBeNull();
});

it('ne renvoie jamais le récit, la position, le contact, la clé ni le nom du contact routé', function (): void {
    $contact = DirectoryContact::factory()->hidden()->create(['name' => 'NOM Confidentiel']);
    $alert = Alert::factory()->sos()->routedTo($contact)->withContact('+229 01 97 38 88 47')->withIdempotencyKey()->create(['description' => 'Récit secret']);
    AlertStatusChange::factory()->create(['alert_id' => $alert->id, 'from_status' => null, 'to_status' => AlertStatus::New]);

    $found = app(FindAlertByTrackingCode::class)($alert->tracking_code);
    $tracking = json_encode((new AlertTrackingResource($found))->toArray(new Request));
    $owner = json_encode((new AlertResource($found))->toArray(new Request));

    foreach ([$tracking, $owner] as $json) {
        expect($json)->not->toContain('Récit secret')->not->toContain('97 38')->not->toContain('NOM Confidentiel')
            ->not->toContain((string) $alert->idempotency_key)->not->toContain('"lat"');
    }

    expect(json_decode($tracking, true))->toMatchArray(['type' => 'sos', 'status' => 'nouveau', 'tracking_code_display' => $alert->displayCode()])
        ->and(json_decode($tracking, true)['history'])->toHaveCount(1)
        ->and(json_decode($owner, true)['routed'])->toBe(['role' => $contact->role->label()])
        ->and(json_decode($tracking, true)['routed_role'])->toBe($contact->role->label())
        ->and(json_decode($owner, true)['can_cancel_until'])->not->toBeNull();
});
