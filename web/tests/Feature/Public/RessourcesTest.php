<?php

declare(strict_types=1);

use App\Application\Content\OfflineLibrary;
use App\Application\Shared\Referentials;
use App\Enums\ResourceKind;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use App\Support\FrenchTypography;
use App\Support\LocaleFormatter;
use App\Support\Settings;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoResourcesSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
 * Ressources publiques `/ressources`, `/ressources/{slug}` et `/ressources/{slug}/telecharger` (P4-03) : pages
 * publiques indexables (Q19 a), recherche, filtres type / catégorie, « Charger plus » côté serveur, « Fiches à
 * télécharger », FAQ de la catégorie, CTA assistant, fiche (fil d'Ariane, téléchargement, lien externe, média,
 * transcription, ressources liées), téléchargement signé et compté, interrupteur « Hors ligne » (contrat
 * data-offline-url / data-offline-file pour le service worker P5-10), JSON-LD, redirections et sitemap.
 */

const RES_CARD = '<article class="card h-100 overflow-hidden xm-card-hover">';

function resCategory(string $slug, array $attributes = [], string $kind = Category::KIND_THEMATIQUE): Category
{
    return Category::factory()->ofKind($kind)->create(['slug' => $slug, 'label' => ucfirst($slug), 'color' => 'primary', ...$attributes]);
}

/** Poids tel que l'affiche LocaleFormatter (« 1,2 Mo »). */
function resSize(int $bytes): string
{
    return LocaleFormatter::make()->fileSize($bytes);
}

/** Texte attendu en typographie française (U+00A0 avant « ? ! : ; » et dans les guillemets), comme lang/fr. */
function resFr(string $text): string
{
    return str_replace(['« ', ' »'], ['«'.FrenchTypography::NBSP, FrenchTypography::NBSP.'»'], FrenchTypography::apply($text));
}

function resHtml(string $uri): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** @return list<array<string, mixed>> */
function resGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

/** @return array<string, mixed>|null */
function resNode(string $html, string $type): ?array
{
    return collect(resGraph($html))->firstWhere('@type', $type);
}

function resBetween(string $html, string $start, string $end): string
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse("Marqueur absent : $start");
    $to = strpos($html, $end, (int) $from);
    expect($to)->not->toBeFalse("Marqueur absent : $end");

    return substr($html, (int) $from, (int) $to - (int) $from);
}

/** @return list<string> textes des titres h1 à h3 de <main>, dans l'ordre */
function resHeadings(string $html): array
{
    // Page seulement : l'offcanvas de l'assistant (dialogue distinct, P4-01) a son propre titre.
    preg_match_all('~<(h[1-3])[^>]*>(.*?)</\1>~s', resBetween($html, '<main id="contenu"', 'id="offcanvasChat"'), $matches);

    return array_map(static fn (string $text, string $tag): string => $tag.' '.trim(html_entity_decode(strip_tags($text))), $matches[2], $matches[1]);
}

beforeEach(function (): void {
    config(['xomiton.offline.library_enabled' => true]);
});

