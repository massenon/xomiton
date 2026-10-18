<?php

declare(strict_types=1);

use App\Application\Content\RecordFaqFeedback;
use App\Application\Seo\SitemapBuilder;
use App\Http\Controllers\Web\HomeController;
use App\Models\Category;
use App\Models\Faq;
use App\Models\FaqFeedback;
use App\Models\User;
use Database\Seeders\ContentSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Centre d'aide (P4-05) : `/aide` (FAQ des catégories `faq` — compte, signalement, soutien, communauté —, filtres
 * `cat`, recherche sans accents, bloc « en danger », FAQPage sans les questions de l'accueil) et `/aide/{slug}` (« À
 * savoir », autres questions, vote « Cet article vous a-t-il aidé·e ? » persisté dans `faq_feedback` avec ou sans
 * JavaScript, empreinte sans IP). Publiques et indexables (vous), sitemap, redirections du prototype.
 */

function aidHtml(string $uri): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** Contenu de <main> (sans l'en-tête, le pied de page ni l'offcanvas de l'assistant). */
function aidMain(string $html): string
{
    preg_match('~<main id="contenu"[^>]*>(.*)</main>~s', $html, $matches);

    return (string) strstr($matches[1] ?? '', '<div class="offcanvas', true) ?: ($matches[1] ?? '');
}

/** @return list<array<string, mixed>> */
function aidGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

/**
 * Questions de l'accordéon, dans l'ordre affiché (espaces insécables ramenées à des espaces pour la lisibilité).
 *
 * @return list<string>
 */
function aidQuestions(string $html): array
{
    preg_match_all('~<button class="accordion-button[^"]*"[^>]*>([^<]+)</button>~', $html, $matches);

    return array_map(static fn (string $q): string => str_replace("\u{00A0}", ' ', html_entity_decode($q, ENT_QUOTES)), $matches[1]);
}

/** Texte attendu en typographie française : espace insécable avant « ? » et « : ». */
function aidNb(string $text): string
{
    return str_replace([' ?', ' :'], ["\u{00A0}?", "\u{00A0}:"], $text);
}

/**
 * Cookie de session fixe : les requêtes d'un test partagent alors la même session, comme un navigateur (`fetch` de
 * aide.js envoie ses cookies : `credentials: 'same-origin'` ; withCredentials() les joint aussi aux requêtes JSON).
 * Changer de session vide d'abord le magasin : dans un test, il survit d'une requête à l'autre et garderait le jeton
 * de vote de la session précédente (RecordFaqFeedback::VOTER_KEY), ce qu'un vrai navigateur ne partage pas.
 */
function aidSession(string $char = 'a'): void
{
    test()->flushSession();
    test()->withCredentials()->withCookie((string) config('session.cookie'), str_repeat($char, 40));
}

beforeEach(function (): void {
    $this->seed(ContentSeeder::class);
});

describe('/aide', function (): void {
    it('liste les questions des quatre catégories avec leur nombre, dans l’ordre, et le bloc « en danger »', function (): void {
        $main = aidMain(aidHtml('/aide'));
        preg_match_all('~<a class="card h-100[^"]*" href="([^"]+)"[^>]*>.*?<span class="d-block h6 mb-1">([^<]+)</span>\s*<small class="text-secondary">([^<]+)</small>~s', $main, $cards);

        expect(array_map(html_entity_decode(...), $cards[2]))->toBe(['Compte & inscription', 'Signalement & urgences', 'Soutien & consultations', 'Communauté & événements'])
            ->and($cards[3])->toBe(['3 articles', '3 articles', '2 articles', '2 articles'])
            ->and($cards[1][1])->toBe(route('aide.index', ['cat' => 'signal']))
            ->and(aidQuestions($main))->toHaveCount(10)
            ->and(aidQuestions($main)[0])->toBe('Qu’est-ce que Xomiton ?')
            ->and($main)->toContain('<h1 class="h2 fw-bold text-white" id="aide-titre">'.e(__('pages/aide.hero_title')).'</h1>')
            ->toContain('href="'.route('aide.show', 'protection-des-donnees').'"')
            ->toContain('<button class="btn btn-primary" type="submit">'.__('pages/aide.search_submit').'</button>')
            ->toContain('href="'.route('urgences').'"')->toContain('href="'.route('signaler').'"')
            // Rouge réservé à l'attention : un seul bouton plein, le SOS du bloc « en danger ».
            ->and(substr_count($main, 'btn btn-danger'))->toBe(1)
            ->and($main)->toContain('<a class="btn btn-danger" href="'.route('sos').'">');
    });

    it('n’affiche que les questions publiées des catégories `faq` actives', function (): void {
        $thematique = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => 'vbg-test']);
        Faq::factory()->create(['category_id' => $thematique->id, 'question' => 'Question de fiche thématique ?']);
        Faq::factory()->create(['question' => 'Question sans catégorie ?']);
        Faq::query()->where('slug', 'porter-plainte-police')->update(['is_published' => false]);
        Category::query()->where('kind', Category::KIND_FAQ)->where('slug', 'communaute')->first()->update(['is_active' => false]);

        $main = aidMain(aidHtml('/aide'));

        expect(aidQuestions($main))->toHaveCount(7)
            ->and($main)->not->toContain('Question de fiche thématique')->not->toContain('Question sans catégorie')
            ->not->toContain('Dois-je porter plainte')->not->toContain('Communauté &amp; événements');
        $this->get('/aide?cat=communaute')->assertNotFound();
    });

    it('filtre par catégorie et répond 404 à une catégorie inconnue, mal formée ou vide', function (): void {
        $main = aidMain(aidHtml('/aide?cat=signal'));

        expect(aidQuestions($main))->toBe(['Quel type d’abus puis-je signaler ?', 'Que se passe-t-il après mon signalement ?', 'Dois-je porter plainte à la police ?'])
            ->and($main)->toContain('<h2 class="h5 mb-0" id="aide-liste-titre">Signalement &amp; urgences</h2>')
            ->toContain('href="'.route('aide.index', ['cat' => 'signal']).'" aria-current="true"')
            ->toContain(__('pages/aide.clear_filters'));

        Category::factory()->ofKind(Category::KIND_FAQ)->create(['slug' => 'vide']);
        $this->get('/aide?cat=vide')->assertNotFound();
        $this->get('/aide?cat=inconnue')->assertNotFound();
        $this->get('/aide?cat=Mal%20Form%C3%A9e')->assertNotFound();
        $this->get('/aide?cat[]=signal')->assertNotFound();
    });

    it('cherche sans tenir compte des accents ni de la casse, mot par mot, et ne s’indexe pas', function (): void {
        $html = aidHtml('/aide?q='.urlencode('DONNEES protegees'));

        expect(aidQuestions(aidMain($html)))->toBe(['Mes données sont-elles protégées ?'])
            ->and($html)->toContain('content="noindex, nofollow"')
            ->toContain('<link rel="canonical" href="'.route('aide.index').'">')
            ->toContain(trans_choice('pages/aide.results_count', 1))
            ->and(aidQuestions(aidMain(aidHtml('/aide?q=signaler+abus'))))->toBe(['Quel type d’abus puis-je signaler ?'])
            ->and(aidQuestions(aidMain(aidHtml('/aide?cat=compte&q=chat'))))->toBe([]);

        $empty = aidMain(aidHtml('/aide?q='.urlencode('<b>zzz</b>')));
        expect($empty)->toContain(e(__('pages/aide.empty_title', ['query' => '<b>zzz</b>'])))
            ->not->toContain('<b>zzz</b>')
            ->toContain('href="'.route('signaler', ['tab' => 'besoin']).'"')
            // Un seul message d'absence : le compte « Aucune question trouvée. » n'est pas ajouté à l'état vide.
            ->not->toContain(trans_choice('pages/aide.results_count', 0));
    });

    it('ignore les mots vides et n’annonce aucun compte quand aucun mot n’est retenu', function (): void {
        expect(aidQuestions(aidMain(aidHtml('/aide?q='.urlencode('Comment signaler un abus ?')))))->toBe(['Quel type d’abus puis-je signaler ?']);

        $all = aidMain(aidHtml('/aide?q='.urlencode('a b le de')));
        expect(aidQuestions($all))->toHaveCount(10)
            ->and($all)->not->toContain(trans_choice('pages/aide.results_count', 10))
            ->not->toContain(e(__('pages/aide.empty_title', ['query' => 'a b le de'])));
    });

    it('propose « Créer un compte » discrètement aux visiteur·ses seulement', function (): void {
        $link = '<a href="'.route('inscription').'">'.__('buttons.discovery.sign_up').'</a>';

        expect(aidMain(aidHtml('/aide')))->toContain(e(__('pages/aide.account_text')))->toContain($link)
            ->and(aidMain((string) $this->actingAs(User::factory()->create())->get('/aide')->assertOk()->getContent()))->not->toContain($link);
    });

    it('produit titre, description, BreadcrumbList et un FAQPage sans les questions de l’accueil, seulement sans filtre', function (): void {
        $html = aidHtml('/aide');
        $graph = aidGraph($html);
        $faq = collect($graph)->firstWhere('@type', 'FAQPage');
        $crumbs = collect($graph)->firstWhere('@type', 'BreadcrumbList');

        expect($html)->toContain('<title>'.e(__('seo.pages.aide.title')).'</title>')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/aide.title')])
            // Les six premières sont balisées par l'accueil (show_on_home) : une seule instance par question sur le site.
            ->and(array_column($faq['mainEntity'], 'name'))->toBe(array_map(aidNb(...), [
                'Comment accéder aux consultations spécialisées ?', 'Comment participer à un événement ou un atelier ?',
                'Xomiton est-il un espace sûr pour les personnes LBTQ+ ?', 'Mes données sont-elles protégées ?',
            ]))
            ->and($faq['mainEntity'][3]['acceptedAnswer']['text'])->toStartWith('Chiffrement, pseudonymes');

        // Au-delà des FAQ_LIMIT questions retenues par l'accueil, une question cochée `show_on_home` reste balisée ici.
        Faq::query()->where('slug', 'consultations-specialisees')->update(['show_on_home' => true]);
        $names = array_column(collect(aidGraph(aidHtml('/aide')))->firstWhere('@type', 'FAQPage')['mainEntity'], 'name');
        expect(HomeController::FAQ_LIMIT)->toBe(6)
            ->and($names)->toContain(aidNb('Comment accéder aux consultations spécialisées ?'))->toHaveCount(4);

        $filtered = aidGraph(aidHtml('/aide?cat=signal'));
        expect(collect($filtered)->firstWhere('@type', 'FAQPage'))->toBeNull()
            ->and(array_column(collect($filtered)->firstWhere('@type', 'BreadcrumbList')['itemListElement'], 'name'))
            ->toBe([__('seo.home'), __('pages/aide.title'), 'Signalement & urgences']);
    });
});

