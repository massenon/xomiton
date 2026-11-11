<?php

declare(strict_types=1);

use App\Enums\ReportFormat;
use App\Models\GeneratedReport;
use App\Support\Audit;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/*
 * `reports:purge` (P1-16) : fichiers de generated_reports au-delà d'expires_at supprimés du disque ; ligne marquée
 * (fichier et taille effacés) si le journal d'audit la référence, supprimée sinon ; idempotent.
 */

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::create(2026, 10, 17, 3, 30));
    Storage::fake('local');
    config(['xomiton.reports.disk' => 'local']);
});

it('supprime les fichiers expirés, marque les lignes référencées et supprime les autres', function (): void {
    $referenced = GeneratedReport::factory()->expired()->create();
    Storage::disk('local')->put($referenced->file_path, 'pdf');
    Audit::log('report.generate', $referenced, 'Rapport généré depuis l’écran Rapports.');

    $orphan = GeneratedReport::factory()->expired()->create();
    Storage::disk('local')->put($orphan->file_path, 'xlsx');

    $missingFile = GeneratedReport::factory()->expired()->create();

    $valid = GeneratedReport::factory()->done()->create();
    Storage::disk('local')->put($valid->file_path, 'pdf');

    $preview = GeneratedReport::factory()->done(ReportFormat::Preview)->create(['expires_at' => now()->subDay()]);

    $this->artisan('reports:purge')
        ->expectsOutputToContain('2 fichiers de rapport supprimés sur le disque « local », 1 rapport marqué (référencé par le journal), 2 rapports supprimés.')
        ->assertSuccessful();

    Storage::disk('local')->assertMissing($referenced->file_path);
    Storage::disk('local')->assertMissing($orphan->file_path);
    Storage::disk('local')->assertExists($valid->file_path);

    $referenced->refresh();

    expect($referenced->file_path)->toBeNull()
        ->and($referenced->file_size)->toBeNull()
        ->and($referenced->expires_at)->not->toBeNull()
        ->and($referenced->hasFile())->toBeFalse()
        ->and(GeneratedReport::query()->whereKey($orphan->id)->exists())->toBeFalse()
        ->and(GeneratedReport::query()->whereKey($missingFile->id)->exists())->toBeFalse()
        ->and($valid->fresh()->file_path)->not->toBeNull()
        ->and($preview->fresh())->not->toBeNull();
});

it('est idempotente', function (): void {
    $report = GeneratedReport::factory()->expired()->create();
    Storage::disk('local')->put($report->file_path, 'pdf');

    $this->artisan('reports:purge')
        ->expectsOutputToContain('1 fichier de rapport supprimé sur le disque « local », 0 rapport marqué (référencé par le journal), 1 rapport supprimé.')
        ->assertSuccessful();

    $this->artisan('reports:purge')
        ->expectsOutputToContain('0 fichier de rapport supprimé sur le disque « local », 0 rapport marqué (référencé par le journal), 0 rapport supprimé.')
        ->assertSuccessful();
});