describe('liste /ressources', function (): void {
    it('répond 200, publique et indexable, avec titre, description, h1, introduction et gabarit public', function (): void {
        Resource::factory()->create();
        $html = resHtml('/ressources');

        expect($html)
            ->toContain('<title>'.e(__('seo.pages.ressources.title')).'</title>')
            ->toContain('<meta name="description" content="'.e(__('seo.pages.ressources.description')).'">')
            ->toContain('<h1 class="h3 mb-0" id="ressources-titre">Ressources</h1>')
            ->toContain('Articles, fiches pratiques et outils à lire, à enregistrer et à partager.')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->toContain('id="pubHeader"')
            ->toContain('class="xm-pub-footer')
            ->toContain('role="search"')
            ->not->toContain('xm-sidenav')
            ->and(preg_match_all('/<h1[\s>]/', $html))->toBe(1);
    });

    it('garde la structure de titres attendue (snapshot), sans saut ni style en ligne, icônes masquées', function (): void {
        $items = Resource::factory()->count(3)->sequence(fn ($sequence) => ['title' => 'Ressource '.($sequence->index + 1), 'published_at' => now()->subDays($sequence->index + 1)])->create();
        $html = resHtml('/ressources');
        $main = resBetween($html, '<main id="contenu"', 'id="offcanvasChat"');

        expect(resHeadings($html))->toBe([
            'h1 Ressources',
            'h2 3 ressources',
            ...$items->map(fn (Resource $resource): string => 'h3 '.$resource->title)->all(),
            // Titre de la liste « enregistrées seulement » (masqué hors de ce mode, x-show).
            'h2 Ressources enregistrées pour le hors connexion',
            'h2 '.resFr('Une question ? L’assistant Xomiton vous répond'),
        ])
            ->and($main)->not->toMatch('/<h[456][\s>]/')->not->toContain(' style="')
            ->and(preg_match_all('~<i class="bi [^"]*"(?! aria-hidden="true")~', $main))->toBe(0);
    });

    it('affiche le type, le poids du fichier, la durée ou le site d’un lien sur chaque carte', function (): void {
        $pdf = Resource::factory()->pdf()->create(['title' => 'Fiche PDF', 'file_size' => 1_258_291]);
        Resource::factory()->video()->create(['title' => 'Vidéo courte', 'duration_minutes' => 12]);
        Resource::factory()->link()->create(['title' => 'Lien utile', 'external_url' => 'https://www.gouv.bj/accueil']);
        $html = resHtml('/ressources');

        expect($html)
            ->toContain('<a href="'.route('ressources.show', $pdf->slug).'">Fiche PDF</a>')
            ->toContain('Document PDF')
            ->toContain('<i class="bi bi-download me-1" aria-hidden="true"></i>'.resSize(1_258_291))
            ->toContain('12 min')
            ->toContain('www.gouv.bj')
            // Types PDF (secondary = rouge de marque) et lien (warning doux peu lisible) en neutre.
            ->toContain('<span class="badge bg-light text-body border d-inline-flex align-items-center gap-1"><i class="bi bi-file-earmark-pdf" aria-hidden="true"></i>Document PDF</span>')
            ->toContain('<span class="badge bg-light text-body border d-inline-flex align-items-center gap-1"><i class="bi bi-link-45deg" aria-hidden="true"></i>Lien externe</span>');
    });

    it('n’affiche que les ressources publiées hors actualités', function (): void {
        Resource::factory()->create(['slug' => 'ressource-publiee']);
        Resource::factory()->draft()->create(['slug' => 'ressource-brouillon']);
        Resource::factory()->hidden()->create(['slug' => 'ressource-masquee']);
        Resource::factory()->scheduled()->create(['slug' => 'ressource-programmee']);
        Resource::factory()->create(['slug' => 'ressource-supprimee'])->delete();
        Resource::factory()->article()->create(['slug' => 'actualite-publiee']);

        $html = resHtml('/ressources');

        expect($html)->toContain('/ressources/ressource-publiee')
            ->not->toContain('/ressources/ressource-brouillon')
            ->not->toContain('/ressources/ressource-masquee')
            ->not->toContain('/ressources/ressource-programmee')
            ->not->toContain('/ressources/ressource-supprimee')
            ->not->toContain('actualite-publiee')
            ->and(substr_count($html, RES_CARD))->toBe(1);
    });

    it('cherche dans le titre et le résumé sans indexer les résultats, canonical sans paramètres', function (): void {
        Resource::factory()->create(['title' => 'Porter PLAINTE pas à pas', 'summary' => 'Les étapes.']);
        Resource::factory()->create(['title' => 'Respirer', 'summary' => 'Calmer une crise.']);
        $html = resHtml('/ressources?q=plainte');

        expect($html)
            ->toContain(e(resFr('1 ressource pour « plainte »')))
            ->toContain('Porter PLAINTE pas à pas')
            ->not->toContain('Respirer')
            ->toContain('<meta name="robots" content="noindex, nofollow">')
            ->toContain('<link rel="canonical" href="'.url('/ressources').'">')
            ->toContain('value="plainte"');
    });

    it('traite % et _ de la recherche comme du texte et nettoie la saisie', function (): void {
        Resource::factory()->count(2)->create();
        Resource::factory()->create(['title' => 'Taux de 100 % atteint']);

        expect(resHtml('/ressources?q=%25'))->toContain(e(resFr('1 ressource pour « % »')))
            ->and(resHtml('/ressources?q=_'))->toContain(e(resFr('Aucune ressource pour « _ »')))
            ->and(resHtml('/ressources?q='.urlencode("  taux\u{0007}   de ")))->toContain('value="taux de"');
    });

    it('filtre par type, présélectionne l’option et refuse article ou un type inconnu (404)', function (): void {
        $pdf = Resource::factory()->pdf()->create();
        $guide = Resource::factory()->ofKind(ResourceKind::Guide)->create();
        $html = resHtml('/ressources?type=pdf');

        expect($html)->toContain('/ressources/'.$pdf->slug)
            ->not->toContain('/ressources/'.$guide->slug)
            ->toContain('<option value="pdf" selected>Document PDF</option>')
            ->not->toContain('<option value="article"');

        // WCAG 3.2.2 : aucun envoi au changement du select (une flèche au clavier rechargerait la page), bouton « Filtrer ».
        expect(resBetween($html, 'role="search"', '</form>'))->not->toContain('x-on:change')->not->toContain('requestSubmit')
            ->toContain('<button class="btn btn-primary" type="submit">');

        $this->get('/ressources?type=article')->assertNotFound();
        $this->get('/ressources?type=inconnu')->assertNotFound();
        $this->get('/ressources?type[]=pdf')->assertNotFound();
        $this->get('/ressources?type=')->assertOk();
    });

    it('filtre par catégorie, marque la puce active et garde type et recherche dans les liens', function (): void {
        $vbg = resCategory('vbg', ['label' => 'Violences basées sur le genre', 'icon' => 'bi-shield-exclamation', 'position' => 1]);
        $droits = resCategory('droits', ['label' => 'Droits & lois', 'icon' => 'bi-bank', 'position' => 2]);
        resCategory('vide', ['label' => 'Sans ressource', 'position' => 3]);
        $inactive = resCategory('archives', ['label' => 'Archives', 'is_active' => false]);
        $inVbg = Resource::factory()->pdf()->for($vbg)->create(['title' => 'Fiche VBG']);
        Resource::factory()->for($droits)->create(['title' => 'Fiche droits']);
        Resource::factory()->for($inactive)->create();

        $html = resHtml('/ressources?cat=vbg&type=pdf&q=fiche');
        $filters = resBetween($html, 'id="ressources-filtres"', '</nav>');

        expect($html)->toContain('/ressources/'.$inVbg->slug)->not->toContain('Fiche droits')
            ->toContain('<input type="hidden" name="cat" value="vbg">')
            ->and($filters)
            ->toContain('aria-label="Filtrer par catégorie"')
            ->toContain('class="xm-chip text-decoration-none bg-primary text-white" href="'.e(route('ressources.index', ['cat' => 'vbg', 'q' => 'fiche', 'type' => 'pdf'])).'" aria-current="true"><i class="bi bi-shield-exclamation" aria-hidden="true"></i>VBG</a>')
            ->toContain('href="'.e(route('ressources.index', ['q' => 'fiche', 'type' => 'pdf'])).'">Tout</a>')
            ->toContain('Droits &amp; lois')
            ->not->toContain('Sans ressource')
            ->not->toContain('Archives')
            ->and(strpos($filters, 'VBG'))->toBeLessThan(strpos($filters, 'Droits'));

        $this->get('/ressources?cat=archives')->assertNotFound();
        $this->get('/ressources?cat=inconnue')->assertNotFound();
        $this->get('/ressources?cat=Mal_Forme')->assertNotFound();
        $this->get('/ressources?cat[]=vbg')->assertNotFound();
    });

    it('liste une ressource d’une catégorie inactive sans badge de catégorie, sur la carte comme sur la fiche', function (): void {
        $inactive = resCategory('archives', ['label' => 'Archives anciennes', 'is_active' => false]);
        $resource = Resource::factory()->for($inactive)->create(['title' => 'Ressource archivée']);

        expect(resBetween(resHtml('/ressources'), 'id="liste"', '</section>'))->toContain('Ressource archivée')->not->toContain('Archives anciennes')
            ->and(resBetween(resHtml('/ressources/'.$resource->slug), 'id="ressource"', '</article>'))->not->toContain('Archives anciennes');
    });

    it('propose aussi les catégories de type resource et regroupe un slug présent dans les deux types', function (): void {
        $thematique = resCategory('sante', ['label' => 'Santé', 'position' => 1]);
        $resourceKind = resCategory('sante', ['label' => 'Santé (ressources)', 'position' => 2], Category::KIND_RESOURCE);
        $outils = resCategory('outils', ['label' => 'Outils', 'position' => 3], Category::KIND_RESOURCE);
        $a = Resource::factory()->for($thematique)->create();
        $b = Resource::factory()->for($resourceKind)->create();
        Resource::factory()->for($outils)->create();

        $filters = resBetween(resHtml('/ressources'), 'id="ressources-filtres"', '</nav>');
        $html = resHtml('/ressources?cat=sante');

        expect(substr_count($filters, '?cat=sante'))->toBe(1)
            ->and($filters)->toContain('Outils')
            ->and($html)->toContain('/ressources/'.$a->slug)->toContain('/ressources/'.$b->slug)
            ->toContain('2 ressources');
    });

    it('pagine par « Charger plus » côté serveur, par pas de 6, et borne n', function (): void {
        Resource::factory()->count(8)->create();

        $first = resHtml('/ressources');
        expect(substr_count($first, RES_CARD))->toBe(6)
            ->and($first)->toContain('href="'.e(route('ressources.index', ['n' => 12])).'#liste" rel="nofollow">Charger plus')
            ->toContain('(6 sur 8)');

        $all = resHtml('/ressources?n=12');
        expect(substr_count($all, RES_CARD))->toBe(8)
            ->and($all)->toContain(e(resFr('Vous avez tout vu : 8 éléments affichés.')))
            ->not->toContain('Charger plus');

        expect(substr_count(resHtml('/ressources?n=abc'), RES_CARD))->toBe(6)
            ->and(substr_count(resHtml('/ressources?n=7'), RES_CARD))->toBe(8)
            ->and(substr_count(resHtml('/ressources?n=5000'), RES_CARD))->toBe(8);
    });

    it('au plafond max_per_load, dit combien de ressources sont affichées sur le total', function (): void {
        config(['xomiton.content.max_per_load' => 6]);
        Resource::factory()->count(8)->create();

        expect(resHtml('/ressources?n=12'))->toContain('Affichage limité à 6 ressources sur 8.')->not->toContain('Charger plus');
    });

    it('garde catégorie, recherche et type dans le lien « Charger plus »', function (): void {
        $vbg = resCategory('vbg');
        Resource::factory()->pdf()->for($vbg)->count(20)->create(['summary' => 'Une fiche x utile.']);

        expect(resHtml('/ressources?cat=vbg&q=x&type=pdf&n=12'))
            ->toContain('href="'.e(route('ressources.index', ['cat' => 'vbg', 'q' => 'x', 'type' => 'pdf', 'n' => 18])).'#liste"');
    });

    it('trie par date de publication décroissante puis par id', function (): void {
        Resource::factory()->create(['title' => 'Ancienne', 'published_at' => now()->subMonth()]);
        Resource::factory()->create(['title' => 'Récente', 'published_at' => now()->subDay()]);
        $html = resHtml('/ressources');

        expect(strpos($html, 'Récente'))->toBeLessThan(strpos($html, 'Ancienne'));
    });

    it('charge la première couverture en priorité (LCP), les suivantes à la demande, toutes dimensionnées', function (): void {
        Resource::factory()->count(3)->create();
        preg_match_all('~<img [^>]*>~', resBetween(resHtml('/ressources'), 'id="liste"', '</section>'), $images);

        expect($images[0])->toHaveCount(3)
            ->and($images[0][0])->toContain('loading="eager"')->toContain('fetchpriority="high"')
            ->and($images[0][1])->toContain('loading="lazy"')->not->toContain('fetchpriority');

        foreach ($images[0] as $img) {
            expect($img)->toContain('alt=""')->toContain('width="640"')->toContain('height="360"');
        }
    });

    it('prend l’illustration, sinon la couverture de la thématique liée, sinon l’image par défaut', function (): void {
        $thematique = Thematique::factory()->create(['cover_path' => 'assets/images/xomiton/covers/th-5.jpg']);
        Resource::factory()->create(['title' => 'Avec image', 'image_path' => 'assets/images/xomiton/covers/th-2.jpg', 'published_at' => now()->subDays(1)]);
        Resource::factory()->forThematique($thematique)->create(['title' => 'Avec thématique', 'published_at' => now()->subDays(2)]);
        Resource::factory()->create(['title' => 'Sans image', 'image_path' => 'assets/images/introuvable.jpg', 'published_at' => now()->subDays(3)]);
        preg_match_all('~<img [^>]*src="([^"]+)"~', resBetween(resHtml('/ressources'), 'id="liste"', '</section>'), $images);

        expect($images[1])->toBe([asset('assets/images/xomiton/covers/th-2.jpg'), asset('assets/images/xomiton/covers/th-5.jpg'), asset(Resource::DEFAULT_COVER)]);
    });

    it('annonce l’état vide, avec ou sans filtre', function (): void {
        $empty = resHtml('/ressources');
        expect($empty)->toContain('Les ressources arrivent bientôt.')->not->toContain('Réinitialiser les filtres');

        Resource::factory()->create();
        $none = resHtml('/ressources?q=zzz');
        expect($none)->toContain('Aucune ressource ne correspond à votre recherche.')
            ->toContain('href="'.route('ressources.index').'">Réinitialiser les filtres</a>');
    });

    it('liste les six fiches PDF les plus récentes à télécharger, seulement sans filtre', function (): void {
        $vbg = resCategory('vbg', ['label' => 'VBG et violences']);
        $pdfs = Resource::factory()->pdf()->for($vbg)->count(7)->sequence(fn ($sequence) => ['published_at' => now()->subDays($sequence->index + 1), 'file_size' => 860_160])->create();
        Resource::factory()->pdf()->create(['file_path' => null, 'title' => 'PDF sans fichier']);
        $html = resHtml('/ressources');
        $files = resBetween($html, 'id="fiches"', '</section>');

        expect(substr_count($files, '<li class="list-group-item'))->toBe(6)
            ->and($files)->toContain('Fiches à télécharger')
            ->toContain('Document PDF · '.resSize(860_160).' · VBG et violences')
            ->toContain('href="'.route('ressources.download', $pdfs[0]->slug).'" rel="nofollow" download')
            ->toContain('<span class="visually-hidden"> '.e(resFr('« '.$pdfs[0]->title.' » (Document PDF, '.resSize(860_160).')')).'</span>')
            ->not->toContain($pdfs[6]->slug)
            ->not->toContain('PDF sans fichier')
            ->not->toContain('text-danger');

        foreach (['?q=fiche', '?type=pdf', '?cat=vbg'] as $filter) {
            expect(resHtml('/ressources'.$filter))->not->toContain('id="fiches"');
        }
    });

    it('n’affiche pas « Fiches à télécharger » sans PDF publié', function (): void {
        Resource::factory()->create();

        expect(resHtml('/ressources'))->not->toContain('id="fiches"');
    });

    it('affiche 3 à 5 questions de la catégorie filtrée en FAQ et en FAQPage, ou rien', function (): void {
        $vbg = resCategory('vbg', ['label' => 'Violences']);
        Resource::factory()->for($vbg)->create();
        Faq::factory()->count(4)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create(['category_id' => $vbg->id]);
        Faq::factory()->unpublished()->create(['category_id' => $vbg->id, 'question' => 'Question cachée ?']);

        $html = resHtml('/ressources?cat=vbg');
        $faq = resNode($html, 'FAQPage');

        expect(resBetween($html, 'id="faq"', '</section>'))->toContain(e(resFr('Questions fréquentes : Violences')))
            ->and(substr_count(resBetween($html, 'id="faq"', '</section>'), 'accordion-button'))->toBe(4)
            ->and($html)->not->toContain('Question cachée')
            ->and($faq['mainEntity'])->toHaveCount(4)
            ->and(resHtml('/ressources'))->not->toContain('id="faq"');

        Faq::query()->where('category_id', $vbg->id)->limit(2)->delete();

        expect(resHtml('/ressources?cat=vbg'))->not->toContain('id="faq"')->not->toContain('"FAQPage"');
    });

    it('publie un CollectionPage + ItemList, le fil d’Ariane JSON-LD et aucun Article sur la liste', function (): void {
        $vbg = resCategory('vbg', ['label' => 'Violences']);
        Resource::factory()->for($vbg)->count(3)->create();
        $html = resHtml('/ressources');
        $collection = resNode($html, 'CollectionPage');

        expect($collection['url'])->toBe(url('/ressources'))
            ->and($collection['mainEntity']['@type'])->toBe('ItemList')
            ->and($collection['mainEntity']['numberOfItems'])->toBe(3)
            ->and($collection['mainEntity']['itemListElement'])->toHaveCount(3)
            ->and(resNode($html, 'WebPage'))->not->toBeNull()
            ->and(resNode($html, 'Article'))->toBeNull()
            ->and(collect(resNode($html, 'BreadcrumbList')['itemListElement'])->pluck('name')->all())->toBe(['Accueil', 'Ressources'])
            ->and(collect(resNode(resHtml('/ressources?cat=vbg'), 'BreadcrumbList')['itemListElement'])->pluck('name')->all())->toBe(['Accueil', 'Ressources', 'Violences'])
            ->and(resNode($html, 'WebSite')['potentialAction']['target'])->toBe(route('ressources.index').'?q={search_term_string}');
    });

    it('propose l’assistant et l’inscription aux visiteur·ses, sans promesse « 24 h/24 » non requalifiée', function (): void {
        $assistant = resBetween(resHtml('/ressources'), 'id="assistant"', '</section>');

        expect($assistant)
            ->toContain('réponse humaine sous 24 h')
            ->toContain('data-bs-target="#offcanvasChat"')
            ->toContain('href="'.route('signaler', ['tab' => 'besoin']).'"')
            ->toContain('href="'.route('inscription').'">Créer un compte</a>');
    });

    it('garde le gabarit public pour un·e membre, sans inscription ni indexation, avec le SOS flottant', function (): void {
        $this->actingAs(User::factory()->create());
        $html = resHtml('/ressources');

        expect($html)->toContain('id="pubHeader"')
            ->toContain('<meta name="robots" content="noindex, nofollow">')
            ->toContain('xm-sos-fab')
            ->not->toContain('href="'.route('inscription').'"');
    });
});

