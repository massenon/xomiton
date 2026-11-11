<?php

declare(strict_types=1);

use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Event;
use App\Models\Group;
use App\Models\Page;
use App\Models\Resource;
use App\Models\User;
use App\Support\FrenchTypography;
use App\Support\ReadingTime;
use App\Support\Settings;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoNewsSeeder;
use Illuminate\Support\Carbon;

/*
 * Actualités publiques `/actualites`, `/actualites/{slug}` (P4-10, prototype blog.php, blog-details.php) : actualités =
 * ressources `kind = article` publiées (Q1 a), puces de catégorie, « Charger plus », colonne (événements membres,
 * « Une question ? »), fiche (fil d'Ariane, signature d'équipe, durée de lecture, avertissement, partage, « À lire
 * aussi »), JSON-LD NewsArticle / CollectionPage / BreadcrumbList, redirections, sitemap et actualités de démonstration.
 */

function newsCategory(string $slug, array $attributes = []): Category
{
    return Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => $slug, 'label' => ucfirst($slug), 'color' => 'primary', ...$attributes]);
}

function newsArticle(array $attributes = []): Resource
{
    return Resource::factory()->article()->create($attributes);
}

function newsHtml(string $uri): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** @return list<array<string, mixed>> */
function newsGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

/** @return list<string> titres des cartes, dans l'ordre */
function newsTitles(string $html): array
{
    preg_match_all('~<h3 class="h[46]"><a class="text-reset" href="[^"]+">([^<]+)</a></h3>~', $html, $matches);

    return array_map(static fn (string $title): string => html_entity_decode($title, ENT_QUOTES), $matches[1]);
}

