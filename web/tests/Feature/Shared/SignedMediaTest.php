<?php

declare(strict_types=1);

use App\Application\Shared\MediaUploader;
use App\Http\Controllers\Web\SignedMediaController;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/*
 * P3-04 : un fichier privé n'est pas servi en direct ; il ne l'est que par un lien temporaire signé (media.signed →
 * SignedMediaController). La route n'étant pas encore déclarée dans routes/web.php, les tests l'enregistrent eux-mêmes.
 */

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->uploader = app(MediaUploader::class);
    $this->media = $this->uploader->upload(
        UploadedFile::fake()->createWithContent('preuve.pdf', "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n"),
        'documents',
    );
});

/**
 * Déclare la route `media.signed` telle qu'elle doit figurer dans routes/web.php, si elle n'y est pas encore.
 */
function registerSignedMediaRoute(): void
{
    if (Route::has(MediaUploader::ROUTE_SIGNED)) {
        return;
    }

    Route::get('/media/{disk}/{path}', SignedMediaController::class)->where('path', '.*')->name(MediaUploader::ROUTE_SIGNED);
    Route::getRoutes()->refreshNameLookups();
}

it('ne sert pas un fichier privé en direct : hors racine web, et la route /storage exige une signature', function (): void {
    $path = $this->media->path;

    expect(is_file(public_path('storage/'.$path)))->toBeFalse()
        ->and(str_starts_with(Storage::disk('local')->path($path), public_path()))->toBeFalse();

    // Route `storage.local` enregistrée par Laravel (disque local, serve = true) : refus sans signature, aucun contenu.
    $response = $this->get('/storage/'.$path);

    expect($response->getStatusCode())->toBeIn([403, 404])
        ->and($response->getContent())->not->toContain('%PDF');
});

it('signedUrl refuse un disque hors liste', function (): void {
    expect(fn () => $this->uploader->signedUrl('public', $this->media->path))->toThrow(InvalidArgumentException::class);
});

it('sert un fichier privé par un lien signé valide, puis refuse une signature altérée ou expirée (403)', function (): void {
    registerSignedMediaRoute();

    $url = $this->uploader->signedUrl('local', $this->media->path, 10);

    expect($url)->toContain('/media/local/'.$this->media->path)->toContain('signature=')->toContain('expires=');

    $this->get($url)
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertHeader('Cache-Control', 'max-age=0, no-store, private');

    // Signature altérée.
    // Premier caractère toujours changé (« f » si ce n'en était pas un, « 0 » sinon), sans quoi le test échoue une fois sur 16.
    $tampered = preg_replace_callback('/signature=([0-9a-f])/', fn (array $m): string => 'signature='.($m[1] === 'f' ? '0' : 'f'), $url);
    $this->get($tampered ?? '')->assertForbidden();

    // Chemin modifié avec la même signature.
    $this->get(str_replace($this->media->path, 'documents/autre.pdf', $url))->assertForbidden();

    // Sans signature.
    $this->get(strtok($url, '?'))->assertForbidden();

    // Expirée.
    $this->travel(11)->minutes();
    $this->get($url)->assertForbidden();
});

it('répond 404 avec une signature valide pour un fichier absent ou un disque non servi', function (): void {
    registerSignedMediaRoute();

    $missing = URL::temporarySignedRoute(MediaUploader::ROUTE_SIGNED, now()->addMinutes(5), ['disk' => 'local', 'path' => 'documents/absent.pdf']);
    $traversal = URL::temporarySignedRoute(MediaUploader::ROUTE_SIGNED, now()->addMinutes(5), ['disk' => 'local', 'path' => '../.env']);
    $publicDisk = URL::temporarySignedRoute(MediaUploader::ROUTE_SIGNED, now()->addMinutes(5), ['disk' => 'public', 'path' => $this->media->path]);

    $this->get($missing)->assertNotFound();
    $this->get($traversal)->assertNotFound();
    $this->get($publicDisk)->assertNotFound();
});

it('sert une image privée ré-encodée avec son type réel', function (): void {
    registerSignedMediaRoute();
    $image = $this->uploader->upload(UploadedFile::fake()->image('photo.jpg', 50, 50), 'images');

    $this->get($this->uploader->signedUrl('local', $image->path))->assertOk()->assertHeader('Content-Type', 'image/webp');
    $this->get($this->uploader->signedUrl('local', (string) $image->fallbackPath))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});