describe('hors connexion', function (): void {
    it('rend un interrupteur par ressource non marquée, avec le contrat du service worker', function (): void {
        $pdf = Resource::factory()->pdf()->create(['is_offline_available' => false, 'title' => 'Fiche à garder']);
        $guide = Resource::factory()->ofKind(ResourceKind::Guide)->create();
        $html = resHtml('/ressources');

        expect($html)
            ->toContain('x-data="xmOfflineLibrary(')
            ->toContain('role="switch" id="off-'.$pdf->slug.'"')
            ->toContain('data-offline-slug="'.$pdf->slug.'" data-offline-url="'.route('ressources.show', $pdf->slug).'" data-offline-file="'.route('ressources.download', $pdf->slug).'"')
            ->toContain('data-offline-url="'.route('ressources.show', $guide->slug).'" data-offline-title=')
            ->toContain(e(resFr(': rendre « Fiche à garder » disponible hors connexion')))
            ->toContain('aria-controls="liste" aria-pressed="false"')
            // Nom accessible stable (action), suivi du compteur visible ; plus de title changeant.
            ->toContain('<span class="visually-hidden">Afficher seulement les ressources enregistrées&#160;: </span><span x-text="countLabel()">Aucune ressource hors connexion</span>')
            ->not->toContain('x-bind:title=')
            ->toContain('id="offEmpty" role="status"')
            ->toContain(e(OfflineLibrary::STORAGE_KEY))
            ->and(substr_count($html, 'role="switch"'))->toBe(2);
    });

    it('liste en mode « enregistrées seulement » les ressources marquées, même hors des cartes chargées', function (): void {
        $marked = Resource::factory()->pdf()->create(['is_offline_available' => true, 'title' => 'Plan de sécurité', 'published_at' => now()->subYear()]);
        Resource::factory()->count(6)->create(['published_at' => now()->subDay()]);
        $html = resHtml('/ressources');
        // @js écrit JSON.parse('…') (guillemets en \u0022) : décoder la chaîne JavaScript, puis le JSON.
        preg_match("~x-data=\"xmOfflineLibrary\\(JSON\\.parse\\('(.*?)'\\)\\)\"~s", $html, $matches);
        $options = json_decode((string) json_decode('"'.($matches[1] ?? '').'"'), true, flags: JSON_THROW_ON_ERROR);

        expect(resBetween($html, 'id="liste"', '</section>'))->not->toContain('Plan de sécurité')
            ->toContain('<template x-for="entry in savedList()" :key="entry.slug">')
            ->toContain('id="offList" aria-labelledby="offList-titre"')
            ->toContain('Ressources enregistrées pour le hors connexion')
            ->toContain('x-on:click="remove(entry.slug)"')
            ->and($options['marked'])->toBe([[
                'slug' => $marked->slug,
                'url' => route('ressources.show', $marked->slug),
                'file' => route('ressources.download', $marked->slug),
                'title' => 'Plan de sécurité',
            ]])
            ->and($options['texts']['removeHint'])->toBe(resFr(' « :title » du hors connexion'));
    });

    it('n’affiche aucun badge « Disponible hors connexion » ni aucune promesse quand la bibliothèque est désactivée', function (): void {
        config(['xomiton.offline.library_enabled' => false]);
        $marked = Resource::factory()->pdf()->create(['is_offline_available' => true]);

        expect(resHtml('/ressources'))->not->toContain('Disponible hors connexion')->not->toContain('offList')
            ->and(resHtml('/ressources/'.$marked->slug))->not->toContain('Disponible hors connexion')->not->toContain('id="hors-ligne"');
    });

    it('active la bibliothèque hors connexion par défaut depuis la livraison du service worker (P5-10)', function (): void {
        $config = require base_path('config/xomiton.php');

        expect($config['offline']['library_enabled'])->toBeTrue()
            ->and(file_get_contents(base_path('.env.example')))->toContain("XOMITON_OFFLINE_LIBRARY=true\n");
    });

    it('affiche « Disponible hors connexion » sans interrupteur pour une ressource marquée par l’équipe', function (): void {
        Resource::factory()->pdf()->create(['is_offline_available' => true]);
        $html = resHtml('/ressources');

        expect(resBetween($html, 'id="liste"', '</section>'))->toContain('Disponible hors connexion')
            ->and($html)->not->toContain('role="switch"');
    });

    it('ne rend ni compteur, ni interrupteur, ni composant quand la bibliothèque est désactivée', function (): void {
        config(['xomiton.offline.library_enabled' => false]);
        $resource = Resource::factory()->create();

        expect(resHtml('/ressources'))->not->toContain('role="switch"')->not->toContain('xmOfflineLibrary')->not->toContain('id="offEmpty"')
            ->and(resHtml('/ressources/'.$resource->slug))->not->toContain('id="hors-ligne"')->not->toContain('xmOfflineLibrary');
    });

    it('propose la lecture hors connexion sur la fiche, avec le même composant', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->pdf()->withFile()->create(['is_offline_available' => false]);
        $marked = Resource::factory()->pdf()->create(['is_offline_available' => true]);

        expect(resBetween(resHtml('/ressources/'.$resource->slug), 'id="hors-ligne"', '</section>'))
            ->toContain('Lecture hors connexion')
            ->toContain('data-offline-file="'.route('ressources.download', $resource->slug).'"')
            ->and(resBetween(resHtml('/ressources/'.$marked->slug), 'id="hors-ligne"', '</section>'))
            ->toContain('Disponible hors connexion')->not->toContain('role="switch"');

        // Média : le fichier à garder est la source du lecteur, pas le téléchargement redirigé.
        $video = Resource::factory()->withMedia()->create();
        expect(resBetween(resHtml('/ressources/'.$video->slug), 'id="hors-ligne"', '</section>'))
            ->toContain('data-offline-file="'.route('ressources.stream', $video->slug).'"');
    });
});

