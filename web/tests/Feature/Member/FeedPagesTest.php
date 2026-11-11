<?php

declare(strict_types=1);

use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Comment;
use App\Models\Event;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostSave;
use App\Models\Thematique;
use App\Models\User;
use App\Models\UserBlock;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\FeedFixtures;

/*
 * Écrans Vue du fil (lot fil-ecrans, P7-03 à P7-06) : props Inertia de `Feed/Index` (/fil : première page sous la forme de
 * l'API, filtre, « À la une », événements, réglages des modales), `Feed/Show` (/fil/{ulid} : publication et première
 * page de commentaires, 404 si invisible) et `Feed/Saved` (/fil/enregistres) ; publication rejouée sans doublon
 * (Idempotency-Key, boîte d'envoi hors connexion).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->reader = FeedFixtures::member();
    $this->author = FeedFixtures::member();
});

/**
 * @param  array<int, array<string, mixed>>  $items
 * @return list<string>
 */
function pageIds(array $items): array
{
    return array_values(array_column($items, 'id'));
}

it('sert /fil en page Feed/Index : première page visible, sous la forme de l’API, plus récentes d’abord', function (): void {
    $older = Post::factory()->for($this->author)->create(['published_at' => now()->subHours(2), 'body' => 'Merci à toutes']);
    $newer = Post::factory()->for($this->author)->tagged(PostTag::Question)->create(['published_at' => now()->subHour()]);
    Post::factory()->for($this->author)->visibility(PostVisibility::Me)->create();
    Post::factory()->for($this->author)->hidden()->create();
    $hiddenByMe = Post::factory()->for($this->author)->create();
    PostHide::query()->create(['post_id' => $hiddenByMe->id, 'user_id' => $this->reader->id]);

    $response = $this->actingAs($this->reader)->get('/fil')
        ->assertOk()
        ->assertSee('<title inertia>Fil d’actualité – Xomiton</title>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index')
            ->where('title', 'Fil d’actualité')
            ->where('tag', null)
            ->where('feed.data', fn ($data): bool => pageIds(collect($data)->all()) === [$newer->ulid, $older->ulid])
            ->where('feed.data.1.body', 'Merci à toutes')
            ->where('feed.data.1.author.pseudo', $this->author->pseudo)
            ->where('feed.data.1.liked', false)
            ->where('feed.data.1.share_url', route('p.show', ['ulid' => $older->ulid]))
            ->where('feed.data.1.url', route('fil.show', ['post' => $older->ulid]))
            ->where('feed.meta.next_cursor', null)
            ->where('feed.meta.per_page', 20)
            ->has('featured')
            ->has('events')
            ->has('composer'));

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('filtre par étiquette (?tag=), ignore une étiquette inconnue et ne recharge que le fil en visite partielle', function (): void {
    $question = Post::factory()->for($this->author)->tagged(PostTag::Question)->create(['published_at' => now()]);
    // Étiquette fixée : celle tirée au hasard par la fabrique pouvait valoir « question » (test instable).
    $testimony = Post::factory()->for($this->author)->tagged(PostTag::Testimony)->create(['published_at' => now()->subMinute()]);

    $this->actingAs($this->reader)->get('/fil?tag=question')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tag', 'question')
            ->where('feed.data', fn ($data): bool => pageIds(collect($data)->all()) === [$question->ulid])
            ->reloadOnly(['feed', 'tag'], fn (Assert $reload) => $reload
                ->has('feed.data', 1)
                ->missing('featured')
                ->missing('events')
                ->missing('composer')));

    $this->actingAs($this->reader)->get('/fil?tag=inconnue')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('tag', null)
            ->where('feed.data', fn ($data): bool => pageIds(collect($data)->all()) === [$question->ulid, $testimony->ulid]));
});

it('donne le curseur de la page suivante, compatible avec GET /api/v1/feed', function (): void {
    config(['xomiton.feed.per_page' => 2]);
    $posts = collect(range(1, 3))->map(fn (int $i) => Post::factory()->for($this->author)->create(['published_at' => now()->subMinutes($i)]));

    $cursor = null;
    $this->actingAs($this->reader)->get('/fil')
        ->assertInertia(function (Assert $page) use (&$cursor, $posts): void {
            $page->where('feed.data', fn ($data): bool => pageIds(collect($data)->all()) === [$posts[0]->ulid, $posts[1]->ulid])
                ->where('feed.meta.next_cursor', function (?string $value) use (&$cursor): bool {
                    $cursor = $value;

                    return is_string($value) && $value !== '';
                });
        });

    $next = FeedFixtures::as($this->reader)->getJson('/api/v1/feed?per_page=2&cursor='.urlencode((string) $cursor))->assertOk()->json('data');
    expect(pageIds($next))->toBe([$posts[2]->ulid]);
});

