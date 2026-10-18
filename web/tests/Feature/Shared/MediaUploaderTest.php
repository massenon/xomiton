<?php

declare(strict_types=1);

use App\Application\Shared\Exceptions\MediaRejected;
use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Rules\UploadAllowed;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

/*
 * P3-04 : liste blanche par type réel, poids, ré-encodage GD (EXIF retiré, orientation appliquée, redimensionnement,
 * WebP + repli, variantes), nom ULID, disque privé par défaut, suppression groupée, règle de validation UploadAllowed.
 */

beforeEach(function (): void {
    Storage::fake('local');
    Storage::fake('public');
    $this->uploader = app(MediaUploader::class);
});

/**
 * JPEG de test fabriqué avec GD : moitié gauche rouge, moitié droite bleue, puis segment APP1 Exif inséré après le
 * marqueur SOI : Orientation = 6 (image à tourner de 90° dans le sens horaire) et Make = « Xomiton Test ».
 */
function jpegWithExif(int $width = 3000, int $height = 2000): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefilledrectangle($image, 0, 0, intdiv($width, 2) - 1, $height - 1, (int) imagecolorallocate($image, 220, 20, 20));
    imagefilledrectangle($image, intdiv($width, 2), 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 20, 20, 220));
    ob_start();
    imagejpeg($image, null, 90);
    $jpeg = (string) ob_get_clean();
    imagedestroy($image);

    $make = "Xomiton Test\0";
    $ifd = pack('v', 2)
        .pack('vvVV', 0x010F, 2, strlen($make), 8 + 2 + 2 * 12 + 4) // Make : ASCII, pointeur vers les données après l'IFD
        .pack('vvVv', 0x0112, 3, 1, 6).pack('v', 0)                // Orientation : SHORT = 6
        .pack('V', 0);                                              // pas d'IFD suivant
    $tiff = 'II'.pack('v', 42).pack('V', 8).$ifd.$make;
    $app1 = "Exif\0\0".$tiff;
    $segment = "\xFF\xE1".pack('n', strlen($app1) + 2).$app1;

    return substr($jpeg, 0, 2).$segment.substr($jpeg, 2);
}

function transparentPng(int $width = 600, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, (int) imagecolorallocatealpha($image, 0, 0, 0, 127));
    imagefilledrectangle($image, 100, 100, 499, 299, (int) imagecolorallocate($image, 4, 51, 195));
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    imagedestroy($image);

    return $png;
}

function pdfBytes(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

function rgbAt(GdImage $image, int $x, int $y): array
{
    $color = imagecolorsforindex($image, imagecolorat($image, $x, $y));

    return [$color['red'], $color['green'], $color['blue'], $color['alpha']];
}

it('ré-encode un JPEG : EXIF retiré, orientation appliquée, 2048 px au plus, WebP avec repli JPEG et variantes', function (): void {
    $source = jpegWithExif();
    expect(exif_read_data('data://image/jpeg;base64,'.base64_encode($source)))->toMatchArray(['Orientation' => 6, 'Make' => 'Xomiton Test']);

    $media = $this->uploader->upload(UploadedFile::fake()->createWithContent('vacances IMG_0042.JPG', $source), 'images');

    expect($media)->toBeInstanceOf(StoredMedia::class)
        ->and($media->disk)->toBe('local')
        ->and($media->mime)->toBe('image/webp')
        ->and($media->originalName)->toBe('vacances IMG_0042.JPG')
        ->and($media->path)->toMatch('#^images/\d{4}/\d{2}/[0-9a-z]{26}\.webp$#')
        ->and($media->fallbackPath)->toBe(substr($media->path, 0, -5).'.jpg')
        // 3000 × 2000 tourné de 90° → 2000 × 3000, puis côté long ramené à 2048.
        ->and([$media->width, $media->height])->toBe([1365, 2048])
        ->and(array_keys($media->variants))->toBe(['thumb', 'medium', 'large'])
        ->and([$media->variant('large')->width, $media->variant('large')->height])->toBe([1066, 1600])
        ->and([$media->variant('thumb')->width, $media->variant('thumb')->height])->toBe([213, 320])
        ->and([$media->variant('medium')->width, $media->variant('medium')->height])->toBe([683, 1024])
        ->and($media->variant('thumb')->path)->toBe(substr($media->path, 0, -5).'-thumb.webp')
        ->and($media->variant('thumb')->fallbackPath)->toBe(substr($media->path, 0, -5).'-thumb.jpg');

    $disk = Storage::disk('local');

    foreach ($media->allPaths() as $path) {
        $disk->assertExists($path);
    }

    // Principal + 3 variantes, chacun en WebP et en JPEG.
    expect($media->allPaths())->toHaveCount(8);

    // Plus aucune métadonnée dans la sortie, ni dans le WebP ni dans le repli JPEG.
    $webp = (string) $disk->get($media->path);
    $jpeg = (string) $disk->get((string) $media->fallbackPath);
    $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($jpeg));

    expect(MediaUploader::detectMime($disk->path($media->path)))->toBe('image/webp')
        ->and(MediaUploader::detectMime($disk->path((string) $media->fallbackPath)))->toBe('image/jpeg')
        ->and(str_contains($webp, 'Xomiton Test'))->toBeFalse()
        ->and(str_contains($webp, 'EXIF'))->toBeFalse()
        ->and(str_contains($jpeg, 'Xomiton Test'))->toBeFalse()
        ->and(str_contains($jpeg, 'Exif'))->toBeFalse()
        ->and(is_array($exif) ? array_intersect_key($exif, ['Orientation' => 1, 'Make' => 1]) : [])->toBe([])
        ->and($media->size)->toBe(strlen($webp));

    // Orientation 6 appliquée : la moitié rouge (gauche à l'origine) est maintenant en haut, la bleue en bas.
    $decoded = imagecreatefromwebp($disk->path($media->path));
    [$r, , $b] = rgbAt($decoded, 682, 100);
    expect($r)->toBeGreaterThan(150)->and($b)->toBeLessThan(80);
    [$r, , $b] = rgbAt($decoded, 682, 1948);
    expect($b)->toBeGreaterThan(150)->and($r)->toBeLessThan(80);
});