describe('fiche /ressources/{slug}', function (): void {
    it('répond 200 avec fil d’Ariane, h1, méta, bouton de téléchargement et JSON-LD DigitalDocument', function (): void {
        Storage::fake('local');
        $droits = resCategory('droits', ['label' => 'Droits']);
        $resource = Resource::factory()->pdf()->withFile()->for($droits)->create([
            'title' => 'Fiche : mes droits', 'file_size' => 1_258_291, 'published_at' => '2026-10-02 09:00:00', 'downloads_count' => 3,
        ]);
        $html = resHtml('/ressources/'.$resource->slug);
        $size = resSize(1_258_291);
        $document = resNode($html, 'DigitalDocument');

        expect($html)
            ->toContain('<h1 class="h4" id="ressource-titre">'.e($resource->title).'</h1>')
            ->toContain('<li class="breadcrumb-item"><a href="'.route('home').'">Accueil</a></li>')
            ->toContain('<li class="breadcrumb-item"><a href="'.route('ressources.index').'">Ressources</a></li>')
            ->toContain('<li class="breadcrumb-item"><a href="'.e(route('ressources.index', ['cat' => 'droits'])).'">Droits</a></li>')
            ->toContain('<li class="breadcrumb-item active" aria-current="page">'.e($resource->title).'</li>')
            ->toContain('href="'.route('ressources.download', $resource->slug).'" rel="nofollow" download')
            ->toContain(e('Télécharger (Document PDF, '.$size.')'))
            ->toContain('Publié le 2 octobre 2026')
            ->toContain('3 téléchargements')
            ->toContain(e(__('pages/ressources.legal_notice')))
            ->toContain('<link rel="preload" as="image" href="'.$resource->cover_url.'"')
            ->toContain('<meta property="og:type" content="article">')
            ->and($document['encodingFormat'])->toBe('application/pdf')
            ->and($document['contentSize'])->toBe($size)
            ->and($document['url'])->toBe(route('ressources.download', $resource->slug))
            ->and(collect(resGraph($html))->where('@type', 'WebPage'))->toHaveCount(1)
            ->and(resNode($html, 'BreadcrumbList')['itemListElement'])->toHaveCount(4);
    });

    it('rend le corps d’un guide non échappé, nettoyé à l’écriture, en Article', function (): void {
        $resource = Resource::factory()->ofKind(ResourceKind::Guide)->create(['body' => '<p>Corps du guide</p><script>alert(1)</script>']);
        $html = resHtml('/ressources/'.$resource->slug);

        expect($html)->toContain('<div class="xm-prose mt-4"><p>Corps du guide</p></div>')
            ->not->toContain('alert(1)')
            ->and(resNode($html, 'Article')['headline'])->toBe($resource->title)
            ->and($html)->not->toContain('download>');
    });

    it('ouvre un lien externe dans une nouvelle fenêtre annoncée, sans iframe ni téléchargement', function (): void {
        $resource = Resource::factory()->link()->create(['external_url' => 'https://www.gouv.bj/']);
        $html = resHtml('/ressources/'.$resource->slug);

        expect($html)->toContain('href="https://www.gouv.bj/" target="_blank" rel="noopener noreferrer nofollow"')
            ->toContain('(nouvelle fenêtre)')
            ->toContain('<meta property="og:type" content="website">')
            ->not->toContain('<iframe')
            // Les règles de préchargement (Speculation Rules) citent le motif d'exclusion « /ressources/*/telecharger » : hors script.
            ->and(preg_replace('#<script type="speculationrules".*?</script>#s', '', $html))->not->toContain('/telecharger')
            ->and(collect(resGraph($html))->pluck('@type')->all())->toBe(['Organization', 'WebSite', 'WebPage', 'BreadcrumbList']);
    });

    it('lit une vidéo hébergée par la source directe, sans la précharger, et affiche sa transcription', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->withMedia()->create(['body' => '<p>Texte de la vidéo</p>', 'duration_minutes' => 4]);
        $html = resHtml('/ressources/'.$resource->slug);
        $video = resNode($html, 'VideoObject');

        expect($html)->toContain('<video class="w-100 rounded" controls preload="none" src="'.route('ressources.stream', $resource->slug).'"')
            ->toContain('Lire la ressource')
            ->toContain('<h2 class="h5 mt-4">Transcription</h2>')
            ->toContain('Texte de la vidéo')
            ->and($video['duration'])->toBe('PT4M')
            ->and($video['contentUrl'])->toBe(route('ressources.stream', $resource->slug));

        $audio = Resource::factory()->withMedia(ResourceKind::Audio)->create();
        expect(resHtml('/ressources/'.$audio->slug))->toContain('<audio class="w-100" controls preload="none" src="'.route('ressources.stream', $audio->slug).'"');
    });

    it('ne rend jamais en lien une adresse externe qui n’est pas http(s)', function (string $url): void {
        $resource = Resource::factory()->link()->create(['external_url' => $url, 'title' => 'Lien douteux']);

        expect(resHtml('/ressources/'.$resource->slug))->not->toContain('target="_blank" rel="noopener noreferrer nofollow"')
            ->not->toContain(e($url))
            ->and(resHtml('/ressources'))->not->toContain('bi-box-arrow-up-right me-1');
    })->with(['javascript:alert(1)', 'data:text/html;base64,PHNjcmlwdD4=', 'JAVASCRIPT://example.org/%0aalert(1)', 'ftp://example.org/x', '//example.org/x']);

    it('renvoie une vidéo externe vers sa source, en VideoObject', function (): void {
        $resource = Resource::factory()->video()->create(['duration_minutes' => 12]);
        $html = resHtml('/ressources/'.$resource->slug);
        $video = resNode($html, 'VideoObject');

        expect($html)->not->toContain('<video')->toContain('target="_blank"')
            ->and($video['duration'])->toBe('PT12M')
            ->and($video['contentUrl'])->toBe($resource->external_url)
            ->and($video['uploadDate'])->not->toBeEmpty();
    });

    it('répond 404 pour une ressource non publiée ou inconnue', function (string $state): void {
        $resource = match ($state) {
            'brouillon' => Resource::factory()->draft()->create(),
            'masquee' => Resource::factory()->hidden()->create(),
            'programmee' => Resource::factory()->scheduled()->create(),
            'supprimee' => tap(Resource::factory()->create())->delete(),
            default => null,
        };

        $this->get('/ressources/'.($resource?->slug ?? 'introuvable'))->assertNotFound();
    })->with(['brouillon', 'masquee', 'programmee', 'supprimee', 'inconnue']);

    it('redirige une actualité vers /actualites en 301', function (): void {
        $article = Resource::factory()->article()->create();

        $this->get('/ressources/'.$article->slug)->assertStatus(301)->assertRedirect(route('actualites.show', $article->slug));
    });

    it('liste les ressources de la même catégorie, complétées par les autres, et la thématique liée publiée', function (): void {
        $vbg = resCategory('vbg');
        $thematique = Thematique::factory()->create(['title' => 'Fiche thématique liée']);
        $current = Resource::factory()->for($vbg)->forThematique($thematique)->create(['published_at' => now()->subDays(10)]);
        $same = Resource::factory()->for($vbg)->create(['title' => 'Même catégorie', 'published_at' => now()->subDays(5)]);
        $other = Resource::factory()->create(['title' => 'Autre catégorie', 'published_at' => now()->subDays(1)]);
        Resource::factory()->article()->for($vbg)->create(['title' => 'Actualité exclue']);
        $html = resHtml('/ressources/'.$current->slug);
        $related = resBetween($html, 'id="ressource-liees"', '</section>');

        expect($related)->toContain('Même catégorie')->toContain('Autre catégorie')->not->toContain('Actualité exclue')
            ->and(strpos($related, 'Même catégorie'))->toBeLessThan(strpos($related, 'Autre catégorie'))
            ->and(resBetween($html, 'id="ressource-thematique"', '</section>'))->toContain('href="'.route('thematiques.show', $thematique).'">Fiche thématique liée</a>');

        $thematique->update(['status' => 'brouillon']);
        expect(resHtml('/ressources/'.$current->slug))->not->toContain('id="ressource-thematique"');
    });

    it('affiche la mention juridique selon les catégories de la configuration', function (): void {
        $droits = Resource::factory()->for(resCategory('droits'))->create();
        $sante = Resource::factory()->for(resCategory('sante'))->create();
        $notice = e(__('pages/ressources.legal_notice'));

        expect(resHtml('/ressources/'.$droits->slug))->toContain($notice)
            ->and(resHtml('/ressources/'.$sante->slug))->not->toContain($notice);

        config(['xomiton.content.legal_notice_categories' => ['sante']]);

        expect(resHtml('/ressources/'.$droits->slug))->not->toContain($notice)
            ->and(resHtml('/ressources/'.$sante->slug))->toContain($notice);
    });

    it('omet le maillon catégorie quand la catégorie n’est pas une puce de la liste', function (): void {
        $resource = Resource::factory()->for(Category::factory()->ofKind(Category::KIND_FAQ)->create(['label' => 'Aide produit']))->create();
        $html = resHtml('/ressources/'.$resource->slug);

        expect(resNode($html, 'BreadcrumbList')['itemListElement'])->toHaveCount(3)
            ->and($html)->not->toContain('?cat=');
    });

    it('propose les liens d’aide : urgences, signalement, SOS en rouge doux seulement', function (): void {
        $resource = Resource::factory()->create();
        $aide = resBetween(resHtml('/ressources/'.$resource->slug), 'id="aide"', '</section>');

        expect($aide)->toContain('href="'.route('urgences').'"')->toContain('href="'.route('signaler').'"')
            ->toContain('class="btn btn-sm btn-danger-soft text-start" href="'.route('sos').'"')
            ->and(substr_count($aide, 'danger'))->toBe(1);
    });

    it('borne le titre et complète la description courte', function (): void {
        $resource = Resource::factory()->create([
            'title' => 'Un titre de ressource vraiment très long qui dépasse nettement la limite des cinquante caractères',
            'summary' => 'Résumé court.',
            'body' => null,
        ]);
        $html = resHtml('/ressources/'.$resource->slug);
        preg_match('~<title>(.*?)</title>~', $html, $title);
        preg_match('~<meta name="description" content="([^"]*)">~', $html, $description);

        expect(mb_strlen(html_entity_decode($title[1])))->toBeLessThanOrEqual(61)
            ->and(html_entity_decode($description[1]))->toContain('Ressource Xomiton pour les filles et femmes LBTQ+ au Bénin.');
    });

    it('compte la consultation sans toucher updated_at, ni pour un robot', function (): void {
        Carbon::setTestNow('2026-10-10 10:00:00');
        $resource = Resource::factory()->create(['views_count' => 0, 'published_at' => '2026-10-01 08:00:00']);
        Carbon::setTestNow('2026-10-12 10:00:00');

        resHtml('/ressources/'.$resource->slug);
        $this->get('/ressources/'.$resource->slug, ['User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1)'])->assertOk();

        $fresh = $resource->fresh();
        expect($fresh->views_count)->toBe(1)
            ->and($fresh->updated_at->toDateTimeString())->toBe('2026-10-10 10:00:00');

        Carbon::setTestNow();
    });
});