it('remplit « À la une » et les événements de la colonne droite, sans adresse', function (): void {
    $thematique = Thematique::factory()->create(['title' => 'Consentement et relations']);
    Thematique::factory()->draft()->create();
    $event = Event::factory()->online()->create(['title' => 'Causerie en ligne']);
    Event::factory()->past()->create();

    $this->actingAs($this->reader)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page
            ->has('featured', 1)
            ->where('featured.0.title', 'Consentement et relations')
            ->where('featured.0.href', route('thematiques.show', ['slug' => $thematique->slug]))
            ->where('featured.0.cover_url', fn (string $url): bool => $url !== '')
            ->has('events', 1)
            ->where('events.0.title', 'Causerie en ligne')
            ->where('events.0.href', route('evenements.show', ['event' => $event->ulid]))
            ->where('events.0.meta', fn (string $meta): bool => str_contains($meta, 'En ligne'))
            ->where('events.0', fn ($item): bool => ! array_key_exists('place', collect($item)->all())));
});

it('transmet aux modales les droits et les bornes de StorePostRequest', function (): void {
    $this->actingAs($this->reader)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page
            ->where('composer.can_post', true)
            ->where('composer.can_poll', true)
            // P8-04 : pastille « Événement » active, réglages de la modale « Créer un événement » joints.
            ->where('composer.events_available', true)
            ->where('composer.event.can', true)
            ->where('composer.event.limits.title_min', (int) config('xomiton.events.title_min'))
            ->where('composer.tags', fn ($tags): bool => array_column(collect($tags)->all(), 'value') === ['temoignage', 'question', 'ressource', 'evenement', 'violences'])
            ->where('composer.visibilities', fn ($items): bool => array_column(collect($items)->all(), 'value') === ['public', 'followers', 'me'])
            ->where('composer.durations', fn ($items): bool => array_column(collect($items)->all(), 'value') === [1, 3, 7, 14, 30, 0])
            ->where('composer.default_duration', 7)
            ->where('composer.limits', [
                'body_max' => 3000, 'question_min' => 5, 'question_max' => 200, 'options_min' => 2, 'options_max' => 6,
                'option_max' => 80, 'image_max_kb' => 5120, 'image_types' => ['image/jpeg', 'image/png', 'image/webp'],
            ]));
});

it('sert une publication anonyme d’autrui sans identifier son auteur·rice (RG-02), avec la couleur de son étiquette', function (): void {
    $anonymous = Post::factory()->for($this->author)->anonymous()->tagged(PostTag::Question)->create(['published_at' => now()]);

    $this->actingAs($this->reader)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page
            ->where('feed.data.0.id', $anonymous->ulid)
            ->where('feed.data.0.is_anonymous', true)
            ->where('feed.data.0.is_mine', false)
            ->where('feed.data.0.author.id', null)
            ->where('feed.data.0.author.anonymous', true)
            ->where('feed.data.0.author.pseudo', 'Membre anonyme')
            ->where('feed.data.0.author.role', null)
            ->where('feed.data.0.tag_color', 'warning'))
        ->assertDontSee($this->author->pseudo);
});

it('fournit aux cartes les motifs de signalement de ContentReportReason, sans le motif automatique', function (string $uri): void {
    $post = Post::factory()->for($this->author)->create();
    $expected = ['harcelement', 'discrimination', 'menaces', 'donnees_personnelles', 'desinformation', 'usurpation', 'spam', 'autre'];

    $this->actingAs($this->reader)->get(str_replace('{ulid}', $post->ulid, $uri))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report_reasons', fn ($reasons): bool => array_column(collect($reasons)->all(), 'value') === $expected
                && collect($reasons)->firstWhere('value', 'harcelement')['label'] === __('enums/feed.report_reason.harcelement')));
})->with(['/fil', '/fil/{ulid}', '/fil/enregistres']);

