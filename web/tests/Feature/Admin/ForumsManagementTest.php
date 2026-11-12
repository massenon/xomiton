<?php

declare(strict_types=1);

use App\Livewire\Admin\Content\Index as ContentScreen;
use App\Livewire\Admin\Network\Forums as ForumsScreen;
use App\Livewire\Admin\Network\Index as NetworkScreen;
use App\Models\AuditLog;
use App\Models\Forum;
use App\Models\ForumTopic;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * P18-10 : gestion des forums dans l'administration (`forums.manage`) — créer, renommer, réordonner (annonce aria-live),
 * archiver / réactiver ; un forum archivé reste lisible mais n'accepte plus de sujet (API et page membre) ; le seeder ne
 * réécrit plus un forum existant. P18-11 : épingler / verrouiller un sujet (admin et API de modération), mettre en avant
 * une ressource (tête du catalogue) ; journal d'audit sans texte.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ForumsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
    $this->moderator = User::factory()->create();
    $this->moderator->syncRoles(['moderateur']);
});

it('réserve la gestion des forums à forums.manage (admin), jamais au modérateur·rice', function (): void {
    $this->actingAs($this->moderator);
    Livewire::test(ForumsScreen::class)->assertForbidden();
    Livewire::test(NetworkScreen::class)->call('setTab', 'gestion')->assertSet('tab', 'groupes');

    $this->actingAs($this->admin);
    Livewire::test(NetworkScreen::class)->call('setTab', 'gestion')->assertSet('tab', 'gestion')->assertSeeLivewire(ForumsScreen::class);
});

it('crée un forum (clé suivante, position en fin de liste) puis le renomme, journal sans texte', function (): void {
    $this->actingAs($this->admin);

    Livewire::test(ForumsScreen::class)
        ->call('create')
        ->set('form', ['name' => 'Ab', 'description' => '', 'icon' => 'inconnue'])
        ->call('save')
        ->assertHasErrors(['form.name', 'form.description', 'form.icon']);

    Livewire::test(ForumsScreen::class)
        ->call('create')
        ->set('form', ['name' => 'Parents & familles', 'description' => 'Parler avec ses proches.', 'icon' => 'house-heart'])
        ->call('save')
        ->assertHasNoErrors();

    $forum = Forum::query()->where('name', 'Parents & familles')->firstOrFail();
    expect($forum->key)->toBe('f6')
        ->and($forum->icon)->toBe('bi-house-heart')
        ->and($forum->position)->toBe(5)
        ->and(Forum::query()->ordered()->get()->last()->id)->toBe($forum->id);

    Livewire::test(ForumsScreen::class)
        ->call('edit', $forum->id)
        ->assertSet('forumId', $forum->id)
        ->set('form.name', 'Familles')
        ->call('save')
        ->assertHasNoErrors();

    expect($forum->refresh()->name)->toBe('Familles')->and($forum->slug)->toBe('familles')->and($forum->key)->toBe('f6');
    $logs = AuditLog::query()->whereIn('action', ['forums.created', 'forums.updated'])->get();
    expect($logs)->toHaveCount(2)->and($logs->pluck('detail')->implode(' '))->not->toContain('Familles');
});

it('verrouille l’identifiant du forum ouvert (#[Locked])', function (): void {
    $this->actingAs($this->admin);
    $forum = Forum::query()->forKey('f2')->firstOrFail();

    expect(fn () => Livewire::test(ForumsScreen::class)->set('forumId', $forum->id))->toThrow(Exception::class);
});

it('réordonne par Monter / Descendre, annonce la nouvelle position et refuse le bord de liste', function (): void {
    $this->actingAs($this->admin);
    $f2 = Forum::query()->forKey('f2')->firstOrFail();
    $f1 = Forum::query()->forKey('f1')->firstOrFail();

    Livewire::test(ForumsScreen::class)
        ->call('move', $f2->id, -1)
        ->assertSet('announce', fn (string $text): bool => str_replace("\u{00A0}", ' ', $text) === '« Droits & démarches » est maintenant en position 1 sur 5.')
        ->assertDispatched('xm-forum-focus', id: 'xmForumDown'.$f2->id);

    expect(Forum::query()->ordered()->pluck('key')->take(2)->all())->toBe(['f2', 'f1']);

    Livewire::test(ForumsScreen::class)->call('move', $f2->id, -1)->assertSet('announce', '');
    expect($f1->refresh()->position)->toBe(1)
        ->and(AuditLog::query()->where('action', 'forums.moved')->count())->toBe(1);
});

