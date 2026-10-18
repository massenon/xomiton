<?php

declare(strict_types=1);

use App\Application\Content\Queries\ViolencesPage;
use App\Application\Shared\Referentials;
use App\Models\Category;
use App\Models\Law;
use App\Models\Thematique;
use App\Models\User;
use App\Models\ViolenceType;
use App\Support\FrenchTypography;
use App\Support\Settings;
use Database\Seeders\ContentSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

/*
 * Pages Apprendre (P4-04) : `/violences` (formes depuis violence_types, cycle, que faire, loi, auto-évaluation
 * confidentielle sans formulaire ni envoi), `/lois` (table `laws`, version simple, recherche et catégorie dans le
 * navigateur, liens officiels sûrs), `/do-glegbe` (contenu du prototype, fiches de la catégorie Do Glegbe, quiz avec
 * corrigé lisible sans JavaScript). Publiques et indexables (Q19 a), vous, SEO, sitemap et redirections du prototype.
 */

function cprHtml(string $uri): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** Contenu de <main> (sans l'en-tête, le pied de page ni l'offcanvas). */
function cprMain(string $html): string
{
    preg_match('~<main id="contenu"[^>]*>(.*)</main>~s', $html, $matches);

    return $matches[1] ?? '';
}

/** Section HTML ouverte par `$start` jusqu'à sa balise fermante `</section>` de même niveau (pas d'imbrication ici). */
function cprSection(string $html, string $start): string
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse();
    $to = strpos($html, '</section>', (int) $from);

    return substr($html, (int) $from, (int) $to - (int) $from);
}

/** @return list<array<string, mixed>> */
function cprGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

