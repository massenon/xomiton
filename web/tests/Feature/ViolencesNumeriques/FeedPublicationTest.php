<?php

declare(strict_types=1);

use App\Application\Content\ViolencesNumeriques\OfficialAccount;
use App\Enums\PostStatus;
use App\Enums\PostVisibility;
use App\Models\CccTool;
use App\Models\Post;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Tests\Support\FeedFixtures;

/*
 * P19-11 (partiel) : chaque BD publique du pilote est publiée UNE fois dans le fil social, comme une actualité, depuis le
 * compte officiel (texte de partage, 4 planches avec leur texte alternatif, lien vers /bd/{key}, visibilité publique),
 * sans notification ; rejouable sans doublon ; visible dans GET /api/v1/feed (web et application).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('publie chaque BD une seule fois depuis le compte officiel, avec 4 images et le lien de la BD, sans notification', function (): void {
    Notification::fake();

    $this->artisan('xomiton:violences-numeriques:import', ['--publish' => true])->assertSuccessful();
    $this->artisan('xomiton:violences-numeriques:import', ['--publish' => true])->assertSuccessful();

    $official = app(OfficialAccount::class)->find();
    expect($official)->not->toBeNull()
        ->and($official->getRoleNames()->all())->toBe([])
        ->and($official->email)->toEndWith('.invalid');

    expect(Post::query()->count())->toBe(23);
    $tool = CccTool::query()->where('key', 'vn02')->firstOrFail();
    $post = Post::query()->with('media')->findOrFail($tool->feed_post_id);

    expect($post->user_id)->toBe($official->id)
        ->and($post->visibility)->toBe(PostVisibility::Public)
        ->and($post->status)->toBe(PostStatus::Published)
        ->and($post->body)->toContain('Je ne paierai pas')->toEndWith(route('bd.show', ['key' => 'vn02']))
        ->and($post->media)->toHaveCount(4);
    expect($post->media->pluck('alt')->all())->toBe(array_column($tool->pages, 'alt'))
        ->and($post->media->pluck('path')->all())->toBe(array_column($tool->pages, 'image'))
        ->and($post->media->first()->width)->toBe(1200);

    Notification::assertNothingSent();
});

it('ne republie pas une BD dont la publication a été supprimée, ni une BD non publique', function (): void {
    $this->artisan('xomiton:violences-numeriques:import')->assertSuccessful();
    CccTool::query()->where('key', 'vn03')->update(['is_public' => false]);

    $this->artisan('xomiton:violences-numeriques:import', ['--publish' => true])->assertSuccessful();
    expect(Post::query()->count())->toBe(22);

    Post::query()->findOrFail(CccTool::query()->where('key', 'vn01')->value('feed_post_id'))->delete();
    $this->artisan('xomiton:violences-numeriques:import', ['--publish' => true])->assertSuccessful();

    expect(Post::query()->withTrashed()->count())->toBe(22)
        ->and(CccTool::query()->where('key', 'vn03')->value('feed_post_id'))->toBeNull();
});

it('montre la publication dans le fil d’un membre avec ses 4 images et leur texte alternatif', function (): void {
    $this->artisan('xomiton:violences-numeriques:import', ['--publish' => true])->assertSuccessful();
    // 23 BD publiées : la plus récente est en tête du fil (la première page de 20 ne contient pas forcément vn01).
    $ulid = Post::query()->orderByDesc('id')->firstOrFail()->ulid;

    $item = collect(FeedFixtures::as(FeedFixtures::member())->getJson('/api/v1/feed?per_page=20')->assertOk()->json('data'))
        ->firstWhere('id', $ulid);

    expect($item)->not->toBeNull()
        ->and($item['author']['pseudo'])->toBe('xomiton')
        ->and($item['media'])->toHaveCount(4)
        ->and(collect($item['media'])->every(fn (array $m): bool => str_contains((string) $m['url'], '/assets/images/violences-numeriques/') && $m['alt'] !== ''))->toBeTrue();
});