describe('/aide/{slug}', function (): void {
    it('affiche la question, sa catégorie, « À savoir » et les autres questions de la même catégorie d’abord', function (): void {
        $html = aidHtml('/aide/apres-mon-signalement');
        $main = aidMain($html);
        preg_match('~id="aide-autres-titre">.*?</section>~s', $main, $aside);
        preg_match_all('~<a class="list-group-item[^"]*" href="([^"]+)">~', $aside[0] ?? '', $related);

        expect($main)->toContain('<h1 class="h3" id="aide-article-titre">'.aidNb('Que se passe-t-il après mon signalement ?').'</h1>')
            ->toContain('<p class="lead">Il est examiné en toute confidentialité')
            ->toContain('href="'.route('aide.index', ['cat' => 'signal']).'"')
            ->toContain(e(__('pages/aide.know.signal')[0]))
            ->not->toContain(e(__('pages/aide.know.compte')[0]))
            ->and(array_slice($related[1], 0, 2))->toBe([route('aide.show', 'abus-a-signaler'), route('aide.show', 'porter-plainte-police')])
            ->and($related[1])->toHaveCount(6)->not->toContain(route('aide.show', 'apres-mon-signalement'))
            ->and(substr_count($main, 'btn btn-danger'))->toBe(1)
            // MetaText::title ramène les espaces (insécables comprises) à des espaces simples.
            ->and($html)->toContain('<title>'.e('Que se passe-t-il après mon signalement ? – Xomiton').'</title>')
            ->and(array_column(collect(aidGraph($html))->firstWhere('@type', 'BreadcrumbList')['itemListElement'], 'name'))
            ->toBe([__('seo.home'), __('pages/aide.title'), 'Signalement & urgences', aidNb('Que se passe-t-il après mon signalement ?')])
            // Une seule instance balisée par question : ni l'article ni sa colonne ne repèrent de FAQPage.
            ->and(collect(aidGraph($html))->firstWhere('@type', 'FAQPage'))->toBeNull();
    });

    it('donne la règle réelle du mot de passe dans « À savoir » du compte', function (): void {
        expect(aidMain(aidHtml('/aide/qui-peut-s-inscrire')))->toContain('10 caractères au moins')->not->toContain(':min');
    });

    it('répond 404 hors du centre d’aide : inconnue, non publiée, d’une fiche thématique', function (): void {
        Faq::query()->where('slug', 'chat-d-assistance')->update(['is_published' => false]);
        $thematique = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create();
        Faq::factory()->create(['category_id' => $thematique->id, 'slug' => 'fiche-thematique']);

        $this->get('/aide/inconnue')->assertNotFound();
        $this->get('/aide/chat-d-assistance')->assertNotFound();
        $this->get('/aide/fiche-thematique')->assertNotFound();
        $this->get('/aide/Majuscules')->assertNotFound();

        // Article d'une catégorie désactivée : hors du centre d'aide, vote compris.
        $this->get('/aide/espace-sur-lbtq')->assertOk();
        Category::query()->where('kind', Category::KIND_FAQ)->where('slug', 'communaute')->firstOrFail()->update(['is_active' => false]);
        $this->get('/aide/espace-sur-lbtq')->assertNotFound();
        $this->postJson('/aide/espace-sur-lbtq/avis', ['helpful' => '1'])->assertNotFound();
    });

    it('garde le contraste AA du rouge sur fond teinté et ne nomme pas la colonne d’un titre trompeur', function (): void {
        $main = aidMain(aidHtml('/aide/abus-a-signaler'));

        expect($main)->toContain('class="badge bg-danger bg-opacity-10 xm-help-attention mb-2 text-decoration-none"')
            ->toContain('<h2 class="h6 xm-help-attention" id="aide-maintenant-titre">')
            ->not->toContain('text-danger')
            ->toContain('<aside class="col-lg-4 vstack gap-3">')
            ->and(aidMain(aidHtml('/aide/qui-peut-s-inscrire')))->toContain('class="badge bg-primary bg-opacity-10 text-primary mb-2');
    });

    it('propose le vote en formulaire POST avec jeton CSRF, « Non » hors du rouge', function (): void {
        $main = aidMain(aidHtml('/aide/abus-a-signaler'));

        expect($main)->toContain('<form class="xm-help-feedback" id="avis" method="post" action="'.route('aide.feedback', 'abus-a-signaler').'" data-xm-faq-feedback')
            ->toContain('name="_token"')
            ->toContain(e(__('pages/aide.feedback.question')))
            ->toContain('<button class="btn btn-sm btn-success-soft" type="submit" name="helpful" value="1">')
            ->toContain('<button class="btn btn-sm btn-light" type="submit" name="helpful" value="0">')
            ->toContain('tabindex="-1" data-feedback-done></p>');
    });
});