describe('liste /actualites', function (): void {
    it('liste les seules actualités publiées, les plus récentes d’abord, la première à la une', function (): void {
        $category = newsCategory('droits');
        newsArticle(['title' => 'Ancienne', 'category_id' => $category->id, 'published_at' => now()->subDays(9), 'duration_minutes' => 4]);
        newsArticle(['title' => 'Récente', 'category_id' => $category->id, 'published_at' => now()->subDay()]);
        Resource::factory()->article()->draft()->create(['title' => 'Brouillon']);
        Resource::factory()->article()->scheduled()->create(['title' => 'Programmée']);
        Resource::factory()->article()->hidden()->create(['title' => 'Masquée']);
        Resource::factory()->create(['title' => 'Fiche pratique']);

        $html = newsHtml('/actualites');

        expect(newsTitles($html))->toBe(['Récente', 'Ancienne'])
            ->and($html)->toContain('<h1 class="h3 mb-1">'.e(__('pages/actualites.title')).'</h1>')
            ->toContain('<li class="col-12">')->toContain('<li class="col-md-6">')
            ->toContain('fetchpriority="high"')
            ->toContain(e(__('pages/actualites.author_team')))
            ->toContain(e(trans_choice('pages/actualites.minutes', 4)))
            ->toContain('aria-current="page"')
            ->not->toContain('Brouillon')->not->toContain('Programmée')->not->toContain('Masquée')->not->toContain('Fiche pratique');
    });

    it('estime la durée de lecture de la carte comme sur la fiche quand elle n’est pas saisie', function (): void {
        $body = '<p>'.str_repeat('mot ', 401).'</p>';
        newsArticle(['slug' => 'sans-duree', 'title' => 'Sans durée', 'body' => $body, 'duration_minutes' => null]);
        $minutes = ReadingTime::minutes(null, $body);

        expect($minutes)->toBe(3)
            ->and(newsHtml('/actualites'))->toContain(e(trans_choice('pages/actualites.minutes', $minutes)))
            ->and(newsHtml('/actualites/sans-duree'))->toContain(e(trans_choice('pages/actualites.reading_time', $minutes)));
    });

    it('filtre par catégorie (puces des catégories qui ont des actualités) et répond 404 sur un filtre inconnu', function (): void {
        $droits = newsCategory('droits');
        $sante = newsCategory('sante', ['label' => 'Santé']);
        newsCategory('vide');
        $inactive = newsCategory('ancienne', ['is_active' => false]);
        newsArticle(['title' => 'Article droits', 'category_id' => $droits->id]);
        newsArticle(['title' => 'Article santé', 'category_id' => $sante->id]);
        newsArticle(['title' => 'Article sans puce', 'category_id' => $inactive->id]);

        $all = newsHtml('/actualites');
        $filtered = newsHtml('/actualites?cat=sante');

        expect($all)->toContain('href="'.route('actualites.index', ['cat' => 'droits']).'"')
            ->toContain('href="'.route('actualites.index', ['cat' => 'sante']).'"')
            ->not->toContain('cat=vide')->not->toContain('cat=ancienne')
            ->toContain('Article sans puce')->not->toContain('>Ancienne<')
            ->and(newsTitles($filtered))->toBe(['Article santé'])
            ->and($filtered)->toContain('href="'.route('actualites.index', ['cat' => 'sante']).'" aria-current="true"')
            ->not->toContain('<li class="col-12">');

        $this->get('/actualites?cat=inconnue')->assertNotFound();
        $this->get('/actualites?cat=ancienne')->assertNotFound();
        $this->get('/actualites?cat=../x')->assertNotFound();
        $this->get('/actualites?cat[]=droits')->assertNotFound();
        $this->get('/actualites?cat=vide')->assertOk()->assertSee(__('pages/actualites.empty_category'));
    });

    it('charge les actualités par 6 avec un lien serveur, puis annonce la fin de la liste', function (): void {
        foreach (range(1, 8) as $day) {
            newsArticle(['title' => 'Article '.$day, 'published_at' => now()->subDays($day)]);
        }

        $first = newsHtml('/actualites');
        $second = newsHtml('/actualites?n=12');

        expect(newsTitles($first))->toHaveCount(6)
            ->and($first)->toContain('href="'.route('actualites.index', ['n' => 12]).'#liste" rel="nofollow"')
            ->toContain('data-xm-items')->toContain('data-xm-more')
            ->and(newsTitles($second))->toHaveCount(8)
            ->and($second)->toContain(e(trans_choice('pages/actualites.end_of_list', 8)));
    });

    it('affiche un état vide sans actualité publiée', function (): void {
        expect(newsHtml('/actualites'))->toContain(e(__('pages/actualites.empty_none')))->not->toContain('data-xm-items')
            ->toContain('<meta property="og:image:alt" content="'.e(__('pages/actualites.og_alt_index')).'">');
    });

    it('réserve « Prochains événements » aux membres et garde « Une question ? » pour tout le monde', function (): void {
        Event::factory()->create(['title' => 'Atelier à venir', 'starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHours(2)]);
        Event::factory()->past()->create(['title' => 'Atelier passé']);
        Event::factory()->draft()->create(['title' => 'Atelier brouillon']);

        $guest = newsHtml('/actualites');

        expect($guest)->not->toContain('actualites-evenements')->not->toContain('Atelier à venir')
            ->toContain('href="'.route('aide.index').'"')->toContain(e(__('pages/actualites.question_alt')));

        $this->actingAs(User::factory()->create());
        $member = newsHtml('/actualites');
        $event = Event::query()->where('title', 'Atelier à venir')->firstOrFail();

        expect($member)->toContain('href="'.route('evenements.show', $event->ulid).'"')
            ->not->toContain('Atelier passé')->not->toContain('Atelier brouillon');
    });

    it('n’expose ni les événements d’un groupe privé ou d’une page non vérifiée, ni le lieu', function (): void {
        $soon = ['starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHours(2)];
        Event::factory()->create([...$soon, 'title' => 'Atelier ouvert', 'place' => 'Rue des Cocotiers, Cotonou']);
        Event::factory()->forGroup(Group::factory()->private()->create())->create([...$soon, 'title' => 'Cercle privé']);
        Event::factory()->forGroup(Group::factory()->create())->create([...$soon, 'title' => 'Groupe public']);
        Event::factory()->forPage(Page::factory()->create())->create([...$soon, 'title' => 'Page non vérifiée']);
        Event::factory()->forPage(Page::factory()->verified()->create())->create([...$soon, 'title' => 'Page vérifiée']);

        $this->actingAs(User::factory()->create());
        $html = newsHtml('/actualites');

        expect($html)->toContain('Atelier ouvert')->toContain('Groupe public')->toContain('Page vérifiée')
            ->not->toContain('Cercle privé')->not->toContain('Page non vérifiée')->not->toContain('Rue des Cocotiers');
    });

    it('produit titre, description, CollectionPage et fil d’Ariane, canonical sans paramètre', function (): void {
        $category = newsCategory('droits', ['label' => 'Droits']);
        $article = newsArticle(['title' => 'Mes droits', 'category_id' => $category->id]);

        $html = newsHtml('/actualites?cat=droits&n=6');
        $graph = collect(newsGraph($html));
        $collection = $graph->firstWhere('@type', 'CollectionPage');
        $crumbs = $graph->firstWhere('@type', 'BreadcrumbList');

        expect($html)->toContain('<title>'.e(__('seo.pages.actualites.title')).'</title>')
            ->toContain('<link rel="canonical" href="'.route('actualites.index').'">')
            // og:image = couverture du premier article : son alt la décrit (pas l'alt générique de la liste).
            ->toContain('<meta property="og:image:alt" content="'.e(__('pages/actualites.cover_alt', ['title' => 'Mes droits'])).'">')
            ->and($collection['mainEntity']['itemListElement'][0]['url'])->toBe(route('actualites.show', $article->slug))
            ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/actualites.title'), 'Droits']);
    });
});

describe('fiche /actualites/{slug}', function (): void {
    it('affiche l’article : fil d’Ariane, signature, date, durée, corps nettoyé, avertissement, partage', function (): void {
        $category = newsCategory('droits', ['label' => 'Droits']);
        $article = newsArticle([
            'slug' => 'atelier-porto-novo',
            'title' => 'Atelier à Porto-Novo',
            'category_id' => $category->id,
            'summary' => 'Un atelier sur les droits.',
            'body' => '<h2>Comprendre</h2><p>Texte de l’article.</p><script>alert(1)</script>',
            'duration_minutes' => null,
            'published_at' => Carbon::parse('2026-10-09 08:00'),
        ]);

        $html = newsHtml('/actualites/atelier-porto-novo');

        expect($html)->toContain('<h1 class="h3" id="actualite-titre">Atelier à Porto-Novo</h1>')
            ->toContain('<link rel="preload" as="image" href="'.$article->cover_url.'" fetchpriority="high">')
            ->toContain('href="'.route('actualites.index', ['cat' => 'droits']).'"')
            ->toContain(e(__('pages/actualites.author_team')))
            ->toContain('<time datetime="2026-10-09">')
            ->toContain(e(trans_choice('pages/actualites.reading_time', 1)))
            ->toContain('<h2>Comprendre</h2>')->not->toContain('<script>alert(1)</script>')
            ->toContain('href="'.route('sos').'"')->toContain('href="'.route('urgences').'"')
            ->toContain('hidden data-xm-share')
            ->toContain('data-share-url="'.route('actualites.show', 'atelier-porto-novo').'"')
            ->toContain('content="article"');
    });

    it('produit le JSON-LD NewsArticle rattaché à la page et le BreadcrumbList du fil visible', function (): void {
        $category = newsCategory('droits', ['label' => 'Droits']);
        newsArticle(['slug' => 'nouvelle-loi', 'title' => 'Nouvelle loi', 'category_id' => $category->id, 'duration_minutes' => 5, 'published_at' => Carbon::parse('2026-10-01 08:00')]);
        $canonical = route('actualites.show', 'nouvelle-loi');

        $graph = collect(newsGraph(newsHtml('/actualites/nouvelle-loi')));
        $news = $graph->firstWhere('@type', 'NewsArticle');
        $crumbs = $graph->firstWhere('@type', 'BreadcrumbList');
        $root = rtrim((string) config('app.url'), '/').'/';

        expect($news)->toMatchArray([
            '@id' => $canonical.'#article',
            'headline' => 'Nouvelle loi',
            'articleSection' => 'Droits',
            'timeRequired' => 'PT5M',
            'datePublished' => '2026-10-01',
            'inLanguage' => __('seo.in_language'),
            'author' => ['@id' => $root.'#org'],
            'publisher' => ['@id' => $root.'#org'],
            'mainEntityOfPage' => ['@id' => $canonical.'#page'],
        ])
            ->and($graph->firstWhere('@type', 'Article'))->toBeNull()
            ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/actualites.title'), 'Droits', 'Nouvelle loi']);
    });

    it('propose « À lire aussi » : 4 autres actualités récentes, sans la courante', function (): void {
        foreach (range(1, 6) as $day) {
            newsArticle(['slug' => 'article-'.$day, 'title' => 'Article '.$day, 'published_at' => now()->subDays($day)]);
        }

        $html = newsHtml('/actualites/article-1');
        $aside = (string) strstr($html, 'id="actualite-autres"');

        expect(substr_count($aside, '<li class="list-group-item d-flex gap-3">'))->toBe(4)
            ->and($aside)->toContain('Article 2')->toContain('Article 5')->not->toContain('Article 6')
            ->not->toContain('href="'.route('actualites.show', 'article-1').'"');
    });

    it('compte la consultation sans toucher la date de modification', function (): void {
        $article = newsArticle(['slug' => 'comptee', 'views_count' => 3]);
        $updated = $article->updated_at?->toAtomString();

        $this->get('/actualites/comptee')->assertOk();

        expect($article->fresh()->views_count)->toBe(4)->and($article->fresh()->updated_at?->toAtomString())->toBe($updated);
    });

    it('répond 404 pour une actualité non publiée ou inconnue et 301 vers /ressources pour un autre type', function (): void {
        Resource::factory()->article()->draft()->create(['slug' => 'brouillon']);
        Resource::factory()->article()->scheduled()->create(['slug' => 'programmee']);
        Resource::factory()->pdf()->create(['slug' => 'fiche-pdf']);

        $this->get('/actualites/brouillon')->assertNotFound();
        $this->get('/actualites/programmee')->assertNotFound();
        $this->get('/actualites/inconnue')->assertNotFound();
        $this->get('/actualites/Majuscules')->assertNotFound();
        $this->get('/actualites/fiche-pdf')->assertStatus(301)->assertRedirect(route('ressources.show', 'fiche-pdf'));
    });

    it('estime la durée de lecture quand elle n’est pas saisie', function (): void {
        expect(ReadingTime::minutes(7, null))->toBe(7)
            ->and(ReadingTime::minutes(null, null))->toBe(1)
            ->and(ReadingTime::minutes(0, '<p>'.str_repeat('mot ', 401).'</p>'))->toBe(3);
    });
});

describe('données semées, redirections et sitemap', function (): void {
    it('sème six actualités de démonstration depuis les fiches thématiques, idempotentes, hors production seulement', function (): void {
        $this->seed(ContentSeeder::class);
        $this->seed(DemoNewsSeeder::class);
        $this->seed(DemoNewsSeeder::class);

        $articles = Resource::query()->news()->published()->get();

        expect($articles)->toHaveCount(6)
            ->and($articles->every(fn (Resource $article): bool => $article->thematique_id !== null && $article->category_id !== null && str_contains((string) $article->body, '<h2>')))->toBeTrue();

        foreach ($articles as $article) {
            expect(mb_strlen((string) $article->meta_title))->toBeLessThanOrEqual(50)
                ->and(mb_strlen((string) $article->meta_description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160);
        }

        expect(newsTitles(newsHtml('/actualites')))->toHaveCount(6);

        Resource::query()->forceDelete();
        $this->app->detectEnvironment(static fn (): string => 'production');
        app(DemoNewsSeeder::class)->run();

        expect(Resource::query()->count())->toBe(0);
    });

    it('redirige les anciennes adresses du prototype vers la liste en 301', function (): void {
        $this->get('/blog.php?cat=Droits')->assertStatus(301)->assertRedirect(route('actualites.index'));
        $this->get('/blog-details.php?id=2')->assertStatus(301)->assertRedirect(route('actualites.index'));
    });

    it('fait apparaître la liste et les actualités publiées dans le sitemap, sans brouillon ni ressource', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);
        $published = newsArticle(['slug' => 'actualite-publiee']);
        Resource::factory()->article()->draft()->create(['slug' => 'actualite-brouillon']);
        Resource::factory()->create(['slug' => 'fiche-pratique']);
        $root = rtrim((string) config('app.url'), '/');

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        expect($xml)->toContain('<loc>'.$root.'/actualites</loc>')
            ->toContain('<loc>'.$root.'/actualites/actualite-publiee</loc>')
            ->toContain((string) $published->updated_at?->toAtomString())
            ->not->toContain('actualite-brouillon')
            ->not->toContain('/actualites/fiche-pratique')
            ->not->toContain('?cat=')->not->toContain('?n=');
    });

    it('vouvoie et respecte la typographie française dans les textes de la page', function (): void {
        $lines = collect((array) trans('pages/actualites'))->flatten()->map(static fn (mixed $line): string => (string) $line);

        foreach ($lines as $line) {
            expect($line)->not->toMatch('/\b(?:tu|ton|ta|tes|toi)\b/u')
                ->not->toMatch('/ [?!:;](?![a-z])/u')
                ->not->toContain("'");
        }

        expect(__('pages/actualites.question_title'))->toBe('Une question'.FrenchTypography::NBSP.'?')
            ->and(ResourceKind::Article->value)->toBe('article');
    });
});
