<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Faq;
use App\Models\Thematique;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/*
 * Accueil `/` (P4-01) : 11 sections dans l'ordre du prototype, CTA selon connexion, ≤ 2 rouges pleins dans le hero,
 * thématiques et FAQ lues en base (6 au plus), JSON-LD FAQPage, image LCP préchargée, aucun style en ligne,
 * vocabulaire (Q29, Q36) et offcanvas de l'assistant.
 */

function accueilHtml(): string
{
    return (string) test()->get('/')->assertOk()->getContent();
}

/** Fragment HTML entre deux marqueurs (bornes exclues). */
function accueilBetween(string $html, string $start, string $end): string
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse("Marqueur absent : $start");
    $to = strpos($html, $end, (int) $from);
    expect($to)->not->toBeFalse("Marqueur absent : $end");

    return substr($html, (int) $from, (int) $to - (int) $from);
}

/** @return array<string, mixed> le graphe JSON-LD de la page, indexé par @type (le premier de chaque type). */
function accueilJsonLd(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);
    $graph = json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];

    return collect($graph)->keyBy('@type')->all();
}

it('répond 200 avec le titre « marque en tête », la description et un seul h1', function (): void {
    $html = accueilHtml();

    expect($html)
        ->toContain('<title>'.e(__('seo.pages.home.title')).'</title>')
        ->toContain('<meta name="description" content="'.e(__('seo.pages.home.description')).'">')
        ->toContain('<link rel="canonical" href="'.url('/').'">')
        ->and(preg_match_all('/<h1[\s>]/', $html))->toBe(1)
        ->and(__('pages/accueil.hero.title'))->toBe("Xomiton\u{00A0}: votre espace safe d’apprentissage et d’appui holistique");

    expect($html)->toContain('<h1 id="heroTitre" class="display-5 mb-3">'.e(__('pages/accueil.hero.title')).'</h1>');
});

it('garde l’ordre des sections du prototype, chaque section reliée à son titre', function (): void {
    Faq::factory()->onHome()->create();
    $html = accueilHtml();

    $positions = collect(['class="lp-hero"', 'id="valeurs"', 'id="questions"', 'id="reseau"', 'id="ressources"', 'id="alerte"', 'id="assistant"', 'id="fonctionnalites"', 'id="faq"', 'id="ctaTitre"', 'id="newsletter"'])
        ->map(fn (string $marker): int|false => strpos($html, $marker));

    expect($positions->contains(false))->toBeFalse()
        ->and($positions->values()->all())->toBe($positions->sort()->values()->all());

    $main = accueilBetween($html, '<main id="contenu"', '</main>');
    preg_match_all('/<section\b[^>]*>/', $main, $sections);

    expect($sections[0])->toHaveCount(10);
    foreach ($sections[0] as $tag) {
        expect($tag)->toMatch('/aria-labelledby="[A-Za-z]+"/');
    }
});

it('propose « Créer un compte » et « Se connecter » à un·e visiteur·se, un seul rouge plein dans le hero', function (): void {
    $html = accueilHtml();
    $hero = accueilBetween($html, 'class="lp-hero"', 'id="valeurs"');

    expect(__('pages/accueil.hero.cta_primary'))->toBe('Créer un compte')
        ->and($hero)->toContain('href="'.route('inscription').'"')
        ->toContain(__('pages/accueil.hero.cta_primary'))
        ->toContain('href="'.route('connexion').'"')
        ->toContain('href="'.route('signaler').'"')
        ->not->toContain('href="'.route('fil').'"')
        ->and(substr_count($hero, 'btn-danger'))->toBe(1);

    $cta = accueilBetween($html, 'id="ctaTitre"', '</section>');
    expect($cta)->toContain(__('buttons.discovery.sign_up'))->toContain(__('buttons.discovery.sign_in'))
        ->and($html)->not->toContain('xm-sos-fab');
});

it('adapte les CTA à un·e membre connecté·e et reste indexable', function (): void {
    $this->actingAs(User::factory()->create());
    $html = accueilHtml();
    $hero = accueilBetween($html, 'class="lp-hero"', 'id="valeurs"');
    $cta = accueilBetween($html, 'id="ctaTitre"', '</section>');

    expect($hero)->toContain('href="'.route('fil').'"')
        ->toContain(__('buttons.discovery.my_space'))
        ->not->toContain('href="'.route('inscription').'"')
        ->and(substr_count($hero, 'btn-danger'))->toBe(1)
        ->and($cta)->toContain(__('pages/accueil.cta.title_member'))->toContain(__('buttons.discovery.my_space'))
        ->not->toContain(__('buttons.discovery.sign_up'))
        ->and($html)->toContain('xm-sos-fab')
        ->toContain('<meta name="robots" content="index, follow, max-image-preview:large">');
});