it('conserve la transparence d’un PNG dans le WebP et dans le repli PNG, sans agrandir ni créer de variante inutile', function (): void {
    $media = $this->uploader->upload(UploadedFile::fake()->createWithContent('logo.png', transparentPng()), 'images');
    $disk = Storage::disk('local');

    expect($media->mime)->toBe('image/webp')
        ->and([$media->width, $media->height])->toBe([600, 400])
        ->and($media->fallbackPath)->toEndWith('.png')
        ->and(array_keys($media->variants))->toBe(['thumb'])
        ->and($media->hasVariant('medium'))->toBeFalse()
        ->and($media->variant('medium'))->toBe($media);

    $webp = imagecreatefromwebp($disk->path($media->path));
    $png = imagecreatefrompng($disk->path((string) $media->fallbackPath));

    expect(rgbAt($webp, 5, 5)[3])->toBe(127)
        ->and(rgbAt($webp, 300, 200)[3])->toBe(0)
        ->and(rgbAt($png, 5, 5)[3])->toBe(127)
        ->and(rgbAt($png, 300, 200)[3])->toBe(0)
        ->and(MediaUploader::detectMime($disk->path((string) $media->fallbackPath)))->toBe('image/png');
});

it('refuse un script PHP renommé en .jpg d’après son contenu réel, sans rien écrire', function (): void {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', "<?php echo 'pwned';");

    try {
        $this->uploader->upload($file, 'images');
        $this->fail('MediaRejected attendue.');
    } catch (MediaRejected $e) {
        expect($e->reason)->toBe(MediaRejected::REASON_TYPE)
            ->and($e->detectedMime)->toBe('text/x-php')
            ->and($e->getMessage())->toBe('Ce type de fichier n’est pas accepté ici. Formats acceptés : JPEG, PNG, WebP.');
    }

    expect(Storage::disk('local')->allFiles())->toBe([])
        ->and(Storage::disk('public')->allFiles())->toBe([]);
});

it('refuse une image dont l’en-tête est valide mais le contenu corrompu', function (): void {
    $file = UploadedFile::fake()->createWithContent('photo.jpg', "\xFF\xD8\xFF\xE0".str_repeat("\x00", 64));

    expect(fn () => $this->uploader->upload($file, 'images'))->toThrow(MediaRejected::class);
});

it('accepte un PDF dans la collection documents et le refuse dans images', function (): void {
    $media = $this->uploader->upload(UploadedFile::fake()->createWithContent('attestation finale.PDF', pdfBytes()), 'documents');

    expect($media->mime)->toBe('application/pdf')
        ->and($media->path)->toMatch('#^documents/\d{4}/\d{2}/[0-9a-z]{26}\.pdf$#')
        ->and($media->width)->toBeNull()
        ->and($media->isImage())->toBeFalse()
        ->and($media->variants)->toBe([])
        ->and(Storage::disk('local')->get($media->path))->toBe(pdfBytes());

    expect(fn () => $this->uploader->upload(UploadedFile::fake()->createWithContent('image.jpg', pdfBytes()), 'images'))
        ->toThrow(MediaRejected::class, 'Formats acceptés : JPEG, PNG, WebP.');
});

