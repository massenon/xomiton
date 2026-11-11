<?php

declare(strict_types=1);

use App\Enums\PostStatus;
use App\Models\Forum;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\User;
use Database\Seeders\ForumsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures as F;

/*
 * Écrans Vue des forums (P8-05 ; prototype forums.php, forum-topic.php) : pages Inertia `Forums/Index` et
 * `Forums/Show` (plus l'écran provisoire), premières pages sous la forme de l'API (docs/API.md §16), lecture tolérante
 * (forum inconnu → « Tous les forums »), vue comptée une fois par session (forum-topic.php l.46), sujet invisible → 404.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(ForumsSeeder::class);
    $this->awa = F::member();
    $this->bea = F::member();
    $this->forum = Forum::query()->forKey('f2')->firstOrFail();
});

it('sert /forums : les 5 forums, sujets récents filtrés, réglages de la modale « Nouveau sujet »', function (): void {
    ForumTopic::factory()->for($this->awa)->create(['forum_id' => $this->forum->id, 'title' => 'Porter plainte à Cotonou']);
    ForumTopic::factory()->for($this->awa)->anonymous()->create(['forum_id' => Forum::query()->forKey('f1')->value('id')]);

    $this->actingAs($this->bea)->get('/forums?forum=f2')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forums/Index')
            ->where('title', 'Forums')
            ->where('forum', 'f2')
            ->where('q', null)
            ->where('search_max', 60)
            ->has('forums', 5)
            ->where('forums.1.name', 'Droits & démarches')
            ->has('topics.data', 1)
            ->where('topics.data.0.title', 'Porter plainte à Cotonou')
            ->where('topics.meta.last_page', 1)
            ->where('create.can', true)
            ->has('create.forums', 5)
            ->where('create.forums.0', ['value' => 'f1', 'label' => 'Parler librement'])
            ->where('create.limits', ['title_min' => 5, 'title_max' => 140, 'body_min' => 10, 'body_max' => 5000])
            ->has('events')
            ->missing('prototype'));
});

it('revient à « Tous les forums » pour un forum inconnu et coupe une recherche trop longue', function (): void {
    ForumTopic::factory()->for($this->awa)->create(['forum_id' => $this->forum->id, 'title' => 'Un sujet']);

    $this->actingAs($this->bea)->get('/forums?forum=zz&q='.str_repeat('a', 80))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forums/Index')
            ->where('forum', null)
            ->where('q', str_repeat('a', 60))
            ->has('topics.data', 0));
});

it('sert /forums sans forum, sujet ni création pour un compte sans accès au réseau (l’API répond 403)', function (): void {
    ForumTopic::factory()->for($this->awa)->create(['forum_id' => $this->forum->id]);

    $this->actingAs(User::factory()->create())->get('/forums')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Forums/Index')
            ->has('forums', 0)
            ->has('topics.data', 0)
            ->where('create.can', false)
            ->has('create.forums', 0));
});

it('sert la fiche d’un sujet : message, réponses, réglages de la réponse, motifs de signalement ; vue comptée une fois par session', function (): void {
    $topic = ForumTopic::factory()->for($this->awa)->create(['forum_id' => $this->forum->id, 'title' => 'Porter plainte', 'body' => "Ligne 1\nLigne 2", 'views_count' => 4]);
    ForumReply::factory()->forTopic($topic)->for($this->bea)->anonymous()->create(['body' => 'Va au commissariat avec une amie.']);

    $this->actingAs($this->bea)->get('/forums/'.$topic->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forums/Show')
            ->where('title', 'Porter plainte')
            ->where('topic.id', $topic->ulid)
            ->where('topic.body', "Ligne 1\nLigne 2")
            ->where('topic.forum.key', 'f2')
            ->where('topic.views_count', 5)
            ->where('topic.can.reply', true)
            ->has('replies.data', 1)
            ->where('replies.data.0.author.pseudo', 'Membre anonyme')
            ->where('replies.data.0.author.id', null)
            ->where('reply.limits', ['min' => 2, 'max' => 2000])
            ->has('report_reasons')
            ->has('events'));

    $this->actingAs($this->bea)->get('/forums/'.$topic->ulid)->assertOk();
    expect($topic->refresh()->views_count)->toBe(5);

    $this->actingAs($this->awa)->get('/forums/'.$topic->ulid)->assertOk();
    expect($topic->refresh()->views_count)->toBe(5);
});

it('rend 404 pour un sujet masqué, inconnu ou à l’ancienne adresse numérique', function (): void {
    $hidden = ForumTopic::factory()->for($this->awa)->create(['forum_id' => $this->forum->id, 'status' => PostStatus::Hidden]);

    $this->actingAs($this->bea)->get('/forums/'.$hidden->ulid)->assertNotFound();
    $this->actingAs($this->bea)->get('/forums/01JC0000000000000000000000')->assertNotFound();
    $this->actingAs($this->bea)->get('/forums/7')->assertNotFound();
    $this->actingAs($this->awa)->get('/forums/'.$hidden->ulid)->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('topic.moderated', true));
});

it('réserve les forums aux comptes connectés', function (): void {
    $this->get('/forums')->assertRedirect();
});
