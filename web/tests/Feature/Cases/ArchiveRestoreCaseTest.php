<?php

declare(strict_types=1);

use App\Application\Cases\ArchiveCase;
use App\Application\Cases\RestoreCase;
use App\Domain\Cases\Exceptions\CaseLocked;
use App\Domain\Cases\Exceptions\InvalidCaseTransition;
use App\Enums\CaseStatus;
use App\Models\AuditLog;
use App\Models\CaseRecord;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Tests\Support\CaseFixtures;

/*
 * P11-01 : archivage logique d'un dossier par l'administration (`cases.view_all`) et restauration. Le dossier n'est jamais
 * supprimé ; archivé, il est en lecture seule et sort des listes de la pair·e ; la restauration le rend « clos ».
 */

beforeEach(function (): void {
    CaseFixtures::seed();
});

function archiveAdmin(): User
{
    $user = User::factory()->create();
    $user->syncRoles(['admin']);

    return $user;
}

it('archive un dossier actif depuis n’importe quel statut : clos au passage, archivé, audit sans contenu', function (): void {
    $admin = archiveAdmin();
    $case = CaseRecord::factory()->create(['code' => 'VBG-0001', 'status' => CaseStatus::InProgress]);

    $archived = app(ArchiveCase::class)($admin, $case);

    expect($archived->status)->toBe(CaseStatus::Archived)
        ->and($archived->archived_at)->not->toBeNull()
        ->and($archived->closed_at)->not->toBeNull();
    expect(AuditLog::query()->where('action', 'cases.archived')->sole()->detail)->toBe('code=VBG-0001 ; statut=en_cours→archive');
    expect(CaseRecord::query()->count())->toBe(1);
});

it('refuse l’archivage et la restauration à la pair·e, même sur son propre dossier', function (): void {
    $peer = CaseFixtures::peer();
    $case = CaseRecord::factory()->collectedBy($peer)->create();

    expect(fn () => app(ArchiveCase::class)($peer, $case))->toThrow(AuthorizationException::class);
    expect(fn () => app(RestoreCase::class)($peer, $case))->toThrow(AuthorizationException::class);
    expect($case->fresh()->status)->toBe(CaseStatus::Open);
});

it('refuse d’archiver deux fois ou un dossier anonymisé, et de restaurer ce qui n’est pas archivé', function (): void {
    $admin = archiveAdmin();
    $case = CaseRecord::factory()->create();
    $archive = app(ArchiveCase::class);

    $archive($admin, $case);
    expect(fn () => $archive($admin, $case))->toThrow(CaseLocked::class);

    $anonymous = CaseRecord::factory()->archived()->create(['anonymized_at' => now(), 'notes' => null]);
    expect(fn () => app(RestoreCase::class)($admin, $anonymous))->toThrow(CaseLocked::class);

    $open = CaseRecord::factory()->create();
    expect(fn () => app(RestoreCase::class)($admin, $open))->toThrow(InvalidCaseTransition::class);
});

it('restaure un dossier archivé en « clos » et le rend à la liste de la pair·e', function (): void {
    $admin = archiveAdmin();
    $peer = CaseFixtures::peer();
    $case = CaseRecord::factory()->collectedBy($peer)->create(['code' => 'VBG-0001']);

    app(ArchiveCase::class)($admin, $case);
    $this->actingAs($peer, 'sanctum')->getJson('/api/v1/pair/cases')->assertOk()->assertJsonCount(0, 'data');

    $restored = app(RestoreCase::class)($admin, $case->fresh());
    expect($restored->status)->toBe(CaseStatus::Closed)->and($restored->archived_at)->toBeNull();
    $this->actingAs($peer, 'sanctum')->getJson('/api/v1/pair/cases')->assertOk()->assertJsonCount(1, 'data');
    expect(AuditLog::query()->where('action', 'cases.restored')->sole()->detail)->toBe('code=VBG-0001 ; statut=archive→clos');
});
