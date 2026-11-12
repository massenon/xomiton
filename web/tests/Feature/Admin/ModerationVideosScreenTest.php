<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Livewire\Admin\Moderation\Videos as VideosScreen;
use App\Models\AuditLog;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Notifications\VideoReviewed;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\FeedFixtures;

/*
 * Q96 : « Vidéos à relire » /admin/moderation/videos (Livewire) — `moderation.manage` (403 pair et membre), file des vidéos
 * en attente, lecteur HTML5 natif, description, auteur·rice, date ; Approuver / Refuser (motif obligatoire, texte libre ≤ 300),
 * permission revérifiée à chaque action (même après l'ouverture), identifiant verrouillé (#[Locked]), jamais sa propre vidéo,
 * journal d'audit sans contenu.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('local');
    $this->mod = User::factory()->create();
    $this->mod->syncRoles(['moderateur']);
});

function reviewable(array $post = [], string $description = 'Une femme montre comment installer un filtre à eau.'): Post
{
    $author = FeedFixtures::member();
    $created = Post::factory()->awaitingReview()->create($post + ['user_id' => $author->id, 'body' => 'Mon atelier du samedi']);
    $path = 'video/2026/12/'.strtolower((string) Str::ulid()).'.mp4';
    Storage::disk('local')->put($path, FeedFixtures::mp4(2048));
    PostMedia::factory()->video()->create(['post_id' => $created->id, 'path' => $path, 'mime' => 'video/mp4', 'alt' => $description, 'size' => 2048, 'position' => 0]);

    return $created;
}

function videosScreen(?User $user = null)
{
    test()->actingAs($user ?? test()->mod);

    return Livewire::test(VideosScreen::class);
}

it('sert l’écran à la modération et à l’administration, le refuse au pair et au membre (403)', function (): void {
    foreach (['admin', 'moderateur', 'superadmin'] as $role) {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user)->get(route('admin.moderation.videos'))->assertOk()->assertSeeLivewire(VideosScreen::class)->assertSee('Vidéos à relire');
    }

    foreach (['pair', 'membre'] as $role) {
        $user = User::factory()->create();
        $user->syncRoles([$role]);
        $this->actingAs($user)->get(route('admin.moderation.videos'))->assertForbidden();
        Livewire::actingAs($user)->test(VideosScreen::class)->assertForbidden();
    }
});

it('redirige vers la connexion sans compte connecté', function (): void {
    $this->get(route('admin.moderation.videos'))->assertRedirect();
});

it('liste les vidéos en attente avec lecteur natif, description, auteur·rice et date', function (): void {
    $post = reviewable();
    $published = Post::factory()->create();
    $rejected = Post::factory()->rejected()->create();

    $html = videosScreen()->assertOk()
        ->assertSee('Une femme montre comment installer un filtre à eau.')
        ->assertSee($post->user->pseudo)
        ->assertSee('Mon atelier du samedi')
        ->assertSee('Approuver')
        ->assertSee('Refuser')
        ->assertDontSee($rejected->user->pseudo)
        ->html();

    expect($html)->toContain('<video')->toContain('controls')->toContain('/media/fil/'.$post->ulid.'/video')->toContain('signature=')
        ->and($html)->not->toContain('wire:key="video-'.$published->id.'"');
});

it('affiche l’état vide et ne laisse aucune clé de traduction brute', function (): void {
    $empty = videosScreen()->assertSee('Aucune vidéo à relire')->html();
    reviewable();
    $full = videosScreen()->call('openDecision', Post::query()->value('id'), 'reject')->html();

    foreach ([$empty, $full] as $html) {
        expect($html)->not->toMatch('/\b(admin\.moderation\.(tabs|video|errors)|feed\.video|notifications\.video)\.[a-z_.]+/');
    }
});

it('approuve : publiée, auteur·rice prévenu·e, journal sans contenu, retirée de la file', function (): void {
    Notification::fake();
    $post = reviewable();

    videosScreen()
        ->call('openDecision', $post->id, 'approve')
        ->assertSet('postId', $post->id)
        ->assertSet('decision', 'approve')
        ->call('approve')
        ->assertSet('postId', null)
        ->assertDispatched('toast');

    $post->refresh();
    expect($post->currentStatus())->toBe(PostStatus::Published)->and($post->reviewed_by)->toBe($this->mod->id);
    Notification::assertSentTo($post->user, VideoReviewed::class, static fn (VideoReviewed $n): bool => $n->decision === VideoReviewed::APPROVED);

    $log = AuditLog::query()->where('action', 'post.video_approved')->sole();
    expect(json_encode($log->getAttributes()))->not->toContain('filtre à eau')->not->toContain('atelier');
    videosScreen()->assertSee('Aucune vidéo à relire');
});

it('refuse avec un motif obligatoire, un texte libre ≤ 300 et la notification de l’auteur·rice', function (): void {
    Notification::fake();
    $post = reviewable();

    videosScreen()
        ->call('openDecision', $post->id, 'reject')
        ->call('reject')
        ->assertHasErrors(['reasonCode'])
        ->set('reasonCode', 'inconnu')->call('reject')->assertHasErrors(['reasonCode'])
        ->set('reasonCode', 'other')->set('note', '')->call('reject')->assertHasErrors(['note'])
        ->set('reasonCode', 'privacy')->set('note', str_repeat('a', 301))->call('reject')->assertHasErrors(['note']);

    expect($post->fresh()->currentStatus())->toBe(PostStatus::Pending);

    videosScreen()
        ->call('openDecision', $post->id, 'reject')
        ->set('reasonCode', 'privacy')->set('note', 'On voit l’adresse de ta maison.')
        ->call('reject')
        ->assertHasNoErrors()
        ->assertDispatched('toast');

    $post->refresh();
    expect($post->currentStatus())->toBe(PostStatus::Rejected)->and($post->review_reason)->toBe('privacy')->and($post->review_note)->toBe('On voit l’adresse de ta maison.');
    Notification::assertSentTo($post->user, VideoReviewed::class, static fn (VideoReviewed $n): bool => $n->decision === VideoReviewed::REJECTED && str_contains((string) $n->reason, 'adresse'));

    $log = AuditLog::query()->where('action', 'post.video_rejected')->sole();
    expect(json_encode($log->getAttributes()))->toContain('privacy')->not->toContain('adresse')->not->toContain('filtre');
});

it('verrouille l’identifiant et la décision : impossible de les modifier depuis le navigateur', function (): void {
    $post = reviewable();
    $other = reviewable();

    expect(fn () => videosScreen()->call('openDecision', $post->id, 'approve')->set('postId', $other->id))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
    expect(fn () => videosScreen()->call('openDecision', $post->id, 'approve')->set('decision', 'reject'))
        ->toThrow(\Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException::class);
});

it('rejoue la permission à chaque action : retirée entre l’ouverture et la décision, tout échoue (403)', function (): void {
    $post = reviewable();
    $component = videosScreen()->call('openDecision', $post->id, 'approve');

    $this->mod->syncRoles(['membre']);
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $component->call('approve')->assertForbidden();
    expect($post->fresh()->currentStatus())->toBe(PostStatus::Pending);
});

it('refuse l’ouverture d’une décision à qui n’a pas la permission, et pour sa propre vidéo', function (): void {
    $own = reviewable(['user_id' => $this->mod->id]);
    $other = reviewable();

    videosScreen()->assertSee('Votre propre vidéo')->call('openDecision', $own->id, 'approve')->assertForbidden();

    $member = FeedFixtures::member();
    Livewire::actingAs($member)->test(VideosScreen::class)->assertForbidden();
    expect($other->fresh()->currentStatus())->toBe(PostStatus::Pending);
});

it('signale une vidéo déjà décidée par quelqu’un d’autre sans rien modifier de plus', function (): void {
    Notification::fake();
    $post = reviewable();
    $component = videosScreen()->call('openDecision', $post->id, 'approve');

    $second = User::factory()->create();
    $second->syncRoles(['moderateur']);
    app(App\Application\Feed\ReviewVideo::class)->reject($post, $second, 'off_topic');

    $component->call('approve')->assertDispatched('toast');
    expect($post->fresh()->currentStatus())->toBe(PostStatus::Rejected);
    Notification::assertSentToTimes($post->user, VideoReviewed::class, 1);
});

it('lie l’écran aux signalements par des onglets et ne montre aucune vidéo dans le Réseau social', function (): void {
    $post = reviewable();

    $this->actingAs($this->mod)->get(route('admin.moderation'))->assertOk()->assertSee('Vidéos à relire')->assertSee('en attente');
    Livewire::actingAs($this->mod)->test(App\Livewire\Admin\Network\Index::class)->assertDontSee('Mon atelier du samedi');
    expect($post->fresh()->currentStatus())->toBe(PostStatus::Pending);
});
