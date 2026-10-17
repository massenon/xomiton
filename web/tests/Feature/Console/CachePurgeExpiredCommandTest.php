<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/*
 * `cache:purge-expired` (P1-16) : pilote `file` seulement, suppression des fichiers dont l'horodatage d'expiration
 * en tête (10 chiffres, Illuminate\Cache\FileStore) est passé ; les autres pilotes ne font rien.
 */

/**
 * Écrit un fichier de cache au format du pilote `file`, dans la même arborescence (sha1 découpé).
 */
function writeCacheFile(string $directory, string $key, string $header, mixed $payload = 'valeur'): string
{
    $hash = sha1($key);
    $path = $directory.'/'.implode('/', array_slice(str_split($hash, 2), 0, 2)).'/'.$hash;

    File::ensureDirectoryExists(dirname($path));
    File::put($path, $header.serialize($payload));

    return $path;
}

beforeEach(function (): void {
    $this->directory = sys_get_temp_dir().'/xomiton-cache-'.Str::lower(Str::random(8));
    File::ensureDirectoryExists($this->directory);
    File::put($this->directory.'/.gitignore', "*\n!.gitignore\n");

    config([
        'cache.default' => 'file',
        'cache.stores.file.driver' => 'file',
        'cache.stores.file.path' => $this->directory,
        'cache.stores.file.lock_path' => $this->directory,
    ]);
});

afterEach(function (): void {
    File::deleteDirectory($this->directory);
});

it('supprime les fichiers expirés et conserve les entrées valides, permanentes ou étrangères', function (): void {
    $expired = writeCacheFile($this->directory, 'expiree', (string) (time() - 100));
    $longExpired = writeCacheFile($this->directory, 'tres-expiree', '1700000000');
    $live = writeCacheFile($this->directory, 'valide', (string) (time() + 3600));
    $forever = writeCacheFile($this->directory, 'permanente', '9999999999');
    $foreign = $this->directory.'/ab/fichier-etranger.txt';
    File::ensureDirectoryExists(dirname($foreign));
    File::put($foreign, 'pas un fichier de cache');

    // Une entrée écrite par le pilote lui-même, pour vérifier que le format lu est bien le sien.
    Cache::store('file')->put('ecrite-par-laravel', 'x', 3600);
    $written = Cache::store('file')->getStore()->path('ecrite-par-laravel');

    $this->artisan('cache:purge-expired')
        ->expectsOutputToContain('2 fichiers de cache expirés supprimés sur 6 fichiers examinés')
        ->assertSuccessful();

    expect(File::exists($expired))->toBeFalse()
        ->and(File::exists($longExpired))->toBeFalse()
        ->and(File::exists($live))->toBeTrue()
        ->and(File::exists($forever))->toBeTrue()
        ->and(File::exists($foreign))->toBeTrue()
        ->and(File::exists($written))->toBeTrue()
        ->and(File::exists($this->directory.'/.gitignore'))->toBeTrue()
        ->and(Cache::store('file')->get('ecrite-par-laravel'))->toBe('x');

    $this->artisan('cache:purge-expired')
        ->expectsOutputToContain('0 fichier de cache expiré supprimé sur 4 fichiers examinés')
        ->assertSuccessful();
});

it('ne fait rien pour un autre pilote', function (): void {
    $expired = writeCacheFile($this->directory, 'expiree', (string) (time() - 100));
    config(['cache.default' => 'array']);

    $this->artisan('cache:purge-expired')
        ->expectsOutputToContain('Le cache « array » utilise le pilote « array » : il purge lui-même ses entrées expirées, rien à faire.')
        ->assertSuccessful();

    expect(File::exists($expired))->toBeTrue();
});

it('tolère un répertoire de cache absent', function (): void {
    config(['cache.stores.file.path' => $this->directory.'/absent']);

    $this->artisan('cache:purge-expired')
        ->expectsOutputToContain('0 fichier de cache expiré supprimé sur 0 fichier examiné')
        ->assertSuccessful();
});