describe('téléchargement /ressources/{slug}/telecharger', function (): void {
    it('compte le téléchargement et redirige vers un lien signé périssable, jamais mis en cache', function (): void {
        Storage::fake('local');
        Carbon::setTestNow('2026-10-10 10:00:00');
        // Date fixe : la factory tire published_at avant l'heure réelle, qui peut suivre l'heure simulée.
        $resource = Resource::factory()->pdf()->withFile()->create(['downloads_count' => 0, 'published_at' => '2026-10-01 08:00:00']);
        Carbon::setTestNow('2026-10-12 10:00:00');

        $response = $this->get('/ressources/'.$resource->slug.'/telecharger')->assertStatus(302);
        $location = (string) $response->headers->get('Location');

        expect($location)->toContain('/media/local/'.$resource->file_path)->toContain('signature=')->toContain('expires=')
            ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow');

        $fresh = $resource->fresh();
        expect($fresh->downloads_count)->toBe(1)
            ->and($fresh->updated_at->toDateTimeString())->toBe('2026-10-10 10:00:00');

        $file = $this->get($location)->assertOk();
        expect($file->headers->get('Content-Type'))->toContain('application/pdf');

        $this->travel(11)->minutes();
        $this->get($location)->assertForbidden();
        $this->get('/ressources/'.$resource->slug.'/telecharger')->assertStatus(302);

        Carbon::setTestNow();
    });

    it('ne compte ni robot, ni HEAD, ni requête de plage suivante d’un lecteur', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->ofKind(ResourceKind::Audio)->withFile()->create(['downloads_count' => 0]);
        $uri = '/ressources/'.$resource->slug.'/telecharger';

        $this->get($uri, ['User-Agent' => 'curl/8.4.0'])->assertStatus(302);
        $this->call('HEAD', $uri)->assertStatus(302);
        $this->get($uri, ['Range' => 'bytes=5000-'])->assertStatus(302);
        // Sonde de Safari avant la lecture : jamais comptée.
        $this->get($uri, ['Range' => 'bytes=0-1'])->assertStatus(302);
        $this->get($uri, ['Purpose' => 'prefetch'])->assertStatus(302);
        expect($resource->fresh()->downloads_count)->toBe(0);

        $this->get($uri, ['Range' => 'bytes=0-'])->assertStatus(302);
        expect($resource->fresh()->downloads_count)->toBe(1);
    });

    it('refuse une ressource sans fichier, un lien, un brouillon ou une actualité (404)', function (): void {
        Storage::fake('local');
        $cases = [
            Resource::factory()->ofKind(ResourceKind::Guide)->create(['file_path' => 'resources/x.pdf']),
            Resource::factory()->link()->create(),
            Resource::factory()->pdf()->draft()->create(),
            Resource::factory()->pdf()->create(['file_path' => null]),
            Resource::factory()->article()->create(['file_path' => 'resources/y.pdf']),
        ];

        foreach ($cases as $resource) {
            $this->get('/ressources/'.$resource->slug.'/telecharger')->assertNotFound();
        }
    });

    it('signe le lien même si le fichier manque : le lien signé répond alors 404', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->pdf()->create();

        $location = (string) $this->get('/ressources/'.$resource->slug.'/telecharger')->assertStatus(302)->headers->get('Location');

        $this->get($location)->assertNotFound();
    });

    it('limite les téléchargements par IP et par ressource (limiteur nommé, seuil configurable)', function (): void {
        Storage::fake('local');
        config(['xomiton.rate_limits.resource_download_per_minute' => 3]);
        [$resource, $other] = Resource::factory()->pdf()->withFile()->count(2)->create();

        for ($i = 0; $i < 3; $i++) {
            $this->get('/ressources/'.$resource->slug.'/telecharger')->assertStatus(302);
        }

        $this->get('/ressources/'.$resource->slug.'/telecharger')->assertStatus(429);
        // Une autre ressource (précache du service worker, CGNAT) garde son propre quota.
        $this->get('/ressources/'.$other->slug.'/telecharger')->assertStatus(302);
        expect(Route::getRoutes()->getByName('ressources.download')->gatherMiddleware())->toContain('throttle:resource_download')
            ->and(Route::getRoutes()->getByName('ressources.stream')->gatherMiddleware())->toContain('throttle:resource_download')
            ->and(config('xomiton.rate_limits'))->toHaveKey('resource_download_per_minute');
    });

    it('déclare la route des fichiers signés au démarrage et l’exclut de robots.txt', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);

        expect(Route::has('media.signed'))->toBeTrue()
            ->and((string) $this->get('/robots.txt')->assertOk()->getContent())->toContain("Disallow: /media/\n")->not->toContain("Disallow: /media\n");
    });
});

