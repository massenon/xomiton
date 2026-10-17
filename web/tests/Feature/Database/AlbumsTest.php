<?php

declare(strict_types=1);

use App\Enums\ProfileVisibility;
use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Follow;
use App\Models\User;
use Illuminate\Database\QueryException;

/*
 * Lot 4 « albums » (DATABASE_DICTIONARY.md §4 `albums`, `album_media`) : médiathèque et albums de profil
 * (albums.php, onglet Médias). Tourne avec RefreshDatabase (tests/Pest.php).
 */

describe('albums', function (): void {
    it('crée un album personnalisé avec ULID, visibilité castée et compteur à zéro', function (): void {
        $album = Album::factory()->create(['title' => 'Atelier sororité 2026']);

        expect($album->ulid)->toHaveLength(26)
            ->and($album->kind)->toBe(Album::KIND_CUSTOM)
            ->and($album->isSystem())->toBeFalse()
            ->and($album->visibility)->toBe(ProfileVisibility::Community)
            ->and($album->visibility->label())->toBe('Toute la communauté')
            ->and($album->media_count)->toBe(0)
            ->and($album->cover_media_id)->toBeNull()
            ->and($album->coverMedia())->toBeNull()
            ->and($album->user)->toBeInstanceOf(User::class)
            ->and($album->user->albums()->count())->toBe(1)
            ->and(Album::query()->whereUlid($album->ulid)->exists())->toBeTrue();

        expect(fn () => Album::factory()->create(['ulid' => $album->ulid]))->toThrow(QueryException::class);
    });

    it('distingue les albums système (profil, couverture) des albums personnalisés', function (): void {
        $user = User::factory()->create();
        Album::factory()->for($user)->profilePictures()->create();
        Album::factory()->for($user)->covers()->create();
        Album::factory()->for($user)->create();

        expect(Album::query()->ofKind(Album::KIND_PROFILE)->count())->toBe(1)
            ->and(Album::query()->ofKind(Album::KIND_COVER)->first()?->isSystem())->toBeTrue()
            ->and(Album::query()->custom()->count())->toBe(1)
            ->and(Album::KINDS)->toBe(['profile', 'cover', 'custom']);
    });

    it('applique la visibilité du profil : tout le monde, communauté, abonné·es, privé', function (): void {
        $owner = User::factory()->create();
        $follower = User::factory()->create();
        $stranger = User::factory()->create();

        Album::factory()->for($owner)->visibility(ProfileVisibility::Public)->create();
        Album::factory()->for($owner)->create();
        Album::factory()->for($owner)->visibility(ProfileVisibility::Followers)->create();
        Album::factory()->for($owner)->visibility(ProfileVisibility::Private)->create();
        Follow::factory()->create(['follower_id' => $follower->id, 'followed_id' => $owner->id]);

        expect(Album::query()->visibleTo(null)->count())->toBe(1)
            ->and(Album::query()->visibleTo($stranger)->count())->toBe(2)
            ->and(Album::query()->visibleTo($follower)->count())->toBe(3)
            ->and(Album::query()->visibleTo($owner)->count())->toBe(4);
    });

    it('supprime logiquement un album et le supprime avec son compte', function (): void {
        $album = Album::factory()->withMedia(2)->create();

        $album->delete();

        expect(Album::query()->count())->toBe(0)
            ->and(Album::withTrashed()->count())->toBe(1)
            ->and(AlbumMedia::query()->count())->toBe(2);

        $album->user->forceDelete();

        expect(Album::withTrashed()->count())->toBe(0)
            ->and(AlbumMedia::query()->count())->toBe(0);
    });
});

describe('album_media', function (): void {
    it('ordonne les médias, désigne une couverture et tient le compteur dénormalisé', function (): void {
        $album = Album::factory()->withMedia(3)->create();
        AlbumMedia::factory()->video()->create(['album_id' => $album->id, 'position' => 3]);

        $album->refresh();

        expect($album->media)->toHaveCount(4)
            ->and($album->media->pluck('position')->all())->toBe([0, 1, 2, 3])
            ->and($album->media->first()->isImage())->toBeTrue()
            ->and($album->media->last()->kind)->toBe(AlbumMedia::KIND_VIDEO)
            ->and($album->cover)->toBeInstanceOf(AlbumMedia::class)
            ->and($album->coverMedia()->is($album->media->first()))->toBeTrue()
            ->and($album->media_count)->toBe(3)
            ->and(AlbumMedia::query()->images()->count())->toBe(3)
            ->and(AlbumMedia::query()->ordered()->first()->album->is($album))->toBeTrue();

        $album->syncMediaCount();

        expect($album->fresh()->media_count)->toBe(4);

        $album->forceDelete();

        expect(AlbumMedia::query()->count())->toBe(0);
    });

    it('retombe sur le premier média quand aucune couverture n’est choisie', function (): void {
        $album = Album::factory()->create();
        $second = AlbumMedia::factory()->create(['album_id' => $album->id, 'position' => 1]);
        $first = AlbumMedia::factory()->create(['album_id' => $album->id, 'position' => 0]);

        expect($album->coverMedia()?->is($first))->toBeTrue()
            ->and($second->album->is($album))->toBeTrue();
    });
});
