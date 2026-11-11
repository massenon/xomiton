<?php

declare(strict_types=1);

use App\Enums\ContentStatus;
use App\Enums\PostStatus;
use App\Livewire\Admin\Network\Index as NetworkScreen;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\ForumTopic;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
 * P12-05 : Réseau social /admin/reseau (Livewire) — `posts.moderate` (modérateur·rice, admin, superadmin ; 403 pour le
 * membre et le pair), onglets Groupes · Pages · Événements · Forums · Publications, masquer / rendre visible / supprimer,
 * approuver et mettre en avant. Done-when du plan : une page d'organisation non approuvée est invisible du public.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

function networkUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function networkScreen(string $role = 'admin', string $tab = 'groupes')
{
    test()->actingAs(networkUser($role));

    return Livewire::test(NetworkScreen::class)->call('setTab', $tab);
}

it('réserve /admin/reseau à la modération : 403 pour le pair et le membre, 200 pour le modérateur et l’admin', function (): void {
    foreach (['pair', 'membre'] as $role) {
        $this->actingAs(networkUser($role))->get('/admin/reseau')->assertForbidden();
    }

    foreach (['moderateur', 'admin', 'superadmin'] as $role) {
        $this->actingAs(networkUser($role))->get('/admin/reseau')->assertOk()->assertSee('Réseau social');
    }
});

it('rend une page d’organisation visible du public seulement une fois approuvée', function (): void {
    $owner = User::factory()->create();
    $page = Page::factory()->pending()->create(['owner_id' => $owner->id, 'name' => 'Association Aube']);
    $visitor = networkUser('membre');

    expect(Page::query()->visibleTo($visitor)->whereKey($page->id)->exists())->toBeFalse();
    $this->actingAs($visitor)->get(route('pages.show', $page->ulid))->assertNotFound();

    $screen = networkScreen('moderateur', 'pages')->assertSee('Association Aube')->assertSee('En attente');
    $screen->call('approve', $page->id, true);

    $page->refresh();
    expect($page->is_approved)->toBeTrue()->and($page->approved_at)->not->toBeNull()->and($page->approved_by)->not->toBeNull()
        ->and(Page::query()->visibleTo($visitor)->whereKey($page->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'network.page_approved')->exists())->toBeTrue();
    $this->actingAs($visitor)->get(route('pages.show', $page->ulid))->assertOk();

    $this->actingAs(networkUser('moderateur'));
    Livewire::test(NetworkScreen::class)->call('setTab', 'pages')->call('approve', $page->id, false);
    expect(Page::query()->visibleTo($visitor)->whereKey($page->id)->exists())->toBeFalse();
});

it('met une page et un groupe en avant : le groupe passe en tête de sa liste', function (): void {
    $viewer = networkUser('membre');
    $plain = Group::factory()->create(['name' => 'Groupe très suivi', 'members_count' => 500]);
    $chosen = Group::factory()->create(['name' => 'Groupe choisi', 'members_count' => 3]);
    $page = Page::factory()->create();

    $screen = networkScreen('admin', 'groupes')->call('feature', 'group', $chosen->id);
    expect($chosen->fresh()->is_featured)->toBeTrue();

    $ids = app(App\Application\Community\Groups\GroupDirectory::class)->query($viewer, App\Application\Community\Groups\GroupDirectory::TAB_DISCOVER)->pluck('groups.id')->all();
    expect($ids[0])->toBe($chosen->id)->and($ids)->toContain($plain->id);

    $screen->call('feature', 'group', $chosen->id);
    expect($chosen->fresh()->is_featured)->toBeFalse();

    $screen->call('setTab', 'pages')->call('feature', 'page', $page->id);
    expect($page->fresh()->is_featured)->toBeTrue()->and(AuditLog::query()->where('action', 'network.page_featured')->exists())->toBeTrue();
});

it('masque puis rend visible un groupe, un événement, un sujet de forum et une publication, et supprime', function (): void {
    $group = Group::factory()->create();
    $event = Event::factory()->create();
    $topic = ForumTopic::factory()->create();
    $post = Post::factory()->create();

    $screen = networkScreen('moderateur');
    $screen->call('hide', 'group', $group->id);
    expect($group->fresh()->is_active)->toBeFalse();
    $screen->call('show', 'group', $group->id);
    expect($group->fresh()->is_active)->toBeTrue();

    $screen->call('hide', 'event', $event->id);
    expect($event->fresh()->status)->toBe(ContentStatus::Hidden)->and(Event::query()->published()->whereKey($event->id)->exists())->toBeFalse();
    $screen->call('show', 'event', $event->id);
    expect($event->fresh()->status)->toBe(ContentStatus::Published);

    $screen->call('hide', 'topic', $topic->id);
    expect($topic->fresh()->status)->toBe(PostStatus::Hidden);
    $screen->call('show', 'topic', $topic->id);
    expect($topic->fresh()->status)->toBe(PostStatus::Published);

    $screen->call('hide', 'post', $post->id);
    expect($post->fresh()->status)->toBe(PostStatus::Hidden);
    $screen->call('show', 'post', $post->id);
    expect($post->fresh()->status)->toBe(PostStatus::Published);

    $screen->call('remove', 'post', $post->id);
    expect(Post::query()->whereKey($post->id)->exists())->toBeFalse();
    $screen->call('remove', 'topic', $topic->id);
    expect(ForumTopic::query()->whereKey($topic->id)->exists())->toBeFalse();
    $screen->call('remove', 'event', $event->id);
    expect(Event::query()->whereKey($event->id)->exists())->toBeFalse();
    $screen->call('remove', 'group', $group->id);
    expect(Group::query()->whereKey($group->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'network.group_deleted')->exists())->toBeTrue();
});

it('refuse les actions à un compte sans la permission et un type inconnu', function (): void {
    $group = Group::factory()->create();
    $this->actingAs(networkUser('membre'));
    Livewire::test(NetworkScreen::class)->assertForbidden();

    networkScreen('admin')->call('hide', 'inconnu', $group->id)->assertNotFound();
});

it('n’affiche jamais l’auteur·rice d’une publication anonyme ni de texte chiffré, et filtre par état', function (): void {
    $author = User::factory()->create(['pseudo' => 'Secrète_Eve']);
    Post::factory()->anonymous()->create(['user_id' => $author->id, 'body' => 'Texte anonyme visible seulement ici']);
    Post::factory()->hidden()->create(['body' => 'Publication masquée test']);

    $screen = networkScreen('moderateur', 'publications')->assertSee('Texte anonyme visible seulement ici')->assertDontSee('Secrète_Eve');
    $screen->set('state', 'hidden')->assertSee('Publication masquée test')->assertDontSee('Texte anonyme visible seulement ici');
    $screen->set('state', '')->set('search', 'anonyme')->assertSee('Texte anonyme')->assertDontSee('Publication masquée test');
});
