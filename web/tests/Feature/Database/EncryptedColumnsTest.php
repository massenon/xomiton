<?php

declare(strict_types=1);

use App\Models\Setting;
use App\Models\SyncConflict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * `config('xomiton.encrypted_columns')` (P1-15) : liste exhaustive des colonnes chiffrées, comparée aux casts
 * `encrypted` réellement déclarés par les modèles (plus `settings.value`, chiffrée à la main pour les secrets).
 * Le test échoue dès qu'une colonne chiffrée est ajoutée à un modèle sans être déclarée, ou l'inverse.
 */

/**
 * @return array<string, list<string>> table => colonnes chiffrées, triées
 */
function encryptedColumnsFromModels(): array
{
    $tables = [];

    foreach (glob(app_path('Models/*.php')) ?: [] as $file) {
        $class = 'App\\Models\\'.basename($file, '.php');

        if (! class_exists($class) || ! is_subclass_of($class, Model::class) || (new ReflectionClass($class))->isAbstract()) {
            continue;
        }

        /** @var Model $model */
        $model = new $class;

        foreach ($model->getCasts() as $column => $cast) {
            if (is_string($cast) && str_starts_with($cast, 'encrypted')) {
                $tables[$model->getTable()][] = $column;
            }
        }
    }

    // Les secrets de `settings` sont chiffrés par Setting::encodeRaw, sans cast Eloquent.
    $tables[(new Setting)->getTable()][] = 'value';

    ksort($tables);

    foreach ($tables as &$columns) {
        sort($columns);
    }

    return $tables;
}

it('déclare dans la configuration chaque colonne chiffrée des modèles, et seulement celles-là', function (): void {
    $configured = config('xomiton.encrypted_columns');

    expect($configured)->toBeArray()->not->toBeEmpty();

    ksort($configured);

    foreach ($configured as &$columns) {
        sort($columns);
    }

    unset($columns);

    $actual = encryptedColumnsFromModels();

    foreach ($actual as $table => $columns) {
        expect(array_key_exists($table, $configured))->toBeTrue("table « {$table} » : cast encrypted non déclaré dans xomiton.encrypted_columns");
        expect($configured[$table])->toBe($columns, "table « {$table} » : colonnes déclarées ≠ casts encrypted");
    }

    expect($configured)->toBe($actual);
});

it('ne liste que des tables et des colonnes existantes', function (): void {
    foreach (config('xomiton.encrypted_columns') as $table => $columns) {
        expect(Schema::hasTable($table))->toBeTrue("table « {$table} »");

        foreach ($columns as $column) {
            expect(Schema::hasColumn($table, $column))->toBeTrue("colonne « {$table}.{$column} »");
        }
    }
});

it('chiffre en base les trois valeurs d’un conflit de synchronisation', function (): void {
    $conflict = SyncConflict::factory()->create(['server_value' => 'ouvert', 'device_value' => 'clos']);
    $conflict->resolve(SyncConflict::RESOLUTION_DEVICE);

    $raw = DB::table('sync_conflicts')->where('id', $conflict->getKey())->first();

    expect($raw->server_value)->not->toBe('ouvert')
        ->and($raw->device_value)->not->toBe('clos')
        ->and($raw->resolved_value)->not->toBe('clos')
        ->and(Crypt::decryptString($raw->server_value))->toBe('ouvert')
        ->and($conflict->fresh()->server_value)->toBe('ouvert')
        ->and($conflict->fresh()->resolved_value)->toBe('clos');
});