describe('vote « Cet article vous a-t-il aidé·e ? »', function (): void {
    it('enregistre une voix par session et par question, sans IP ni compte, et remplace un nouveau vote', function (): void {
        $faq = Faq::query()->where('slug', 'abus-a-signaler')->firstOrFail();
        aidSession('a');

        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])
            ->assertOk()->assertExactJson(['message' => __('pages/aide.feedback.thanks_yes'), 'helpful' => true]);
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '0'])
            ->assertOk()->assertJsonPath('message', __('pages/aide.feedback.thanks_no'));

        $row = FaqFeedback::query()->sole();
        expect($row->faq_id)->toBe($faq->id)
            ->and($row->helpful)->toBeFalse()
            ->and($row->fingerprint)->toMatch('/^[0-9a-f]{64}$/')
            ->and($row->created_at)->not->toBeNull()
            ->and(json_encode($row->getAttributes()))->not->toContain('127.0.0.1')
            ->and(array_keys($row->getAttributes()))->toEqualCanonicalizing(['id', 'faq_id', 'helpful', 'fingerprint', 'created_at']);

        // Autre session, compte connecté (autre session), autre question : autant de voix distinctes.
        aidSession('b');
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        aidSession('c');
        $this->actingAs(User::factory()->create())->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        $this->postJson('/aide/protection-des-donnees/avis', ['helpful' => '1'])->assertOk();

        expect($faq->feedback()->count())->toBe(3)
            ->and($faq->feedback()->where('helpful', true)->count())->toBe(2)
            ->and(FaqFeedback::query()->count())->toBe(4);
    });

    it('garde la même empreinte à la connexion (session régénérée) et ne la dérive ni du compte ni de la session', function (): void {
        $faq = Faq::query()->where('slug', 'abus-a-signaler')->firstOrFail();
        $request = Request::create('/aide/abus-a-signaler/avis', 'POST');
        $request->setLaravelSession($session = app('session')->driver());
        $session->start();

        $before = RecordFaqFeedback::fingerprint($faq, $request);
        $id = $session->getId();
        $session->regenerate();
        $user = User::factory()->create();
        $request->setUserResolver(static fn () => $user);

        expect(RecordFaqFeedback::fingerprint($faq, $request))->toBe($before)
            ->and($session->getId())->not->toBe($id)
            ->and($before)->not->toBe(App\Support\KeyedHash::make('faq_feedback|'.$faq->id.'|user:'.$user->id))
            ->not->toBe(App\Support\KeyedHash::make('faq_feedback|'.$faq->id.'|session:'.$id));

        // Déconnexion : session vidée, nouveau jeton, nouvelle voix possible.
        $session->invalidate();
        expect(RecordFaqFeedback::fingerprint($faq, $request))->not->toBe($before);
    });

    it('fonctionne sans JavaScript : retour sur #avis avec le remerciement à la place des boutons', function (): void {
        aidSession();
        $this->post('/aide/porter-plainte-police/avis', ['helpful' => '0'])
            ->assertRedirect(route('aide.show', 'porter-plainte-police').'#avis');

        $main = aidMain(aidHtml('/aide/porter-plainte-police'));
        expect($main)->toContain('data-feedback-actions hidden')
            ->toContain('tabindex="-1" data-feedback-done>'.e(__('pages/aide.feedback.thanks_no')).'</p>')
            ->and(FaqFeedback::query()->sole()->helpful)->toBeFalse();

        // Le remerciement ne suit pas sur un autre article.
        $this->post('/aide/porter-plainte-police/avis', ['helpful' => '1']);
        expect(aidMain(aidHtml('/aide/abus-a-signaler')))->not->toContain('data-feedback-actions hidden');
    });

    it('refuse une valeur invalide, une question hors du centre d’aide, et ignore les robots', function (): void {
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => 'peut-etre'])
            ->assertUnprocessable()->assertJsonPath('errors.helpful.0', __('pages/aide.feedback.invalid'));
        $this->postJson('/aide/abus-a-signaler/avis')->assertUnprocessable();
        $this->postJson('/aide/inconnue/avis', ['helpful' => '1'])->assertNotFound();
        $this->withHeader('User-Agent', 'Googlebot/2.1 (+http://www.google.com/bot.html)')
            ->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();

        expect(FaqFeedback::query()->count())->toBe(0);
    });

    it('limite les votes par session, sans pénaliser une IP partagée (CGNAT), sous un plafond large par IP', function (): void {
        config(['xomiton.rate_limits.faq_feedback_per_hour' => 2, 'xomiton.rate_limits.faq_feedback_per_hour_per_ip' => 5]);

        aidSession('a');
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertTooManyRequests();

        // Même IP, autre personne (autre session) : elle vote encore, jusqu'au plafond par IP.
        aidSession('b');
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        aidSession('c');
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertOk();
        $this->postJson('/aide/abus-a-signaler/avis', ['helpful' => '1'])->assertTooManyRequests();
    });

    it('indexe le limiteur par empreinte HMAC, jamais par l’IP en clair (ADR-33)', function (): void {
        [$session, $ip] = RateLimiter::limiter('faq_feedback')(Request::create('/aide/abus-a-signaler/avis', 'POST', server: ['REMOTE_ADDR' => '41.79.12.34']));

        expect($session->key)->toMatch('/^faq_feedback\|[0-9a-f]{64}$/')->not->toContain('41.79.12.34')
            ->and($session->maxAttempts)->toBe(30)
            ->and($ip->key)->toMatch('/^faq_feedback_ip\|[0-9a-f]{64}$/')->not->toContain('41.79.12.34')
            ->and($ip->maxAttempts)->toBe(600)->and($ip->decaySeconds)->toBe(3600);
    });
});