describe('lecture /ressources/{slug}/lire', function (): void {
    it('sert la vidéo directement, sans redirection ni signature, avec Accept-Ranges et son vrai type', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->withMedia()->create();
        $response = $this->get('/ressources/'.$resource->slug.'/lire')->assertOk();

        expect($response->headers->get('Content-Type'))->toBe('video/mp4')
            ->and($response->headers->get('Accept-Ranges'))->toBe('bytes')
            ->and($response->headers->get('Content-Length'))->toBe('2048')
            ->and((string) $response->headers->get('Cache-Control'))->toContain('no-store')
            ->and($response->headers->get('X-Robots-Tag'))->toBe('noindex, nofollow')
            ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
            ->and((string) $response->headers->get('Content-Disposition'))->toContain('inline')->toContain($resource->slug.'.mp4')
            ->and($response->headers->has('Location'))->toBeFalse();
    });

    it('répond 206 à une requête de plage (iOS Safari, avance dans le média)', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->withMedia(ResourceKind::Audio)->create();
        $uri = '/ressources/'.$resource->slug.'/lire';

        $probe = $this->get($uri, ['Range' => 'bytes=0-1'])->assertStatus(206);
        expect($probe->headers->get('Content-Range'))->toBe('bytes 0-1/2048')
            ->and($probe->headers->get('Content-Length'))->toBe('2')
            ->and($probe->headers->get('Content-Type'))->toBe('audio/mpeg');

        $seek = $this->get($uri, ['Range' => 'bytes=1024-'])->assertStatus(206);
        expect($seek->headers->get('Content-Range'))->toBe('bytes 1024-2047/2048')
            ->and($seek->headers->get('Content-Length'))->toBe('1024');

        $full = $this->get($uri, ['Range' => 'bytes=0-'])->assertStatus(206);
        expect($full->headers->get('Content-Range'))->toBe('bytes 0-2047/2048');

        $this->get($uri, ['Range' => 'bytes=4096-5000'])->assertStatus(416);
    });

    it('compte une lecture une fois : ni sonde, ni saut, ni robot, ni HEAD, ni précache', function (): void {
        Storage::fake('local');
        $resource = Resource::factory()->withMedia()->create(['downloads_count' => 0]);
        $uri = '/ressources/'.$resource->slug.'/lire';

        $this->get($uri, ['Range' => 'bytes=0-1'])->assertStatus(206);
        $this->get($uri, ['Range' => 'bytes=1024-'])->assertStatus(206);
        $this->get($uri, ['User-Agent' => 'curl/8.4.0'])->assertOk();
        $this->get($uri, ['Purpose' => 'prefetch'])->assertOk();
        $this->call('HEAD', $uri)->assertOk();
        expect($resource->fresh()->downloads_count)->toBe(0);

        $this->get($uri, ['Range' => 'bytes=0-'])->assertStatus(206);
        expect($resource->fresh()->downloads_count)->toBe(1);

        // Safari : la sonde ne compte pas, la plage complète qui suit compte.
        $this->get($uri, ['Range' => 'bytes=0-2047'])->assertStatus(206);
        expect($resource->fresh()->downloads_count)->toBe(2);
    });

    it('refuse une ressource qui n’est pas un média hébergé lisible (404)', function (): void {
        Storage::fake('local');
        $cases = [
            Resource::factory()->pdf()->withFile()->create(),
            Resource::factory()->ofKind(ResourceKind::Guide)->create(),
            Resource::factory()->video()->create(),
            Resource::factory()->withMedia()->draft()->create(),
            // Fichier absent, ou contenu qui n'est pas un média (PDF déguisé en vidéo).
            Resource::factory()->ofKind(ResourceKind::Video)->create(['file_path' => 'resources/absent.mp4']),
            Resource::factory()->ofKind(ResourceKind::Video)->withFile()->create(['file_path' => 'resources/deguise.mp4']),
        ];

        foreach ($cases as $resource) {
            $this->get('/ressources/'.$resource->slug.'/lire')->assertNotFound();
        }
    });
});