it('refuse un fichier plus lourd que le poids maximal de sa collection', function (): void {
    config(['xomiton.uploads.collections.documents.max_kb' => 1]);
    $file = UploadedFile::fake()->createWithContent('gros.pdf', pdfBytes().str_repeat('%', 2048));

    try {
        $this->uploader->upload($file, 'documents');
        $this->fail('MediaRejected attendue.');
    } catch (MediaRejected $e) {
        expect($e->reason)->toBe(MediaRejected::REASON_TOO_LARGE)
            ->and($e->getMessage())->toContain('Poids maximal : 1 Ko');
    }
});

it('refuse une image dont la surface dépasse la garde mémoire', function (): void {
    config(['xomiton.uploads.image_max_pixels' => 1000]);

    expect(fn () => $this->uploader->upload(UploadedFile::fake()->image('grande.png', 100, 100), 'images'))
        ->toThrow(MediaRejected::class, '100 × 100 px');
});

it('rejette une collection inconnue sans consulter le fichier', function (): void {
    expect(fn () => $this->uploader->upload(UploadedFile::fake()->image('a.png'), 'archives'))
        ->toThrow(InvalidArgumentException::class);
});

it('stocke sur le disque privé par défaut, et sur le disque public seulement à la demande', function (): void {
    $private = $this->uploader->upload(UploadedFile::fake()->image('preuve.jpg', 40, 40), 'images');
    $public = $this->uploader->upload(UploadedFile::fake()->image('avatar.jpg', 40, 40), 'images', public: true);

    expect($private->disk)->toBe('local')->and($private->isPublic())->toBeFalse()
        ->and($public->disk)->toBe('public')->and($public->isPublic())->toBeTrue();

    Storage::disk('local')->assertExists($private->path)->assertMissing($public->path);
    Storage::disk('public')->assertExists($public->path)->assertMissing($private->path);
});

it('supprime un média avec son repli et ses variantes, par DTO ou par chemin', function (): void {
    $first = $this->uploader->upload(UploadedFile::fake()->image('a.jpg', 1200, 800), 'images');
    $second = $this->uploader->upload(UploadedFile::fake()->image('b.jpg', 1200, 800), 'images');
    $disk = Storage::disk('local');

    expect($first->allPaths())->toHaveCount(6)->and($disk->allFiles())->toHaveCount(12);

    $this->uploader->delete($first);

    foreach ($first->allPaths() as $path) {
        $disk->assertMissing($path);
    }

    expect($disk->allFiles())->toHaveCount(6);

    $this->uploader->delete($second->variant('thumb')->path, 'local');

    expect($disk->allFiles())->toBe([]);
});

it('expose une règle de validation UploadAllowed avec le message traduit', function (): void {
    $pdf = UploadedFile::fake()->createWithContent('a.pdf', pdfBytes());

    $invalid = Validator::make(['piece' => $pdf], ['piece' => ['required', new UploadAllowed('images')]]);
    $valid = Validator::make(['piece' => $pdf], ['piece' => ['required', new UploadAllowed('documents')]]);
    $notAFile = Validator::make(['piece' => 'texte'], ['piece' => [new UploadAllowed('documents')]]);

    expect($invalid->fails())->toBeTrue()
        ->and($invalid->errors()->first('piece'))->toBe('Ce type de fichier n’est pas accepté ici. Formats acceptés : JPEG, PNG, WebP.')
        ->and($valid->passes())->toBeTrue()
        ->and($notAFile->fails())->toBeTrue()
        ->and($notAFile->errors()->first('piece'))->toBe('Aucun fichier n’a été reçu.');
});

it('stocke le fichier d’origine quand le ré-encodage est désactivé, toujours sous un nom ULID', function (): void {
    config(['xomiton.uploads.reencode_images' => false]);
    $source = jpegWithExif(300, 200);

    $media = $this->uploader->upload(UploadedFile::fake()->createWithContent('brut.jpeg', $source), 'images');

    expect($media->mime)->toBe('image/jpeg')
        ->and($media->path)->toMatch('#/[0-9a-z]{26}\.jpg$#')
        ->and([$media->width, $media->height])->toBe([300, 200])
        ->and(Storage::disk('local')->get($media->path))->toBe($source);
});
