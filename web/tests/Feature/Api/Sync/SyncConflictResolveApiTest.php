<?php

declare(strict_types=1);

use App\Application\Account\DeviceRegistry;
use App\Enums\ConflictStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;

/*
 * Décision client du 2026-10-29 : le·la pair tranche lui·elle-même un conflit de synchronisation 409 (valeur du serveur
 * ou valeur de l'appareil) — seulement sur ses propres dossiers et ses propres appareils ; la fusion à la main reste à
 * l'administration ; journal `sync.conflict_resolved`.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, ValidationRulesSeeder::class]);
    Notification::fake();

    $this->pair = AlertFixtures::member('pair');
    $this->device = SyncDevice::factory()->create(['user_id' => $this->pair->id, 'conflicts_count' => 1]);
    $this->case = CaseRecord::factory()->collectedBy($this->pair)->create(['referral' => 'Juriste', 'notes' => 'Notes du serveur.']);
    $this->conflict = SyncConflict::factory()->create([
        'device_id' => $this->device->id,
        'subject_type' => $this->case->getMorphClass(),
        'subject_id' => $this->case->id,
        'field' => 'referral',
        'server_value' => 'Juriste',
        'device_value' => 'Psychologue',
    ]);
});

function resolveConflict(SyncConflict $conflict, array $body, ?SyncDevice $device = null)
{
    return test()->withHeader(DeviceRegistry::HEADER, ($device ?? test()->device)->device_uuid)
        ->postJson('/api/v1/sync/conflicts/'.$conflict->id.'/resolve', $body);
}

it('laisse le pair garder la valeur de l’appareil : appliquée au dossier, conflit résolu, compteur baissé, journal', function (): void {
    Sanctum::actingAs($this->pair);

    resolveConflict($this->conflict, ['resolution' => 'appareil'])->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertJsonPath('data.status', 'resolu')
        ->assertJsonPath('data.resolution', 'appareil')
        ->assertJsonPath('data.resolved_value', 'Psychologue')
        ->assertJsonPath('meta.open', 0);

    expect($this->case->fresh()->referral)->toBe('Psychologue')
        ->and($this->conflict->fresh()->status)->toBe(ConflictStatus::Resolved)
        ->and($this->conflict->fresh()->resolved_by)->toBe($this->pair->id)
        ->and($this->device->fresh()->conflicts_count)->toBe(0)
        ->and(AuditLog::query()->where('action', 'sync.conflict_resolved')->where('user_id', $this->pair->id)->where('detail', 'like', '%choix=appareil%par=pair%')->exists())->toBeTrue();
});

it('laisse le pair garder la valeur du serveur : le dossier ne change pas', function (): void {
    Sanctum::actingAs($this->pair);

    resolveConflict($this->conflict, ['resolution' => 'serveur'])->assertOk()
        ->assertJsonPath('data.resolution', 'serveur')
        ->assertJsonPath('data.resolved_value', 'Juriste');

    expect($this->case->fresh()->referral)->toBe('Juriste')->and($this->conflict->fresh()->status)->toBe(ConflictStatus::Resolved);
});

it('annonce ce que le pair peut faire dans la liste des conflits (resolve oui, merge non)', function (): void {
    Sanctum::actingAs($this->pair);

    $this->withHeader(DeviceRegistry::HEADER, $this->device->device_uuid)->getJson('/api/v1/sync/conflicts?status=open')->assertOk()
        ->assertJsonPath('data.0.can.resolve', true)
        ->assertJsonPath('data.0.can.merge', false);
});

it('refuse la fusion au pair (administration seulement) et rend 422 si le conflit est déjà tranché', function (): void {
    Sanctum::actingAs($this->pair);

    resolveConflict($this->conflict, ['resolution' => 'fusion', 'merged' => 'Mélange'])->assertForbidden();
    resolveConflict($this->conflict, ['resolution' => 'inconnue'])->assertStatus(422);
    expect($this->conflict->fresh()->status)->toBe(ConflictStatus::Open);

    resolveConflict($this->conflict, ['resolution' => 'serveur'])->assertOk();
    resolveConflict($this->conflict, ['resolution' => 'appareil'])->assertStatus(422)->assertJsonPath('code', 'conflict_already_resolved');
});

it('ne laisse pas un pair trancher le conflit d’un autre pair ni sur le dossier d’un autre (404 neutre)', function (): void {
    $other = AlertFixtures::member('pair');
    $otherDevice = SyncDevice::factory()->create(['user_id' => $other->id]);
    Sanctum::actingAs($other);

    resolveConflict($this->conflict, ['resolution' => 'appareil'], $otherDevice)->assertNotFound();
    expect($this->case->fresh()->referral)->toBe('Juriste')->and($this->conflict->fresh()->status)->toBe(ConflictStatus::Open);

    // Même appareil, mais le dossier a été attribué à quelqu'un d'autre : plus à lui·elle.
    $this->case->forceFill(['collected_by' => $other->id])->save();
    Sanctum::actingAs($this->pair);
    resolveConflict($this->conflict, ['resolution' => 'appareil'])->assertNotFound();
    expect($this->conflict->fresh()->status)->toBe(ConflictStatus::Open);
});

it('refuse un membre, exige un appareil enregistré et laisse l’administration fusionner', function (): void {
    $member = AlertFixtures::member();
    $memberDevice = SyncDevice::factory()->create(['user_id' => $member->id]);
    Sanctum::actingAs($member);
    resolveConflict($this->conflict, ['resolution' => 'appareil'], $memberDevice)->assertNotFound();

    Sanctum::actingAs($this->pair);
    $this->postJson('/api/v1/sync/conflicts/'.$this->conflict->id.'/resolve', ['resolution' => 'appareil'])->assertForbidden()->assertJsonPath('code', 'device_required');

    $admin = AlertFixtures::admin();
    $adminDevice = SyncDevice::factory()->create(['user_id' => $admin->id]);
    $this->conflict->forceFill(['field' => 'notes', 'server_value' => 'A', 'device_value' => 'B'])->save();
    Sanctum::actingAs($admin);

    resolveConflict($this->conflict, ['resolution' => 'fusion', 'merged' => 'Notes fusionnées par l’administration.'], $adminDevice)->assertOk()
        ->assertJsonPath('data.resolution', 'fusion')->assertJsonPath('data.resolved_value', 'Notes fusionnées par l’administration.');
    expect($this->case->fresh()->notes)->toBe('Notes fusionnées par l’administration.');
});