describe('performances', function (): void {
    it('lit la liste en 8 requêtes au plus et la fiche en 7 au plus, sans chargement paresseux', function (): void {
        $vbg = resCategory('vbg');
        $thematique = Thematique::factory()->create();
        $resources = Resource::factory()->pdf()->for($vbg)->forThematique($thematique)->count(8)->create();
        Resource::factory()->count(3)->create();
        Faq::factory()->count(4)->create(['category_id' => $vbg->id]);
        app(Referentials::class)->categories();

        Model::preventLazyLoading();
        $pageQueries = static fn (): int => collect(DB::getQueryLog())
            ->filter(static fn (array $entry): bool => preg_match('/"(thematiques|categories|resources|faqs)"/', $entry['query']) === 1)
            ->count();

        DB::enableQueryLog();
        resHtml('/ressources');
        $list = $pageQueries();
        DB::flushQueryLog();
        resHtml('/ressources?cat=vbg');
        $filtered = $pageQueries();
        DB::flushQueryLog();
        resHtml('/ressources/'.$resources->first()->slug);
        $show = $pageQueries();
        DB::disableQueryLog();
        Model::preventLazyLoading(false);

        expect($list)->toBeLessThanOrEqual(8)->and($filtered)->toBeLessThanOrEqual(8)->and($show)->toBeLessThanOrEqual(7);
    });

    it('lit les catégories dans le référentiel en cache, invalidé quand une catégorie change', function (): void {
        $vbg = resCategory('violences', ['label' => 'Violences']);
        Resource::factory()->for($vbg)->create();
        resHtml('/ressources');

        DB::enableQueryLog();
        resHtml('/ressources');
        $categoryQueries = collect(DB::getQueryLog())->filter(static fn (array $entry): bool => str_contains($entry['query'], 'from "categories" where "is_active"'))->count();
        DB::disableQueryLog();

        $vbg->update(['label' => 'Violences et recours']);

        expect($categoryQueries)->toBe(0)
            ->and(resBetween(resHtml('/ressources'), 'id="ressources-filtres"', '</nav>'))->toContain('Violences et recours');
    });
});