it('affiche la page sans accès au réseau social : fil vide, jamais un 403', function (): void {
    $guest = User::factory()->create();
    Post::factory()->for($this->author)->create();

    $this->actingAs($guest)->get('/fil')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index')
            ->where('feed.data', [])
            ->where('composer.can_post', false));
});

it('sert le détail /fil/{ulid} : publication, première page de commentaires et leur nombre', function (): void {
    $post = Post::factory()->for($this->author)->create(['comments_count' => 2]);
    $first = Comment::factory()->for($post)->for($this->author)->create(['created_at' => now()->subMinutes(5), 'body' => 'Merci pour ce partage']);
    $second = Comment::factory()->for($post)->anonymous()->create(['created_at' => now()->subMinute()]);
    Comment::factory()->for($post)->hidden()->create();

    $this->actingAs($this->reader)->get('/fil/'.$post->ulid)
        ->assertOk()
        ->assertSee('<title inertia>Publication – Xomiton</title>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Show')
            ->where('post.id', $post->ulid)
            ->where('post.can.comment', true)
            ->where('comments.data', fn ($data): bool => pageIds(collect($data)->all()) === [$first->id, $second->id])
            ->where('comments.data.0.body', 'Merci pour ce partage')
            ->where('comments.data.1.author.anonymous', true)
            ->where('comments.data.1.author.id', null)
            ->where('comments.meta.comments_count', 2)
            ->has('events'));
});

it('répond 404 au détail d’une publication invisible, supprimée ou à l’adresse invalide', function (): void {
    $private = Post::factory()->for($this->author)->visibility(PostVisibility::Me)->create();
    $hidden = Post::factory()->for($this->author)->hidden()->create();
    $blocker = FeedFixtures::member();
    $fromBlocker = Post::factory()->for($blocker)->create();
    UserBlock::query()->create(['user_id' => $blocker->id, 'blocked_user_id' => $this->reader->id]);
    $deleted = Post::factory()->for($this->author)->create();
    $deleted->delete();

    foreach ([$private, $hidden, $fromBlocker, $deleted] as $post) {
        $this->actingAs($this->reader)->get('/fil/'.$post->ulid)->assertNotFound();
    }

    $this->actingAs($this->reader)->get('/fil/'.Str::ulid())->assertNotFound();
    $this->actingAs($this->reader)->get('/fil/12')->assertNotFound();

    // L'auteur·rice voit sa publication masquée par la modération (moderated: true).
    $this->actingAs($this->author)->get('/fil/'.$hidden->ulid)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('post.moderated', true));
});

it('liste les publications enregistrées encore visibles sur /fil/enregistres', function (): void {
    $kept = Post::factory()->for($this->author)->create();
    $gone = Post::factory()->for($this->author)->create();
    Post::factory()->for($this->author)->create();
    PostSave::query()->create(['post_id' => $kept->id, 'user_id' => $this->reader->id]);
    PostSave::query()->create(['post_id' => $gone->id, 'user_id' => $this->reader->id]);
    $gone->forceFill(['visibility' => PostVisibility::Me])->save();

    $this->actingAs($this->reader)->get('/fil/enregistres')
        ->assertOk()
        ->assertSee('<title inertia>Publications enregistrées – Xomiton</title>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Saved')
            ->where('title', 'Publications enregistrées')
            ->where('saved.data', fn ($data): bool => pageIds(collect($data)->all()) === [$kept->ulid])
            ->where('saved.data.0.saved', true)
            ->where('saved.meta.next_cursor', null));
});

it('réserve les pages du fil aux personnes connectées', function (string $uri): void {
    $this->get($uri)->assertRedirect(route('connexion'));
})->with(['/fil', '/fil/enregistres', '/fil/01JC0000000000000000000000']);

it('rejoue une publication mise en file hors connexion sans doublon (même Idempotency-Key)', function (): void {
    $key = (string) Str::uuid();
    $payload = ['body' => 'Écrit hors connexion', 'tag' => 'temoignage', 'visibility' => 'public'];

    $first = FeedFixtures::as($this->reader)->withHeaders(['Idempotency-Key' => $key])->postJson('/api/v1/posts', $payload)->assertCreated();
    $replay = FeedFixtures::as($this->reader)->withHeaders(['Idempotency-Key' => $key])->postJson('/api/v1/posts', $payload)->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($replay->json('data.id'))->toBe($first->json('data.id'))
        ->and(Post::query()->where('user_id', $this->reader->id)->count())->toBe(1);
});