it('archive un forum : listé et lisible, plus de nouveau sujet (API et page), réactivation', function (): void {
    $forum = Forum::query()->forKey('f3')->firstOrFail();
    $topic = ForumTopic::factory()->create(['forum_id' => $forum->id]);
    $this->actingAs($this->admin);

    Livewire::test(ForumsScreen::class)->call('archive', $forum->id, true);
    expect($forum->refresh()->isArchived())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'forums.archived')->count())->toBe(1);

    $member = F::member();
    F::as($member)->getJson('/api/v1/forums')->assertOk()
        ->assertJsonFragment(['key' => 'f3', 'is_archived' => true])
        ->assertJsonFragment(['key' => 'f1', 'is_archived' => false]);
    F::as($member)->getJson('/api/v1/forums/topics/'.$topic->ulid)->assertOk();
    F::as($member)->postJson('/api/v1/forums/topics', ['forum' => 'f3', 'title' => 'Un nouveau sujet', 'body' => 'Un message assez long pour passer.', 'is_anonymous' => false])
        ->assertUnprocessable()->assertJsonValidationErrors('forum');

    $this->actingAs($member)->get('/forums')->assertOk()->assertInertia(fn ($page) => $page
        ->where('create.forums', fn ($options) => collect($options)->pluck('value')->doesntContain('f3')));

    $this->actingAs($this->admin);
    Livewire::test(ForumsScreen::class)->call('archive', $forum->id, false);
    expect($forum->refresh()->isArchived())->toBeFalse();
    F::as($member)->postJson('/api/v1/forums/topics', ['forum' => 'f3', 'title' => 'Un nouveau sujet', 'body' => 'Un message assez long pour passer.', 'is_anonymous' => false])
        ->assertCreated();
});

it('ne réécrit plus un forum existant au rejeu du seeder (choix de l’administration conservés)', function (): void {
    Forum::query()->forKey('f1')->update(['name' => 'Renommé', 'position' => 4, 'is_active' => false]);
    (new ForumsSeeder)->run();

    $forum = Forum::query()->forKey('f1')->firstOrFail();
    expect($forum->name)->toBe('Renommé')->and($forum->position)->toBe(4)->and($forum->is_active)->toBeFalse();
});

it('épingle et verrouille un sujet depuis l’admin (modération), journal sans titre', function (): void {
    $topic = ForumTopic::factory()->create(['title' => 'Titre sensible']);
    $this->actingAs($this->moderator);

    Livewire::test(NetworkScreen::class)->set('tab', 'forums')
        ->call('pin', $topic->id, true)
        ->call('lock', $topic->id, true);

    $topic->refresh();
    expect($topic->is_pinned)->toBeTrue()->and($topic->is_locked)->toBeTrue();
    $logs = AuditLog::query()->whereIn('action', ['forums.topic_pinned', 'forums.topic_locked'])->get();
    expect($logs)->toHaveCount(2)->and($logs->pluck('detail')->filter()->all())->toBe([]);

    Livewire::test(NetworkScreen::class)->set('tab', 'forums')->call('pin', $topic->id, false);
    expect($topic->refresh()->is_pinned)->toBeFalse();
});

it('épingle et verrouille par l’API pour la modération seulement ; un sujet verrouillé refuse les réponses', function (): void {
    $topic = ForumTopic::factory()->create();
    $member = F::member();
    $url = '/api/v1/forums/topics/'.$topic->ulid;

    F::as($member)->postJson($url.'/pin')->assertForbidden();
    F::as($member)->getJson($url)->assertOk()->assertJsonPath('data.can.moderate', false);

    F::as($this->moderator)->postJson($url.'/pin')->assertOk()->assertJsonPath('data.is_pinned', true)->assertJsonPath('data.can.moderate', true);
    F::as($this->moderator)->postJson($url.'/lock')->assertOk()->assertJsonPath('data.is_locked', true)->assertJsonPath('data.can.reply', false);
    F::as($member)->postJson($url.'/replies', ['body' => 'Une réponse', 'is_anonymous' => false])->assertUnprocessable();
    F::as($this->moderator)->deleteJson($url.'/lock')->assertOk()->assertJsonPath('data.is_locked', false);
});

it('met une ressource à la une : en tête du catalogue public et de l’API, journal', function (): void {
    $old = Resource::factory()->create(['title' => 'Guide d’urgence', 'kind' => 'guide', 'status' => 'publie', 'published_at' => now()->subYear()]);
    Resource::factory()->create(['title' => 'Fiche récente', 'kind' => 'fiche', 'status' => 'publie', 'published_at' => now()->subDay()]);
    $this->actingAs($this->admin);

    Livewire::test(ContentScreen::class)->call('featureResource', $old->id);
    expect($old->refresh()->is_featured)->toBeTrue()
        ->and(AuditLog::query()->where('action', 'content.resource_featured')->count())->toBe(1);

    $this->getJson('/api/v1/ressources')->assertOk()->assertJsonPath('data.0.title', 'Guide d’urgence')->assertJsonPath('data.0.is_featured', true);
    auth()->logout();
    $this->get('/ressources')->assertOk()->assertSeeInOrder(['Guide d’urgence', 'Fiche récente'])->assertSee('À la une');
});

it('refuse la mise en avant sans resources.manage', function (): void {
    $resource = Resource::factory()->create();
    $this->actingAs($this->moderator);

    $this->get('/admin/contenu')->assertForbidden();
    expect($resource->refresh()->is_featured)->toBeFalse();
});