describe('slug, sitemap et anciennes adresses', function (): void {
    it('dérive un slug de cinq mots au plus, unique et stable quand la question change', function (): void {
        $a = Faq::factory()->create(['question' => 'Comment changer mon pseudonyme sur la plateforme ?']);
        $b = Faq::factory()->create(['question' => 'Comment changer mon pseudonyme sur l’application ?']);

        expect($a->slug)->toBe('comment-changer-mon-pseudonyme-sur')
            ->and($b->slug)->toBe('comment-changer-mon-pseudonyme-sur-2');

        $a->update(['question' => 'Comment modifier mon pseudonyme ?']);
        expect($a->fresh()->slug)->toBe('comment-changer-mon-pseudonyme-sur')
            ->and(Faq::query()->where('slug', 'protection-des-donnees')->value('question'))->toBe(aidNb('Mes données sont-elles protégées ?'));

        // Rejouer le seed garde les adresses.
        $this->seed(ContentSeeder::class);
        expect(Faq::query()->whereNotNull('slug')->distinct()->count('slug'))->toBe(Faq::query()->count());
    });

    it('sépare les mots aux apostrophes et ne laisse jamais de tiret final après la coupe à 70 caractères', function (): void {
        $long = str_repeat('a', 34).' '.str_repeat('b', 34).' '.str_repeat('c', 10);

        expect(Faq::slugBase('Qu’est-ce que Xomiton ?'))->toBe('qu-est-ce-que-xomiton')
            ->and(Faq::slugBase("Quel type d'abus puis-je signaler ?"))->toBe('quel-type-d-abus-puis')
            ->and(Faq::slugBase($long))->toBe(str_repeat('a', 34).'-'.str_repeat('b', 34))
            ->and(Faq::slugBase(str_repeat('x', 90)))->toHaveLength(70)
            ->and(Faq::slugBase('?!'))->toBe('question')
            ->and(Faq::factory()->create(['question' => $long])->slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');
    });

    it('pose à la migration les slugs écrits à la main du seed, quelle que soit la graphie, puis dérive les autres', function (): void {
        $migration = require database_path('migrations/2026_10_25_080000_add_slug_to_faqs_table.php');
        $migration->down();
        DB::table('faq_feedback')->delete();
        DB::table('faqs')->delete();
        $category = Category::query()->where('kind', Category::KIND_FAQ)->value('id');
        foreach (["Qu'est-ce que Xomiton ?", 'Comment changer mon pseudonyme sur l’application ?', 'Mes données sont-elles protégées ?'] as $i => $question) {
            DB::table('faqs')->insert(['category_id' => $category, 'question' => $question, 'answer' => 'Réponse.', 'position' => $i + 1, 'is_published' => true, 'show_on_home' => false]);
        }

        $migration->up();

        expect(DB::table('faqs')->orderBy('id')->pluck('slug')->all())
            ->toBe(['qu-est-ce-que-xomiton', 'comment-changer-mon-pseudonyme-sur', 'protection-des-donnees']);
    });

    it('liste les articles du centre d’aide dans le sitemap, pas les FAQ des fiches', function (): void {
        $thematique = Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create();
        Faq::factory()->create(['category_id' => $thematique->id, 'slug' => 'fiche-thematique']);
        $locs = array_column(app(SitemapBuilder::class)->urls(), 'loc');
        $root = rtrim((string) config('app.url'), '/');
        $articles = array_filter($locs, static fn (string $loc): bool => str_starts_with($loc, $root.'/aide/'));

        expect($locs)->toContain($root.'/aide', $root.'/aide/protection-des-donnees', $root.'/confidentialite')
            ->not->toContain($root.'/aide/fiche-thematique')
            ->and($articles)->toHaveCount(10);
    });

    it('redirige les anciennes adresses du prototype en gardant le filtre, la recherche et l’article', function (): void {
        $this->get('/help.php')->assertStatus(301)->assertRedirect(route('aide.index'));
        $this->get('/help.php?cat=signal&q=plainte')->assertStatus(301)->assertRedirect(route('aide.index', ['cat' => 'signal', 'q' => 'plainte']));
        $this->get('/help.php?cat=Mal%20Form%C3%A9e')->assertStatus(301)->assertRedirect(route('aide.index'));
        // help-details.php?id=<rang 1…> : même ordre que x_faq() et le seed.
        $this->get('/help-details.php?id=3')->assertStatus(301)->assertRedirect(route('aide.show', 'abus-a-signaler'));
        $this->get('/help-details.php?id=10')->assertStatus(301)->assertRedirect(route('aide.show', 'protection-des-donnees'));
        $this->get('/help-details.php?id=11')->assertStatus(301)->assertRedirect(route('aide.index'));
        $this->get('/help-details.php?id=abc')->assertStatus(301)->assertRedirect(route('aide.index'));
        Faq::query()->where('slug', 'abus-a-signaler')->update(['is_published' => false]);
        $this->get('/help-details.php?id=3')->assertStatus(301)->assertRedirect(route('aide.index'));
        $this->get('/privacy-and-terms.php')->assertStatus(301)->assertRedirect('/confidentialite');
    });
});