describe('/violences', function (): void {
    beforeEach(function (): void {
        $this->seed(ReferenceDataSeeder::class);
    });

    it('affiche les formes actives du référentiel avec leurs signes, dans l’ordre', function (): void {
        ViolenceType::query()->where('slug', 'economique')->update(['is_active' => false]);
        ViolenceType::query()->where('slug', 'numerique')->update(['label' => 'Violence numérique administrée']);
        ViolenceType::factory()->create(['slug' => 'spirituelle', 'label' => 'Violence spirituelle', 'icon' => 'bi-moon', 'position' => 99]);
        app(Referentials::class)->invalidate();

        $main = cprMain(cprHtml('/violences'));
        preg_match_all('~<h3 class="h6 mb-0" id="forme-([a-z]+)">([^<]+)</h3>~', $main, $forms);

        expect($forms[1])->toBe(['physique', 'psychologique', 'sexuelle', 'numerique', 'spirituelle'])
            ->and($forms[2])->toContain('Violence numérique administrée')
            ->and($main)->toContain(e(trans_choice('pages/violences.forms.title', 5)))
            ->toContain('Coups, brûlures, séquestration, agressions.')
            ->toContain(e(__('pages/violences.forms.signs.physique')[0]))
            ->toContain('bi-hand-index-thumb')->toContain('bi-moon')
            ->not->toContain('Violence économique')
            // Type sans signes dans lang : liste omise, carte gardée.
            ->and(cprSection($main, '<article class="card card-body h-100" aria-labelledby="forme-spirituelle">'))
            ->not->toContain(__('pages/violences.forms.signs_title'));
    });

    it('sert le héro, le cycle, que faire et la loi, en vous, avec SOS, Signaler et Assistant', function (): void {
        $html = cprHtml('/violences');
        $main = cprMain($html);

        expect($main)->toContain('<h1 class="h3" id="violences-titre">'.e(__('pages/violences.title')).'</h1>')
            ->toContain('class="btn btn-danger" href="'.route('sos').'"')
            ->toContain('class="btn btn-primary" href="'.route('signaler').'"')
            ->toContain('data-bs-target="#offcanvasChat"')
            ->toContain('href="'.route('urgences').'#urgences-contacts"')
            ->toContain('href="'.route('urgences').'#plan"')
            ->toContain('href="'.route('lois').'"')
            ->toContain('<strong>'.e(__('pages/violences.law.law_name')).'</strong>')
            ->not->toContain('href="'.route('annuaire').'"')
            ->and(substr_count($main, 'class="xm-cycle__step'))->toBe(4)
            ->and(substr_count($main, 'xm-cycle__arrow'))->toBe(3)
            ->and($html)->toContain('id="offcanvasChat"')
            ->toContain('<link rel="preload" as="image" href="'.asset('assets/images/xomiton/hero-13.jpg').'" fetchpriority="high">')
            ->toContain('fetchpriority="high"');

        // Personne connectée : annuaire et page assistant membre, pas d'offcanvas public.
        $this->actingAs(User::factory()->create());
        $member = cprHtml('/violences');

        expect(cprMain($member))->toContain('href="'.route('annuaire').'"')->toContain('href="'.route('assistant').'"')
            ->not->toContain('data-bs-target="#offcanvasChat"')
            ->and($member)->not->toContain('id="offcanvasChat"');
    });

    it('rend l’auto-évaluation sans formulaire envoyable : rien ne peut partir vers le serveur', function (): void {
        $check = cprSection(cprMain(cprHtml('/violences')), '<section class="card" id="auto-evaluation"');

        expect(substr_count($check, 'data-check-question'))->toBe(8)
            ->and(substr_count($check, 'type="radio"'))->toBe(16)
            ->and(substr_count($check, 'autocomplete="off"'))->toBe(16)
            ->and($check)->toContain('x-data="xmSelfCheck(')
            ->toContain('x-on:pagehide.window="clear()"')
            // Une question = un fieldset, énoncé en legend (ACCESSIBILITE.md §4).
            ->and(substr_count($check, '<fieldset class="xm-check-q">'))->toBe(8)
            ->and($check)->toContain('<legend class="xm-choice-legend" id="check-q0">')
            ->not->toContain('role="radiogroup"')
            ->toContain('data-check-tools hidden x-bind:hidden="false"')
            ->toContain('data-check-nojs x-show="false"')
            ->toContain('aria-live="polite" data-check-notice')
            ->toContain(e(__('pages/violences.check.intro', ['count' => 8])))
            ->not->toContain('<form')->not->toContain('action=')->not->toContain('method=')
            ->not->toContain('type="submit"')->not->toContain('name="_token"')
            ->not->toContain('fetch(')->not->toContain('localStorage');

        foreach (['low' => 'success', 'medium' => 'warning', 'high' => 'danger'] as $level => $tone) {
            expect($check)->toContain('<div class="alert alert-'.$tone.' mt-3 mb-0" data-check-result="'.$level.'" tabindex="-1"')
                ->toContain('hidden x-bind:hidden="result !== \''.$level.'\'"')
                ->toContain(e(__("pages/violences.check.results.$level.title")));
        }

        expect(substr_count($check, 'type="button"'))->toBe(2 + 3); // Voir / Recommencer + assistant de chaque résultat
    });

    it('transmet au composant les seuils du prototype et les messages, sans texte en dur', function (): void {
        $check = cprSection(cprMain(cprHtml('/violences')), '<section class="card" id="auto-evaluation"');
        preg_match('~xmSelfCheck\(JSON\.parse\(\'(.*?)\'\)\)~s', $check, $json);
        $config = json_decode(json_decode('"'.($json[1] ?? '').'"', flags: JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        expect($config)->toBe([
            'total' => 8,
            'lowMax' => ViolencesPage::LOW_MAX,
            'mediumMax' => ViolencesPage::MEDIUM_MAX,
            'texts' => [
                'incomplete' => __('pages/violences.check.incomplete', ['count' => 8]),
                'resetDone' => __('pages/violences.check.reset_done'),
            ],
        ]);
    });

    it('garde le rouge plein pour le seul SOS du héro', function (): void {
        $main = cprMain(cprHtml('/violences'));
        $hero = cprSection($main, '<section class="card overflow-hidden"');
        $beforeCheck = (string) strstr($main, '<section class="card" id="auto-evaluation"', true);

        expect(substr_count($hero, 'btn btn-danger'))->toBe(1)
            ->and(substr_count($beforeCheck, 'btn btn-danger'))->toBe(1);
    });

    it('produit titre, description, Article, fil d’Ariane et canonical, indexable', function (): void {
        $html = cprHtml('/violences?utm=1');
        $article = collect(cprGraph($html))->firstWhere('@type', 'Article');
        $crumbs = collect(cprGraph($html))->firstWhere('@type', 'BreadcrumbList');

        expect($html)->toContain('<title>'.e(__('seo.pages.violences.title')).'</title>')
            ->toContain('<link rel="canonical" href="'.route('violences').'">')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->toContain('<meta property="og:type" content="article">')
            ->and(cprMain($html))->toContain('<nav aria-label="'.e(__('components.breadcrumb.label')).'">')
            ->toContain('href="'.route('home').'"')
            ->and($article['headline'])->toBe(__('pages/violences.title'))
            ->and($article['articleSection'])->toBe(__('pages/violences.schema_section'))
            ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/violences.title')]);
    });
});

describe('/lois', function (): void {
    it('liste les textes publiés dans l’ordre, avec version simple cachée et repli sur le résumé', function (): void {
        Law::factory()->create(['slug' => 'loi-b', 'title' => 'Texte B', 'position' => 2, 'category' => 'Santé', 'summary' => 'Résumé B.', 'simple_summary' => 'Simple B.']);
        Law::factory()->create(['slug' => 'loi-a', 'title' => 'Texte A', 'position' => 1, 'category' => 'Violences', 'summary' => 'Résumé A.', 'simple_summary' => null]);
        Law::factory()->unpublished()->create(['slug' => 'loi-brouillon', 'title' => 'Texte non publié']);

        $main = cprMain(cprHtml('/lois'));
        preg_match_all('~<div class="accordion-item" id="texte-([a-z0-9-]+)" data-law~', $main, $ids);

        expect($ids[1])->toBe(['loi-a', 'loi-b'])
            ->and($main)->not->toContain('Texte non publié')
            ->toContain('data-cat="Violences" data-text="texte a')
            ->toContain('Résumé B.</p>')
            ->toContain('<p class="mb-2" data-law-simple hidden>')
            ->toContain('Simple B.</p>')
            // Texte sans version simple : le résumé tient lieu de version simple (prototype l.47).
            ->and(substr_count(cprSection($main, 'id="texte-loi-a"'), 'Résumé A.'))->toBeGreaterThanOrEqual(2)
            ->and($main)->toContain('<option value="Violences">Violences</option>')
            ->toContain('<option value="Santé">Santé</option>')
            ->toContain('aria-controls="texte-loi-a-texte"')
            ->toContain('data-bs-parent="#lois-liste"')
            // Sans JavaScript, tous les résumés sont lisibles : panneaux ouverts côté serveur, repliés par xmLaws.
            ->and(substr_count($main, 'class="accordion-collapse collapse show"'))->toBe(2)
            ->and(substr_count($main, 'class="accordion-button" type="button" data-bs-toggle="collapse"'))->toBe(2)
            ->and(substr_count($main, 'aria-expanded="true"'))->toBe(2)
            ->and($main)->not->toContain('accordion-button collapsed')->not->toContain('aria-expanded="false"');
    });

    it('révèle les outils avec JavaScript seulement, sans formulaire ni requête', function (): void {
        Law::factory()->create(['slug' => 'loi-a', 'category' => 'Famille']);
        $main = cprMain(cprHtml('/lois'));
        $head = cprSection($main, '<section class="card card-body" aria-labelledby="lois-titre">');

        expect($main)->toContain('x-data="xmLaws(')
            ->and(substr_count($head, 'data-law-tool hidden x-bind:hidden="false"'))->toBe(2)
            ->and($head)->toContain('role="switch" id="lois-simple"')
            ->toContain('x-model="simple"')
            ->toContain('x-model.debounce.150ms="q"')
            ->toContain('x-model="cat"')
            ->toContain('aria-live="polite" data-law-count')
            ->not->toContain('<form')->not->toContain('onchange')
            ->and($main)->toContain('data-law-empty hidden x-bind:hidden="visible !== 0"');
    });

    it('n’affiche un lien officiel qu’en http(s) et oriente vers le bon contact juriste', function (): void {
        Law::factory()->create(['slug' => 'loi-officielle', 'official_url' => 'https://sgg.gouv.bj/doc/loi-2011-26']);
        Law::factory()->create(['slug' => 'loi-piegee', 'official_url' => 'javascript:alert(1)']);

        $main = cprMain(cprHtml('/lois'));

        expect($main)->toContain('href="https://sgg.gouv.bj/doc/loi-2011-26" target="_blank" rel="noopener noreferrer external"')
            ->not->toContain('javascript:')
            ->and(substr_count($main, e(__('pages/lois.official'))))->toBe(1)
            ->and($main)->toContain('href="'.route('urgences').'#urgences-contacts"')
            ->not->toContain('href="'.route('annuaire').'"');

        $this->actingAs(User::factory()->create());
        expect(cprMain(cprHtml('/lois')))->toContain('href="'.route('annuaire').'"');
    });

    it('dit qu’aucun texte n’est publié, sans outils', function (): void {
        $main = cprMain(cprHtml('/lois'));

        expect($main)->toContain(e(__('pages/lois.none')))
            ->not->toContain('data-law-tool')->not->toContain('data-law ');
    });

    it('sème les 7 textes du prototype avec leur version simple, en typographie française', function (): void {
        expect(Schema::hasColumn('laws', 'simple_summary'))->toBeTrue();

        $this->seed(ContentSeeder::class);
        $law = Law::query()->where('slug', 'loi-2011-26')->firstOrFail();

        expect(Law::query()->published()->count())->toBe(7)
            ->and($law->simple_summary)->toStartWith('La loi protège les femmes'.FrenchTypography::NBSP.':')
            ->and($law->title)->toBe('Loi n°'.FrenchTypography::NBSP.'2011-26 du 9 janvier 2012')
            ->and(Law::query()->where('slug', 'charte-africaine')->value('title'))->toBe('Charte africaine des droits de l’homme et des peuples');

        $main = cprMain(cprHtml('/lois'));
        expect($main)->toContain(e($law->simple_summary))->toContain('<option value="Constitution">Constitution</option>');
    });

    it('produit titre, description, CollectionPage des textes et date de mise à jour', function (): void {
        Carbon::setTestNow('2026-10-18 10:00:00');
        Law::factory()->create(['slug' => 'loi-a', 'title' => 'Texte A', 'position' => 1]);
        Carbon::setTestNow();

        $html = cprHtml('/lois');
        $page = collect(cprGraph($html))->firstWhere('@type', 'CollectionPage');

        expect($html)->toContain('<title>'.e(__('seo.pages.lois.title')).'</title>')
            ->toContain('<link rel="canonical" href="'.route('lois').'">')
            ->and($page['mainEntity']['itemListElement'][0])->toMatchArray(['url' => route('lois').'#texte-loi-a', 'name' => 'Texte A'])
            ->and(cprMain($html))->toContain('<nav aria-label="'.e(__('components.breadcrumb.label')).'">');
    });
});

describe('/do-glegbe', function (): void {
    it('sert le contenu du prototype, en vous, avec le lien actif de l’en-tête', function (): void {
        $html = cprHtml('/do-glegbe');
        $main = cprMain($html);

        expect($main)->toContain('<h1 class="display-5 fw-bold mb-3" id="dg-titre">Do Glegbe</h1>')
            ->toContain(e(__('pages/do_glegbe.mission.lead')))
            ->and(substr_count($main, 'class="dg-benefit"'))->toBe(4)
            ->and(substr_count($main, 'class="dg-step d-flex flex-column"'))->toBe(3)
            ->and($main)->toContain('href="'.route('evenements.index').'"')
            ->toContain('href="'.route('groupes.index').'"')
            ->toContain('href="'.route('forums.index').'"')
            ->toContain('href="'.route('urgences').'"')
            ->toContain('('.e(__('pages/do_glegbe.services.members_only')).')')
            ->not->toContain('touxtes')->not->toContain('style=')
            ->and($html)->toMatch('~class="nav-link active fw-bold" href="'.preg_quote(route('do-glegbe'), '~').'"\s+aria-current="page"~');
    });

    it('liste les fiches publiées de la catégorie Do Glegbe, ou omet la section', function (): void {
        expect(cprMain(cprHtml('/do-glegbe')))->not->toContain('id="dg-fiches"');

        $regles = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => 'regles', 'label' => 'Hygiène menstruelle']);
        $other = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => 'droits']);
        foreach (range(1, 5) as $i) {
            Thematique::factory()->create(['category_id' => $regles->id, 'title' => 'Fiche règles '.$i, 'slug' => 'fiche-regles-'.$i, 'published_at' => now()->subDays($i)]);
        }
        Thematique::factory()->draft()->create(['category_id' => $regles->id, 'title' => 'Fiche brouillon']);
        Thematique::factory()->create(['category_id' => $other->id, 'title' => 'Fiche autre catégorie']);
        app(Referentials::class)->invalidate();

        $main = cprMain(cprHtml('/do-glegbe'));
        $sheets = cprSection($main, '<section aria-labelledby="dg-fiches">');

        expect(substr_count($sheets, 'class="card h-100 xm-card-hover'))->toBe(4)
            ->and($sheets)->toContain('Fiche règles 1')->not->toContain('Fiche règles 5')
            ->not->toContain('Fiche brouillon')->not->toContain('Fiche autre catégorie')
            ->toContain('href="'.route('thematiques.index', ['cat' => 'regles']).'"')
            ->and($main)->toContain('class="btn btn-light fw-bold text-danger" href="'.route('thematiques.index', ['cat' => 'regles']).'"');
    });

    it('rend le quiz sans formulaire, avec un corrigé lisible sans JavaScript', function (): void {
        $quiz = cprSection(cprMain(cprHtml('/do-glegbe')), '<section class="card card-body p-4 p-md-5" id="quiz"');
        $items = (array) __('pages/do_glegbe.quiz.items');

        expect(substr_count($quiz, 'data-quiz-item'))->toBe(count($items))
            ->and(substr_count($quiz, '<details class="small" data-quiz-details'))->toBe(count($items))
            ->and(substr_count($quiz, 'type="radio"'))->toBe(2 * count($items))
            ->and(substr_count($quiz, 'data-quiz-choices hidden x-bind:hidden="false"'))->toBe(count($items))
            ->and(substr_count($quiz, '<legend class="xm-choice-legend fw-semibold"'))->toBe(count($items))
            ->and($quiz)->not->toContain('role="radiogroup"')
            ->toContain('x-data="xmQuiz(')
            ->toContain(e($items[0]['explanation']))
            ->toContain('aria-live="polite" data-quiz-live')
            ->not->toContain('<form')->not->toContain('fetch(')->not->toContain('localStorage');
    });

    it('produit un Article et un og:type article', function (): void {
        $html = cprHtml('/do-glegbe');
        $article = collect(cprGraph($html))->firstWhere('@type', 'Article');

        expect($html)->toContain('<title>'.e(__('seo.pages.do_glegbe.title')).'</title>')
            ->toContain('<meta property="og:type" content="article">')
            ->and($article['headline'])->toBe(__('pages/do_glegbe.headline'))
            ->and($article['articleSection'])->toBe(__('pages/do_glegbe.schema_section'));
    });
});