it('affiche les six premières FAQ « accueil » publiées, la première ouverte', function (): void {
    foreach (range(1, 8) as $position) {
        Faq::factory()->onHome()->create(['question' => "Question d’accueil n°$position ?", 'position' => $position]);
    }
    Faq::factory()->onHome()->unpublished()->create(['question' => 'Question dépubliée ?', 'position' => 0]);
    Faq::factory()->create(['question' => 'Question hors accueil ?', 'position' => 0]);

    $faq = accueilBetween(accueilHtml(), 'id="faq"', '</section>');

    foreach (range(1, 6) as $position) {
        expect($faq)->toContain(e("Question d’accueil n°$position ?"));
    }
    expect($faq)->not->toContain('n°7 ?')->not->toContain('n°8 ?')
        ->not->toContain('Question dépubliée')->not->toContain('Question hors accueil')
        ->and(substr_count($faq, 'class="accordion-button'))->toBe(6)
        ->and(substr_count($faq, 'aria-expanded="true"'))->toBe(1)
        ->and(substr_count($faq, 'accordion-button fw-semibold collapsed'))->toBe(5)
        ->and($faq)->toContain('href="'.route('aide.index').'"');
});

it('échappe les réponses de la FAQ, texte brut en base', function (): void {
    Faq::factory()->onHome()->create(['answer' => '<b>x</b> & y']);

    expect(accueilHtml())->toContain('&lt;b&gt;x&lt;/b&gt; &amp; y')->not->toContain('<b>x</b>');
});

it('publie un JSON-LD FAQPage identique à l’accordéon et un fil d’Ariane à un seul élément', function (): void {
    $faqs = collect(range(1, 3))->map(fn (int $i): Faq => Faq::factory()->onHome()->create(['position' => $i, 'answer' => "Réponse $i </script>"]));
    $html = accueilHtml();
    $graph = accueilJsonLd($html);

    expect($graph)->toHaveKeys(['Organization', 'WebSite', 'WebPage', 'BreadcrumbList', 'FAQPage'])
        ->and($graph['FAQPage']['mainEntity'])->toHaveCount(3)
        ->and($graph['FAQPage']['mainEntity'][0])->toBe([
            '@type' => 'Question',
            'name' => $faqs[0]->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Réponse 1 </script>'],
        ])
        ->and($graph['BreadcrumbList']['itemListElement'])->toHaveCount(1)
        ->and($graph['BreadcrumbList']['itemListElement'][0]['name'])->toBe(__('seo.home'))
        ->and($graph['Organization']['description'])->toBe(__('seo.organization_description'))
        ->and($graph['WebSite']['potentialAction']['@type'])->toBe('SearchAction')
        // Un contenu éditorial ne peut pas refermer la balise <script> du JSON-LD.
        ->and(substr_count($html, '</script>'))->toBe(substr_count($html, '<script'));
});

it('n’affiche ni la section FAQ ni le JSON-LD FAQPage sans FAQ « accueil »', function (): void {
    Faq::factory()->create();
    $html = accueilHtml();

    expect($html)->not->toContain('id="faq"')
        ->not->toContain('"FAQPage"')
        ->toContain('href="'.route('aide.index').'"');
});

it('affiche les six thématiques publiées les plus anciennes avec leur catégorie', function (): void {
    $category = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['label' => 'Violences', 'icon' => 'bi-shield-exclamation', 'color' => 'danger']);
    $published = Thematique::factory()->count(8)->create(['category_id' => $category->getKey(), 'published_at' => now()->subDay()]);
    $draft = Thematique::factory()->draft()->create();
    $future = Thematique::factory()->create(['published_at' => now()->addDay()]);

    $section = accueilBetween(accueilHtml(), 'id="ressources"', '</section>');

    expect(substr_count($section, 'lp-card lp-th'))->toBe(6);
    foreach ($published->sortBy('id')->take(6) as $thematique) {
        expect($section)->toContain('href="'.route('thematiques.show', $thematique->slug).'"')
            ->toContain('alt="'.e(__('pages/accueil.ressources.cover_alt', ['title' => $thematique->title])).'"');
    }
    expect($section)->toContain('Violences')->toContain('bg-danger bg-opacity-10 text-danger')->toContain('bi-shield-exclamation')
        ->not->toContain(route('thematiques.show', $published->sortBy('id')->last()->slug).'"')
        ->not->toContain(route('thematiques.show', $draft->slug).'"')
        ->not->toContain(route('thematiques.show', $future->slug).'"');
});

it('affiche un état vide avec une action sans thématique publiée', function (): void {
    Thematique::factory()->draft()->create();
    $section = accueilBetween(accueilHtml(), 'id="ressources"', '</section>');

    expect($section)->toContain(__('pages/accueil.ressources.empty'))
        ->toContain('href="'.route('thematiques.index').'"')
        ->not->toContain('lp-th');
});

it('se contente de trois requêtes de contenu, sans toucher à l’assistant ni aux paramètres', function (): void {
    Thematique::factory()->count(2)->create();
    Faq::factory()->onHome()->count(2)->create();

    accueilHtml(); // premier rendu : les paramètres (lus par le middleware de maintenance) passent en cache
    DB::enableQueryLog();
    accueilHtml();
    $queries = collect(DB::getQueryLog())->pluck('query');
    DB::disableQueryLog();

    $on = fn (string $table): int => $queries->filter(fn (string $sql): bool => str_contains($sql, 'from "'.$table.'"'))->count();

    expect($on('thematiques'))->toBe(1)
        ->and($on('categories'))->toBe(1)
        ->and($on('faqs'))->toBe(1)
        ->and($on('assistant_kb'))->toBe(0)
        ->and($on('settings'))->toBe(0);
});

