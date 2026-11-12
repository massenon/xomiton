<?php

declare(strict_types=1);

use App\Models\Post;
use App\Models\PostMedia;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FeedFixtures;

/*
 * Q96 : plusieurs images par publication ou sondage (`images[]`, `images_alt[]` alignés, jusqu'à `feed.max_images`),
 * ordre conservé, ré-encodage sans EXIF, ancien champ `image` rétrocompatible (= images[0]), retrait d'images par PATCH.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    $this->author = FeedFixtures::member();
});

function fakePng(string $name, int $red = 30): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, FeedFixtures::png(120, 80, $red));
}

function postMultipart(array $data)
{
    return FeedFixtures::as(test()->author)->post('/api/v1/posts', $data, ['Accept' => 'application/json']);
}

it('publie de une à quatre images, ordre conservé, un texte alternatif par image', function (int $count): void {
    $images = array_map(static fn (int $i): UploadedFile => fakePng("p{$i}.png", 40 * $i), range(1, $count));
    $alts = array_map(static fn (int $i): string => "Image numéro {$i}", range(1, $count));

    $response = postMultipart(['body' => 'Nos photos.', 'images' => $images, 'images_alt' => $alts])->assertCreated();

    $media = $response->json('data.media');
    expect($media)->toHaveCount($count)
        ->and(PostMedia::query()->count())->toBe($count);

    foreach ($media as $index => $item) {
        expect($item['kind'])->toBe('image')
            ->and($item['position'])->toBe($index)
            ->and($item['alt'])->toBe('Image numéro '.($index + 1))
            ->and($item['url'])->toContain('signature=')
            ->and($item['width'])->toBe(120)
            ->and($item['mime'])->toBe('image/webp');
    }

    expect(PostMedia::query()->orderBy('position')->pluck('position')->all())->toBe(range(0, $count - 1));
})->with([1, 2, 3, 4]);

it('refuse plus d’images que le plafond (422) et ne crée rien', function (): void {
    $images = array_map(static fn (int $i): UploadedFile => fakePng("p{$i}.png"), range(1, 5));

    postMultipart(['body' => 'Trop.', 'images' => $images])->assertUnprocessable()->assertJsonValidationErrors('images');

    expect(Post::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
});

it('respecte un plafond configuré et compte l’ancien champ image', function (): void {
    config(['xomiton.feed.max_images' => 2]);

    postMultipart(['body' => 'Trois.', 'image' => fakePng('a.png'), 'images' => [fakePng('b.png'), fakePng('c.png')]])
        ->assertUnprocessable()->assertJsonValidationErrors('images');

    postMultipart(['body' => 'Deux.', 'image' => fakePng('a.png'), 'images' => [fakePng('b.png')]])->assertCreated()
        ->assertJsonCount(2, 'data.media');
});

it('garde l’alignement des textes alternatifs même quand certaines images n’en ont pas', function (): void {
    $response = postMultipart([
        'body' => 'Alt partiel.', 'images' => [fakePng('a.png'), fakePng('b.png', 90), fakePng('c.png', 150)], 'images_alt' => [1 => 'Seconde image'],
    ])->assertCreated();

    expect(array_column($response->json('data.media'), 'alt'))->toBe([null, 'Seconde image', null]);
});

it('refuse un texte alternatif sans image correspondante et un texte alternatif trop long', function (): void {
    postMultipart(['body' => 'x', 'images' => [fakePng('a.png')], 'images_alt' => ['un', 'deux']])
        ->assertUnprocessable()->assertJsonValidationErrors('images_alt');

    postMultipart(['body' => 'x', 'images' => [fakePng('a.png')], 'images_alt' => [str_repeat('a', 256)]])
        ->assertUnprocessable()->assertJsonValidationErrors('images_alt.0');
});

it('accepte une publication sans texte quand elle porte des images', function (): void {
    postMultipart(['images' => [fakePng('a.png'), fakePng('b.png')]])->assertCreated()->assertJsonCount(2, 'data.media');
    postMultipart([])->assertUnprocessable()->assertJsonValidationErrors('body');
});

it('refuse une image de type non autorisé dans le lot (422) et ne garde aucun fichier', function (): void {
    postMultipart(['body' => 'x', 'images' => [fakePng('a.png'), UploadedFile::fake()->createWithContent('b.jpg', "%PDF-1.4\n%%EOF\n")]])
        ->assertUnprocessable()->assertJsonValidationErrors('images.1');

    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('ré-encode chaque image sans EXIF, sur le disque privé seulement', function (): void {
    $files = [
        UploadedFile::fake()->createWithContent('a.jpg', FeedFixtures::jpegWithExif()),
        UploadedFile::fake()->createWithContent('b.jpg', FeedFixtures::jpegWithExif(640, 480)),
    ];

    postMultipart(['body' => 'Deux photos.', 'images' => $files])->assertCreated();

    foreach (PostMedia::query()->get() as $media) {
        $bytes = (string) Storage::disk('local')->get($media->path);
        expect($bytes)->not->toBe('')
            ->and(str_contains($bytes, 'Exif'))->toBeFalse()
            ->and(str_contains($bytes, 'Xomiton Test'))->toBeFalse();
    }

    expect(Storage::disk('public')->allFiles())->toBe([]);
});

it('reste rétrocompatible : l’ancien champ image / image_alt est la première image', function (): void {
    $response = postMultipart(['body' => 'Une seule.', 'image' => fakePng('photo.png'), 'image_alt' => 'Atelier en plein air'])->assertCreated();

    expect($response->json('data.media'))->toHaveCount(1)
        ->and($response->json('data.media.0.alt'))->toBe('Atelier en plein air')
        ->and($response->json('data.media.0.position'))->toBe(0);

    $both = postMultipart(['body' => 'Mixte.', 'image' => fakePng('a.png'), 'image_alt' => 'Première', 'images' => [fakePng('b.png', 99)], 'images_alt' => ['Seconde']])->assertCreated();

    expect(array_column($both->json('data.media'), 'alt'))->toBe(['Première', 'Seconde']);
});

it('publie un sondage avec plusieurs images', function (): void {
    $response = postMultipart([
        'kind' => 'poll', 'poll' => ['question' => 'Quel thème ?', 'options' => ['Santé', 'Droits']],
        'images' => [fakePng('a.png'), fakePng('b.png', 80)], 'images_alt' => ['Affiche', 'Programme'],
    ])->assertCreated();

    expect($response->json('data.tag'))->toBe('sondage')
        ->and(array_column($response->json('data.media'), 'alt'))->toBe(['Affiche', 'Programme'])
        ->and($response->json('data.poll.options'))->toHaveCount(2);
});

it('bloque un terme interdit dans un texte alternatif sans rien téléverser', function (): void {
    FeedFixtures::badWord('grossierete');

    postMultipart(['body' => 'Photos.', 'images' => [fakePng('a.png')], 'images_alt' => ['une grossierete']])
        ->assertUnprocessable()->assertJsonPath('code', 'content_rejected');

    expect(Storage::disk('local')->allFiles())->toBe([])->and(Post::query()->count())->toBe(0);
});

it('retire des images par PATCH remove_media : fichiers supprimés, positions renumérotées, publication « modifiée »', function (): void {
    $created = postMultipart(['body' => 'Trois.', 'images' => [fakePng('a.png'), fakePng('b.png', 90), fakePng('c.png', 160)], 'images_alt' => ['A', 'B', 'C']])->assertCreated();
    $ulid = $created->json('data.id');
    $second = PostMedia::query()->where('position', 1)->sole();
    $secondPath = $second->path;
    expect(Storage::disk('local')->exists($secondPath))->toBeTrue();

    $response = FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$ulid, ['remove_media' => [1]])->assertOk();

    expect(array_column($response->json('data.media'), 'alt'))->toBe(['A', 'C'])
        ->and(array_column($response->json('data.media'), 'position'))->toBe([0, 1])
        ->and($response->json('data.edited'))->toBeTrue()
        ->and(Storage::disk('local')->exists($secondPath))->toBeFalse()
        ->and(PostMedia::query()->count())->toBe(2);
});

it('refuse de retirer une image inconnue ou la dernière d’une publication sans texte', function (): void {
    $created = postMultipart(['images' => [fakePng('a.png')]])->assertCreated();
    $ulid = $created->json('data.id');

    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$ulid, ['remove_media' => [5]])->assertUnprocessable()->assertJsonValidationErrors('remove_media');
    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$ulid, ['remove_media' => [0]])->assertUnprocessable()->assertJsonValidationErrors('remove_media');
    FeedFixtures::as($this->author)->patchJson('/api/v1/posts/'.$ulid, ['body' => 'Un texte', 'remove_media' => [0]])->assertOk()->assertJsonCount(0, 'data.media');
});

it('ne laisse pas une autre personne retirer une image (403)', function (): void {
    $ulid = postMultipart(['body' => 'x', 'images' => [fakePng('a.png')]])->assertCreated()->json('data.id');

    FeedFixtures::as(FeedFixtures::member())->patchJson('/api/v1/posts/'.$ulid, ['remove_media' => [0]])->assertForbidden();
});