describe('commun aux trois pages', function (): void {
    it('respecte les longueurs SEO (title 50–60, description 140–160) sans guillemets doubles', function (string $key): void {
        $title = (string) __("seo.pages.$key.title");
        $description = (string) __("seo.pages.$key.description");

        expect(mb_strlen($title))->toBeGreaterThanOrEqual(50)->toBeLessThanOrEqual(60)
            ->and($title)->toEndWith(' – Xomiton')
            ->and(mb_strlen($description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160)
            ->and($description)->not->toContain('"');
    })->with(['violences', 'lois', 'do_glegbe']);

    it('vouvoie et respecte la typographie française dans les textes', function (string $file): void {
        $lines = collect((array) trans("pages/$file"))->flatten()->filter(static fn (mixed $line): bool => is_string($line));

        foreach ($lines as $line) {
            expect($line)->not->toMatch('/\b(tu|toi|ton|tes)\b/iu')
                ->not->toMatch('/ [?!:;](?![a-z])/u')
                ->not->toMatch('/n°(?!\x{00A0})/u') // « n° » suivi d'une espace insécable
                ->not->toContain("'");
        }
    })->with(['violences', 'lois', 'do_glegbe']);

    it('écrit « n° » suivi d’une espace insécable dans les textes de loi semés', function (): void {
        $fields = collect(require database_path('seeders/data/content/laws.php'))->flatten();

        expect($fields->filter(static fn (string $field): bool => str_contains($field, 'n°'))->count())->toBe(3);
        $fields->each(static fn (string $field) => expect($field)->not->toMatch('/n°(?!\x{00A0})/u'));
    });

    it('apparaissent dans le sitemap et remplacent les adresses du prototype', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);
        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();
        $root = rtrim((string) config('app.url'), '/');

        expect($xml)->toContain('<loc>'.$root.'/violences</loc>')
            ->toContain('<loc>'.$root.'/lois</loc>')
            ->toContain('<loc>'.$root.'/do-glegbe</loc>');

        $this->get('/violences.php')->assertStatus(301)->assertRedirect('/violences');
        $this->get('/lois-benin.php')->assertStatus(301)->assertRedirect('/lois');
        $this->get('/do-glegbe.php')->assertStatus(301)->assertRedirect('/do-glegbe');
    });
});