it('précharge l’image LCP, diffère les autres et dimensionne chaque image', function (): void {
    $html = accueilHtml();
    $lcp = asset('assets/images/xomiton/05.jpg');
    $main = accueilBetween($html, '<main id="contenu"', '</main>');

    expect(substr_count($html, '<link rel="preload" as="image"'))->toBe(1)
        ->and($html)->toContain('<link rel="preload" as="image" href="'.$lcp.'" fetchpriority="high">')
        ->and($main)->toMatch('~<img[^>]*src="'.preg_quote($lcp, '~').'"[^>]*loading="eager"[^>]*fetchpriority="high"~');

    foreach (['04.jpg', 'mockup-B.gif', 'doglegbexo.png'] as $file) {
        expect($main)->toMatch('~<img[^>]*src="[^"]*'.preg_quote($file, '~').'"[^>]*loading="lazy"~');
    }

    preg_match_all('/<img\b[^>]*>/', $main, $images);
    expect($images[0])->not->toBeEmpty();
    foreach ($images[0] as $img) {
        expect($img)->toContain('width="')->toContain('height="')->toContain('alt="');
    }
});

it('ne porte aucun style en ligne dans le contenu principal', function (): void {
    Faq::factory()->onHome()->create();
    Thematique::factory()->create();

    expect(accueilBetween(accueilHtml(), '<main id="contenu"', '</main>'))->not->toContain(' style="');
});

it('respecte le vocabulaire : ni « dénonciation », ni 7 j/7, ni « 24 h/24 » sans réponse humaine', function (): void {
    $text = html_entity_decode(strip_tags(accueilHtml()), ENT_QUOTES | ENT_HTML5);

    expect($text)->not->toMatch('/d[ée]nonciation/iu')
        ->not->toMatch('/7\s?j\s?\/\s?7/u')
        ->not->toContain('LGBT')
        ->toContain('LBTQ+')
        ->and(__('pages/accueil.experts.arg_1_text'))->toContain('sous 24 h');

    preg_match_all('/[^.!?]*24\s?h\s?\/\s?24[^.!?]*[.!?]?/u', $text, $sentences);
    foreach ($sentences[0] as $sentence) {
        expect($sentence)->toContain('sous 24 h');
    }
});

it('inclut l’assistant : bouton flottant, offcanvas, puces et lien SOS', function (): void {
    $html = accueilHtml();
    $offcanvas = accueilBetween($html, 'id="offcanvasChat"', '</form>');

    expect($html)->toContain('class="xm-chat-fab btn btn-primary no-print"')
        ->toContain('aria-label="'.e(__('components.assistant.open')).'"')
        ->toContain('data-bs-target="#offcanvasChat"')
        ->toContain('href="'.route('sos').'"')
        ->and($offcanvas)->toContain('x-data="xmAssistant(JSON.parse(\'')
        ->toContain('role="log" aria-live="polite"')
        ->not->toContain('x-html');

    // Configuration passée à Alpine par Js::from() : JSON.parse('<JSON encodé en chaîne>').
    preg_match("~xmAssistant\\(JSON\\.parse\\('(.*?)'\\)\\)~", $offcanvas, $matches);
    $config = json_decode((string) json_decode('"'.($matches[1] ?? '').'"'), true);

    expect($config['url'] ?? null)->toBe(route('api.assistant.question'))
        ->and($config['offlineUrl'])->toBe(route('urgences'))
        ->and($config['welcome'])->toBe(__('components.assistant.welcome'))
        // Hors connexion : premier numéro d'urgence configuré, en texte et en lien tel:.
        ->and($config['texts']['offline'])->toBe(__('components.assistant.offline', ['number' => '117']))
        ->and($config['offlineCall']['url'] ?? null)->toBe('tel:117');

    // « Réponse humaine sous 24 h » rattachée au parcours « Exprimer un besoin » (l'assistant n'enregistre rien).
    expect(substr_count($html, 'href="'.e(route('signaler', ['tab' => 'besoin'])).'"'))->toBe(2);

    foreach ((array) __('components.assistant.chips') as $chip) {
        expect($offcanvas)->toContain(e($chip));
    }
});

it('redirige les anciennes adresses du prototype vers l’accueil', function (): void {
    $this->get('/index.php')->assertRedirect('/')->assertStatus(301);
    $this->get('/landing.php')->assertRedirect('/')->assertStatus(301);
});

it('Q54 : la section #alerte est un fond rouge très clair avec bordure, plus une bande rouge pleine', function (): void {
    $html = accueilHtml();

    expect($html)->toContain('lp-band-alert')->not->toContain('lp-band-red')
        ->and(file_get_contents(resource_path('css/app/pages/accueil.css')))->toContain('background: var(--xm-alert-surface)');
});