describe('données semées, redirections et sitemap', function (): void {
    it('sème des ressources de démonstration publiées, avec de vrais PDF, idempotentes, hors production seulement', function (): void {
        Storage::fake('local');
        $this->seed(ContentSeeder::class);
        $this->seed(DemoResourcesSeeder::class);
        $this->seed(DemoResourcesSeeder::class);

        $pdfs = Resource::query()->catalog()->where('kind', ResourceKind::Pdf)->get();
        $html = resHtml('/ressources');

        expect(Resource::query()->count())->toBe(12)
            ->and(Resource::query()->catalog()->count())->toBe(11)
            ->and($pdfs)->toHaveCount(6)
            ->and($pdfs->every(fn (Resource $pdf): bool => Storage::disk('local')->exists((string) $pdf->file_path) && $pdf->file_size > 0))->toBeTrue()
            ->and(Resource::query()->offlineAvailable()->count())->toBe(2)
            ->and(Resource::query()->whereNull('category_id')->count())->toBe(0)
            ->and($html)->toContain('id="fiches"')->not->toContain('brouillon');

        foreach (Resource::query()->get() as $resource) {
            expect(mb_strlen((string) $resource->meta_title))->toBeLessThanOrEqual(50)
                ->and(mb_strlen((string) $resource->meta_description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160);
        }
    });

    it('respecte la typographie française dans les textes de la page', function (): void {
        foreach (['assistant_title', 'help_title', 'faq_title', 'cover_alt', 'end_of_list'] as $key) {
            $line = (string) __('pages/ressources.'.$key);
            expect($line)->not->toMatch('/ [?!:;](?![a-z])/u')->not->toContain("'");
        }
    });

    it('redirige les anciennes adresses du prototype en 301', function (): void {
        $this->seed(ContentSeeder::class);
        $third = Thematique::query()->where('cover_path', 'like', '%/covers/th-3.jpg')->firstOrFail();

        $this->get('/ressources.php?q=droit&cat=vbg')->assertStatus(301)->assertRedirect(route('ressources.index', ['q' => 'droit', 'cat' => 'vbg']));
        $this->get('/ressources.php?cat=../x')->assertStatus(301)->assertRedirect(route('ressources.index'));
        $this->get('/ressource.php?id=3')->assertStatus(301)->assertRedirect(route('thematiques.show', $third));
        $this->get('/ressource.php')->assertStatus(301)->assertRedirect(route('ressources.index'));
        $this->get('/ressource.php?id=999')->assertStatus(301)->assertRedirect(route('ressources.index'));
    });

    it('fait apparaître la liste et les ressources publiées dans le sitemap, sans actualité ni téléchargement', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);
        $published = Resource::factory()->pdf()->create(['slug' => 'fiche-publiee']);
        Resource::factory()->draft()->create(['slug' => 'ressource-brouillon']);
        Resource::factory()->article()->create(['slug' => 'actualite-du-mois']);
        $root = rtrim((string) config('app.url'), '/');

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        expect($xml)
            ->toContain('<loc>'.$root.'/ressources</loc>')
            ->toContain('<loc>'.$root.'/ressources/fiche-publiee</loc>')
            ->toContain($published->updated_at->toAtomString())
            ->not->toContain('ressource-brouillon')
            ->not->toContain('/ressources/actualite-du-mois')
            ->not->toContain('/telecharger')
            ->not->toContain('/lire')
            ->not->toContain('/media/')
            ->not->toContain('?q=')->not->toContain('?type=')->not->toContain('?cat=');
    });
});
