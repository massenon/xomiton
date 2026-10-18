<?php

declare(strict_types=1);

use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Models\Category;
use App\Models\DirectoryContact;
use App\Models\Faq;
use App\Models\Resource;
use App\Models\Thematique;
use App\Models\User;
use App\Support\FrenchTypography;
use App\Support\Settings;
use Database\Seeders\ContentSeeder;
use Database\Seeders\DemoDirectorySeeder;
use Database\Seeders\ThematiqueFaqSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * Thématiques publiques `/thematiques` et `/thematiques/{slug}` (P4-02) : pages publiques indexables (Q19 a), filtres,
 * recherche et « Charger plus » côté serveur, fiche (fil d'Ariane, accordéon, avertissement, contacts d'annuaire
 * **publics seulement**, « J’ai besoin d’aide », ressource principale, fiches liées, FAQ 3–5), JSON-LD, redirections du
 * prototype et présence dans le sitemap une fois les routes livrées.
 */

const TH_CARD = 'class="card h-100 xm-card-hover';

function thCategory(string $slug, array $attributes = []): Category
{
    return Category::factory()->ofKind(Category::KIND_THEMATIQUE)->create(['slug' => $slug, 'label' => ucfirst($slug), ...$attributes]);
}

/** Texte attendu en typographie française (U+00A0 avant « ? ! : ; »), comme lang/fr. */
function thFr(string $text): string
{
    return FrenchTypography::apply($text);
}

function thHtml(string $uri): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** @return list<array<string, mixed>> le graphe JSON-LD de la page. */
function thGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

/** @return array<string, mixed>|null premier nœud du type demandé. */
function thNode(string $html, string $type): ?array
{
    return collect(thGraph($html))->firstWhere('@type', $type);
}

function thBetween(string $html, string $start, string $end): string
{
    $from = strpos($html, $start);
    expect($from)->not->toBeFalse("Marqueur absent : $start");
    $to = strpos($html, $end, (int) $from);
    expect($to)->not->toBeFalse("Marqueur absent : $end");

    return substr($html, (int) $from, (int) $to - (int) $from);
}

describe('liste /thematiques', function (): void {
    it('répond 200, publique et indexable, avec titre, h1, recherche et grille', function (): void {
        Thematique::factory()->create();
        $html = thHtml('/thematiques');

        expect($html)
            ->toContain('<title>'.e(__('seo.pages.thematiques.title')).'</title>')
            ->toContain('<h1 class="h4 mb-1" id="thematiques-titre">Thématiques</h1>')
            ->toContain('role="search"')
            ->toContain('<label class="visually-hidden" for="th-q">')
            ->toContain('<span class="visually-hidden">Rechercher</span>')
            ->toContain('id="liste"')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->not->toContain('xm-sidenav')
            ->and(preg_match_all('/<h1[\s>]/', $html))->toBe(1);
    });

    it('n’affiche que les fiches publiées', function (): void {
        $published = Thematique::factory()->create(['slug' => 'fiche-publiee']);
        Thematique::factory()->draft()->create(['slug' => 'fiche-brouillon']);
        Thematique::factory()->hidden()->create(['slug' => 'fiche-masquee']);
        Thematique::factory()->scheduled()->create(['slug' => 'fiche-programmee']);
        Thematique::factory()->create(['slug' => 'fiche-supprimee'])->delete();

        $html = thHtml('/thematiques');

        expect($html)->toContain('href="'.route('thematiques.show', $published).'"')
            ->not->toContain('/thematiques/fiche-brouillon')
            ->not->toContain('/thematiques/fiche-masquee')
            ->not->toContain('/thematiques/fiche-programmee')
            ->not->toContain('/thematiques/fiche-supprimee')
            ->and(substr_count($html, TH_CARD))->toBe(1);
    });

    it('compte toutes les fiches publiées dans l’en-tête, au singulier comme au pluriel', function (): void {
        Thematique::factory()->create();
        expect(thHtml('/thematiques'))->toContain('1 fiche pédagogique pour comprendre, se protéger et agir.');

        Thematique::factory()->count(6)->create();
        expect(thHtml('/thematiques'))->toContain('7 fiches pédagogiques pour comprendre, se protéger et agir.');
    });

    it('affiche un filtre par catégorie active avec le nombre de fiches publiées', function (): void {
        $vbg = thCategory('vbg', ['label' => 'Violences basées sur le genre', 'icon' => 'bi-shield-exclamation', 'position' => 1]);
        $droits = thCategory('droits', ['label' => 'Droits & lois', 'icon' => 'bi-bank', 'position' => 2]);
        thCategory('archives', ['label' => 'Archives', 'is_active' => false]);
        Thematique::factory()->count(2)->for($vbg)->create();
        Thematique::factory()->draft()->for($vbg)->create();
        Thematique::factory()->for($droits)->create();

        $filters = thBetween(thHtml('/thematiques'), 'id="thematiques-filtres"', '</nav>');

        expect($filters)
            ->toContain('aria-label="Filtrer par catégorie"')
            ->toContain('Toutes (3)</a>')
            ->toContain('<i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i>Violences basées sur le genre (2)')
            ->toContain('Droits &amp; lois (1)')
            ->not->toContain('Archives')
            ->and(strpos($filters, 'Violences'))->toBeLessThan(strpos($filters, 'Droits'));
    });

    it('liste dans « Toutes » et sert une fiche de catégorie hors filtres, sans maillon vers un ?cat= en 404', function (string $case): void {
        $category = $case === 'inactive'
            ? thCategory('archives', ['label' => 'Archives', 'is_active' => false])
            : Category::factory()->ofKind(Category::KIND_RESOURCE)->create(['slug' => 'archives', 'label' => 'Archives']);
        $thematique = Thematique::factory()->for($category)->create(['title' => 'Fiche archivée']);

        expect(thHtml('/thematiques'))->toContain('href="'.route('thematiques.show', $thematique).'"');
        $this->get('/thematiques?cat=archives')->assertNotFound();

        $html = thHtml('/thematiques/'.$thematique->slug);
        $crumbs = thBetween($html, '<nav aria-label="Fil d’Ariane">', '</nav>');

        expect($html)->not->toContain('cat=archives')
            ->and($crumbs)->not->toContain('Archives')->toContain('<li class="breadcrumb-item active" aria-current="page">Fiche archivée</li>')
            ->and(collect(thNode($html, 'BreadcrumbList')['itemListElement'])->pluck('name')->all())->toBe(['Accueil', 'Thématiques', 'Fiche archivée']);
    })->with(['inactive', 'autre type']);

    it('filtre par catégorie, marque le filtre actif et garde la catégorie dans la recherche', function (): void {
        $vbg = thCategory('vbg');
        $inVbg = Thematique::factory()->for($vbg)->create();
        $other = Thematique::factory()->create();

        $html = thHtml('/thematiques?cat=vbg');

        expect($html)
            ->toContain('href="'.route('thematiques.show', $inVbg).'"')
            ->not->toContain('href="'.route('thematiques.show', $other).'"')
            ->toContain('<input type="hidden" name="cat" value="vbg">')
            ->toMatch('~<a class="btn btn-sm btn-primary" href="'.preg_quote(route('thematiques.index', ['cat' => 'vbg']), '~').'"\s+aria-current="true">~')
            ->toMatch('~<a class="btn btn-sm btn-light" href="'.preg_quote(route('thematiques.index'), '~').'"\s*>Toutes~');
    });

    it('répond 404 pour une catégorie inconnue ou mal formée', function (string $cat): void {
        thCategory('vbg');

        $this->get('/thematiques?cat='.urlencode($cat))->assertNotFound();
    })->with(['inconnue', 'VBG', '../x', str_repeat('a', 61)]);

    it('cherche dans le titre et le résumé, sans indexer les résultats', function (): void {
        $byTitle = Thematique::factory()->create(['title' => 'Le consentement expliqué', 'summary' => 'Repères simples.']);
        $bySummary = Thematique::factory()->create(['title' => 'Relations saines', 'summary' => 'Comprendre le consentement au quotidien.']);
        $other = Thematique::factory()->create(['title' => 'Hygiène menstruelle', 'summary' => 'Protections et santé.']);

        $html = thHtml('/thematiques?q=consentement');

        expect($html)
            ->toContain('href="'.route('thematiques.show', $byTitle).'"')
            ->toContain('href="'.route('thematiques.show', $bySummary).'"')
            ->not->toContain('href="'.route('thematiques.show', $other).'"')
            ->toContain('<meta name="robots" content="noindex, nofollow">')
            ->toContain('value="consentement"')
            ->toContain('<link rel="canonical" href="'.url('/thematiques').'">');

        $empty = thHtml('/thematiques?q=zzzz');

        expect($empty)
            ->toContain('Aucune thématique ne correspond à votre recherche.')
            ->toContain('Essayez un autre mot ou retirez le filtre de catégorie.')
            ->toContain('href="'.route('thematiques.index').'">Réinitialiser</a>')
            ->and(substr_count($empty, TH_CARD))->toBe(0);
    });

    it('nettoie la recherche (contrôles, espaces, 100 caractères)', function (): void {
        $html = thHtml('/thematiques?q='.urlencode("  san\u{0007}té   ".str_repeat('x', 150)));

        preg_match('~id="th-q" name="q" value="([^"]*)"~', $html, $matches);

        expect(html_entity_decode($matches[1] ?? ''))->toStartWith('san té x')->and(mb_strlen(html_entity_decode($matches[1] ?? '')))->toBe(100);
    });

    it('traite % et _ de la recherche comme du texte, pas comme des jokers', function (): void {
        $percent = Thematique::factory()->create(['title' => 'Contraception efficace à 99% des cas', 'summary' => 'Repères.']);
        $underscore = Thematique::factory()->create(['title' => 'Nommer un fichier mon_dossier', 'summary' => 'Repères.']);
        $other = Thematique::factory()->create(['title' => 'Hygiène menstruelle', 'summary' => 'Protections et santé.']);

        expect(thHtml('/thematiques?q='.urlencode('%')))
            ->toContain('href="'.route('thematiques.show', $percent).'"')
            ->not->toContain('href="'.route('thematiques.show', $underscore).'"')
            ->not->toContain('href="'.route('thematiques.show', $other).'"')
            ->and(thHtml('/thematiques?q=_'))
            ->toContain('href="'.route('thematiques.show', $underscore).'"')
            ->not->toContain('href="'.route('thematiques.show', $percent).'"')
            ->not->toContain('href="'.route('thematiques.show', $other).'"')
            ->and(substr_count(thHtml('/thematiques?q='.urlencode('!')), TH_CARD))->toBe(0);
    });

    it('annonce l’état vide quand aucune fiche n’est publiée', function (): void {
        expect(thHtml('/thematiques'))->toContain('Les fiches arrivent bientôt.')->toContain('Aucune fiche pour l’instant.')->not->toContain('Réinitialiser');
    });

    it('pagine par « Charger plus » côté serveur, par pas de 6', function (): void {
        Thematique::factory()->count(8)->create();

        $first = thHtml('/thematiques');
        expect(substr_count($first, TH_CARD))->toBe(6)
            ->and($first)->toContain('<div class="xm-load-more" data-xm-more>')
            ->toContain('href="'.route('thematiques.index', ['n' => 12]).'#liste" rel="nofollow">Charger plus')
            ->toContain('(6 sur 8)');

        $all = thHtml('/thematiques?n=12');
        expect(substr_count($all, TH_CARD))->toBe(8)
            ->and($all)->toContain(thFr('Vous avez tout vu : 8 éléments affichés.'))
            ->not->toContain('Charger plus');

        expect(substr_count(thHtml('/thematiques?n=abc'), TH_CARD))->toBe(6)
            ->and(substr_count(thHtml('/thematiques?n=7'), TH_CARD))->toBe(8);
    });

    it('au plafond max_per_load, dit combien de fiches sont affichées sur le total au lieu de « tout vu »', function (): void {
        config(['xomiton.content.thematiques_per_load' => 6, 'xomiton.content.max_per_load' => 12]);
        Thematique::factory()->count(14)->create();

        $html = thHtml('/thematiques?n=60');

        expect(substr_count($html, TH_CARD))->toBe(12)
            ->and($html)->not->toContain('Charger plus')->not->toContain('Vous avez tout vu')
            ->toContain('<p class="text-center small text-secondary mb-0" data-xm-more>Affichage limité à 12 fiches sur 14.');
    });

    it('garde la catégorie et la recherche dans le lien « Charger plus »', function (): void {
        $vbg = thCategory('vbg');
        Thematique::factory()->count(20)->for($vbg)->sequence(fn ($sequence) => ['title' => 'Repère '.$sequence->index.' sur la violence'])->create();

        expect(thHtml('/thematiques?cat=vbg&q=violence&n=12'))
            ->toContain('href="'.e(route('thematiques.index', ['cat' => 'vbg', 'q' => 'violence', 'n' => 18])).'#liste" rel="nofollow"');
    });

    it('trie par date de publication décroissante puis par id', function (): void {
        $old = Thematique::factory()->create(['title' => 'Ancienne fiche', 'published_at' => now()->subMonth()]);
        $a = Thematique::factory()->create(['title' => 'Fiche A récente', 'published_at' => now()->subDay()]);
        $b = Thematique::factory()->create(['title' => 'Fiche B récente', 'published_at' => $a->published_at]);

        $html = thHtml('/thematiques');

        expect(strpos($html, 'Fiche A récente'))->toBeLessThan(strpos($html, 'Fiche B récente'))
            ->and(strpos($html, 'Fiche B récente'))->toBeLessThan(strpos($html, 'Ancienne fiche'));
    });

    it('charge la première couverture en priorité (LCP) et les suivantes à la demande', function (): void {
        $items = Thematique::factory()->count(3)->sequence(fn ($sequence) => ['published_at' => now()->subDays($sequence->index + 1)])->create();
        $html = thHtml('/thematiques');

        preg_match_all('~<img [^>]*>~', thBetween($html, 'id="liste"', '</section>'), $images);

        expect($images[0])->toHaveCount(3)
            ->and($images[0][0])->toContain('loading="eager"')->toContain('fetchpriority="high"')
            ->and($images[0][1])->toContain('loading="lazy"')->not->toContain('fetchpriority')
            ->and($images[0][2])->toContain('loading="lazy"');

        foreach ($images[0] as $index => $img) {
            expect($img)->toContain('alt="'.e(__('pages/thematiques.cover_alt', ['title' => $items[$index]->title])).'"')->toContain('width="640"')->toContain('height="360"');
        }
    });

    it('publie un CollectionPage + ItemList et une canonical sans paramètres', function (): void {
        $vbg = thCategory('vbg');
        Thematique::factory()->count(8)->for($vbg)->create();
        $html = thHtml('/thematiques?cat=vbg&n=6');
        $collection = thNode($html, 'CollectionPage');

        expect($html)->toContain('<link rel="canonical" href="'.url('/thematiques').'">')
            ->and($collection['url'])->toBe(url('/thematiques'))
            ->and($collection['mainEntity']['@type'])->toBe('ItemList')
            ->and($collection['mainEntity']['numberOfItems'])->toBe(8)
            ->and($collection['mainEntity']['itemListElement'])->toHaveCount(substr_count($html, TH_CARD))
            ->and(collect(thNode($html, 'BreadcrumbList')['itemListElement'])->pluck('name')->all())->toBe(['Accueil', 'Thématiques', 'Vbg']);
    });

    it('garde une structure de titres sans saut et des icônes masquées', function (): void {
        Thematique::factory()->count(2)->create();
        $main = thBetween(thHtml('/thematiques'), '<main id="contenu"', '</main>');

        expect(preg_match_all('/<h1[\s>]/', $main))->toBe(1)
            ->and($main)->toContain('<h2 class="visually-hidden" id="thematiques-liste-titre">Liste des fiches</h2>')
            ->not->toMatch('/<h[456][\s>]/')
            ->not->toContain(' style="')
            ->and(preg_match_all('~<i class="bi [^"]*"(?! aria-hidden="true")~', $main))->toBe(0);
    });
});

describe('fiche /thematiques/{slug}', function (): void {
    it('répond 200 avec fil d’Ariane, badge, h1, durée et résumé', function (): void {
        $category = thCategory('droits', ['label' => 'Droits & lois', 'icon' => 'bi-bank', 'color' => 'info']);
        $thematique = Thematique::factory()->for($category)->create(['title' => 'Porter plainte au Bénin', 'summary' => 'Les étapes pour porter plainte.', 'reading_minutes' => 4]);

        $html = thHtml('/thematiques/'.$thematique->slug);
        $crumbs = thBetween($html, '<nav aria-label="Fil d’Ariane">', '</nav>');

        expect($html)
            ->toContain('<h1 class="h4" id="fiche-titre">Porter plainte au Bénin</h1>')
            ->toContain('<p class="lead fs-6">Les étapes pour porter plainte.</p>')
            ->toContain('badge bg-info bg-opacity-10 text-info')
            ->toContain('4 min de lecture · Fiche pédagogique Xomiton')
            ->toContain('<link rel="preload" as="image" href="'.$thematique->cover_url.'" fetchpriority="high">')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->and($crumbs)
            ->toContain('<a href="'.route('home').'">Accueil</a>')
            ->toContain('<a href="'.route('thematiques.index').'">Thématiques</a>')
            ->toContain('<a href="'.e(route('thematiques.index', ['cat' => 'droits'])).'">Droits &amp; lois</a>')
            ->toContain('<li class="breadcrumb-item active" aria-current="page">Porter plainte au Bénin</li>');
    });

    it('n’affiche qu’un chapeau : celui qui ouvre le corps, sinon le résumé', function (): void {
        $withLead = Thematique::factory()->create(['summary' => 'Résumé de la fiche.', 'body' => '<p class="lead">Introduction spécifique.</p><p>Suite.</p>']);
        $article = thBetween(thHtml('/thematiques/'.$withLead->slug), 'id="fiche"', 'Ces informations sont éducatives');

        expect(preg_match_all('/<p class="lead/', $article))->toBe(1)
            ->and($article)->toContain('<p class="lead">Introduction spécifique.</p>')->not->toContain('Résumé de la fiche.');

        $plain = Thematique::factory()->create(['summary' => 'Résumé simple.', 'body' => '<p>Corps.</p>']);
        $article = thBetween(thHtml('/thematiques/'.$plain->slug), 'id="fiche"', 'Ces informations sont éducatives');

        expect(preg_match_all('/<p class="lead/', $article))->toBe(1)
            ->and(substr_count($article, 'Résumé simple.'))->toBe(1);
    });

    it('nettoie à l’écriture le HTML du corps et des sections (scripts, styles, attributs, URL dangereuses)', function (): void {
        $thematique = Thematique::factory()->create([
            'body' => '<p class="lead" style="color:red" onclick="x()">Intro</p><script>alert(1)</script><p><a href="javascript:alert(1)">lien</a> <a href="https://example.org">source</a></p>',
            'sections' => [['title' => 'Points <b>clés</b>', 'body' => '<ul><li onmouseover="x()">Point</li></ul><iframe src="https://example.org"></iframe>']],
        ]);

        $fresh = $thematique->fresh();
        $html = thHtml('/thematiques/'.$thematique->slug);

        expect($fresh->body)->toBe('<p class="lead">Intro</p><p><a>lien</a> <a href="https://example.org">source</a></p>')
            ->and($fresh->sections)->toBe([['title' => 'Points <b>clés</b>', 'body' => '<ul><li>Point</li></ul>']])
            ->and($html)->not->toContain('alert(1)')->not->toContain('onmouseover')->not->toContain('<iframe')
            ->toContain('Points &lt;b&gt;clés&lt;/b&gt;');
    });

    it('répond 404 pour une fiche non publiée ou inconnue', function (string $state): void {
        $thematique = match ($state) {
            'brouillon' => Thematique::factory()->draft()->create(),
            'masquée' => Thematique::factory()->hidden()->create(),
            'programmée' => Thematique::factory()->scheduled()->create(),
            'supprimée' => tap(Thematique::factory()->create())->delete(),
            default => null,
        };

        $this->get('/thematiques/'.($thematique?->slug ?? 'fiche-inconnue'))->assertNotFound();
    })->with(['brouillon', 'masquée', 'programmée', 'supprimée', 'inconnue']);

    it('rend les sections en accordéon dans l’ordre, la première ouverte', function (): void {
        $thematique = Thematique::factory()->create(['sections' => [
            ['title' => 'Points clés', 'body' => '<ul><li>Point un</li></ul>'],
            ['title' => 'Que faire ?', 'body' => '<ol><li>Étape</li></ol>'],
            ['title' => 'Cadre légal', 'body' => '<p>Loi.</p>'],
            ['title' => 'À retenir', 'body' => '<p>Essentiel.</p>'],
        ]]);

        $accordion = thBetween(thHtml('/thematiques/'.$thematique->slug), 'id="fiche-sections"', 'Ces informations sont éducatives');
        preg_match_all('~<button class="accordion-button[^"]*"[^>]*aria-expanded="(true|false)"[^>]*>([^<]+)</button>~', $accordion, $buttons);

        expect($buttons[2])->toBe(['Points clés', 'Que faire ?', 'Cadre légal', 'À retenir'])
            ->and($buttons[1])->toBe(['true', 'false', 'false', 'false'])
            ->and($accordion)->toContain('<h2 class="accordion-header"')->toContain('<ul><li>Point un</li></ul>');

        $plain = Thematique::factory()->create(['sections' => []]);
        expect(thHtml('/thematiques/'.$plain->slug))->not->toContain('class="accordion');
    });

    it('affiche l’avertissement de contenu avec un lien SOS seulement quand il existe', function (): void {
        $warned = Thematique::factory()->create(['content_warning' => 'Ce contenu évoque des violences.']);
        $html = thHtml('/thematiques/'.$warned->slug);

        expect(thBetween($html, 'alert alert-warning', '</div>'))->toContain('role="alert"');
        expect(thBetween($html, 'alert alert-warning', '</a>'))->toContain('Ce contenu évoque des violences.')->toContain('href="'.route('sos').'"');

        expect(thHtml('/thematiques/'.Thematique::factory()->create()->slug))->not->toContain('alert-warning');
    });

    it('n’affiche que les contacts d’annuaire actifs et publics des rôles liés (Q19)', function (): void {
        $thematique = Thematique::factory()->create(['related_contact_roles' => [DirectoryRole::Juriste->value]]);
        $public = DirectoryContact::factory()->role(DirectoryRole::Juriste)->create([
            'name' => 'AHOUANSOU Clarisse', 'phone' => '+229 01 95 49 49 79', 'whatsapp' => '+229 01 95 49 49 79',
            'email' => 'clarisse@exemple.bj', 'notes' => 'Note interne confidentielle',
        ]);
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->hidden()->create(['name' => 'CONTACT Non-public']);
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->inactive()->create(['name' => 'CONTACT Inactif']);
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->create(['name' => 'CONTACT Supprime'])->delete();
        DirectoryContact::factory()->role(DirectoryRole::Psychologue)->create(['name' => 'CONTACT Autre-role']);

        $html = thHtml('/thematiques/'.$thematique->slug);
        $aide = thBetween($html, 'id="fiche-aide"', '</section>');

        expect($aide)
            ->toContain('AHOUANSOU Clarisse')
            ->toContain('href="tel:+2290195494979"')
            ->toContain('href="https://wa.me/2290195494979" target="_blank" rel="noopener"')
            ->toContain($public->zone->name)
            ->toContain('Juriste')
            ->and($html)
            ->not->toContain('CONTACT Non-public')
            ->not->toContain('CONTACT Inactif')
            ->not->toContain('CONTACT Supprime')
            ->not->toContain('CONTACT Autre-role')
            ->not->toContain('clarisse@exemple.bj')
            ->not->toContain('Note interne confidentielle');
    });

    it('limite les contacts à quatre, dans l’ordre des rôles, point focal national d’abord', function (): void {
        $thematique = Thematique::factory()->create(['related_contact_roles' => [DirectoryRole::PointFocal->value, DirectoryRole::Juriste->value]]);
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->create(['name' => 'AAA Juriste']);
        DirectoryContact::factory()->role(DirectoryRole::PointFocal)->create(['name' => 'BBB Focal zone']);
        DirectoryContact::factory()->focalNational()->create(['name' => 'ZZZ Focal national']);
        DirectoryContact::factory()->role(DirectoryRole::PointFocal)->count(3)->create();

        $aide = thBetween(thHtml('/thematiques/'.$thematique->slug), 'id="fiche-aide"', '</section>');

        expect(substr_count($aide, '<li class="border rounded p-2">'))->toBe(4)
            ->and(strpos($aide, 'ZZZ Focal national'))->toBeLessThan(strpos($aide, 'BBB Focal zone'))
            ->and($aide)->not->toContain('AAA Juriste');

        $alone = Thematique::factory()->create(['related_contact_roles' => []]);
        $aide = thBetween(thHtml('/thematiques/'.$alone->slug), 'id="fiche-aide"', '</section>');

        expect($aide)->not->toContain('xm-th-contacts')
            ->toContain(thFr('Besoin d’aide ?'))
            ->toContain('<a class="btn btn-primary w-100" href="'.route('signaler').'">')
            ->toContain('J’ai besoin d’aide')
            ->toContain('href="'.route('urgences').'"')
            ->not->toContain('btn-danger');
    });

    it('réserve l’annuaire et l’assistant aux membres, qui ne sont pas indexé·es', function (): void {
        $mental = Thematique::factory()->for(thCategory('mental'))->create();
        $droits = Thematique::factory()->for(thCategory('droits'))->create();

        expect(thHtml('/thematiques/'.$mental->slug))->not->toContain('href="'.route('annuaire').'"')->not->toContain('href="'.route('assistant').'"');

        $this->actingAs(User::factory()->create());

        expect(thHtml('/thematiques/'.$mental->slug))
            ->toContain('href="'.route('annuaire').'"')
            ->toContain('href="'.route('assistant').'"')
            ->toContain('<meta name="robots" content="noindex, nofollow">')
            ->not->toContain('24 h/24')
            ->and(thHtml('/thematiques/'.$droits->slug))->toContain('href="'.route('annuaire').'"')->not->toContain('href="'.route('assistant').'"');
    });

    it('liste les fiches liées dans l’ordre de related_ids, complétées par la même catégorie', function (): void {
        $category = thCategory('vbg');
        $a = Thematique::factory()->for($category)->create(['title' => 'Fiche liée A', 'published_at' => now()->subDays(3)]);
        $c = Thematique::factory()->create(['title' => 'Fiche liée C']);
        $draft = Thematique::factory()->for($category)->draft()->create(['title' => 'Fiche brouillon']);
        $sibling = Thematique::factory()->for($category)->create(['title' => 'Fiche sœur', 'published_at' => now()->subDays(2)]);
        Thematique::factory()->create(['title' => 'Fiche ailleurs', 'published_at' => now()->subDay()]);
        $current = Thematique::factory()->for($category)->create(['related_ids' => [$c->id, $draft->id, $a->id]]);

        $related = thBetween(thHtml('/thematiques/'.$current->slug), 'id="fiche-liees"', '</section>');

        expect(substr_count($related, '<h3 class="h6 card-title mb-0 lh-sm">'))->toBe(3)
            ->and(strpos($related, 'Fiche liée C'))->toBeLessThan(strpos($related, 'Fiche liée A'))
            ->and(strpos($related, 'Fiche liée A'))->toBeLessThan(strpos($related, 'Fiche sœur'))
            ->and($related)->not->toContain('Fiche brouillon')->not->toContain('Fiche ailleurs')
            ->toContain('tabindex="-1" aria-hidden="true"')->toContain('alt=""');

        Thematique::query()->whereKeyNot($current->getKey())->delete();
        expect(thHtml('/thematiques/'.$current->slug))->not->toContain('id="fiche-liees"');
    });

    it('affiche la ressource principale la plus récente rattachée à la fiche', function (): void {
        $thematique = Thematique::factory()->create();
        Resource::factory()->pdf()->forThematique($thematique)->create(['title' => 'Guide ancien', 'published_at' => now()->subMonth()]);
        $recent = Resource::factory()->pdf()->forThematique($thematique)->create(['title' => 'Guide récent', 'file_size' => (int) round(1.2 * 1024 * 1024), 'published_at' => now()->subDay()]);
        Resource::factory()->article()->forThematique($thematique)->create(['title' => 'Actualité liée', 'published_at' => now()]);

        $block = thBetween(thHtml('/thematiques/'.$thematique->slug), 'id="fiche-ressource"', '</section>');

        expect($block)->toContain('Ressource principale')
            ->toContain('<a href="'.route('ressources.show', $recent->slug).'">Guide récent</a>')
            ->toContain('Document PDF · 1,2'."\u{00A0}".'Mo')
            ->toContain('Disponible hors connexion')
            ->not->toContain('Guide ancien')->not->toContain('Actualité liée');

        $lonely = Thematique::factory()->create();
        Resource::factory()->draft()->forThematique($lonely)->create();
        expect(thHtml('/thematiques/'.$lonely->slug))->not->toContain('id="fiche-ressource"');
    });

    it('affiche 3 à 5 questions de la catégorie en FAQ et en FAQPage, ou rien', function (): void {
        $category = thCategory('esc');
        $thematique = Thematique::factory()->for($category)->create();
        Faq::factory()->count(3)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create(['category_id' => $category->id]);
        Faq::factory()->create(['category_id' => $category->id, 'position' => 4, 'question' => 'Question piège ?', 'answer' => 'Réponse <script>alert(1)</script>']);
        Faq::factory()->unpublished()->create(['category_id' => $category->id, 'question' => 'Question non publiée ?']);
        Faq::factory()->create(['category_id' => thCategory('autre')->id, 'question' => 'Question d’ailleurs ?']);

        $html = thHtml('/thematiques/'.$thematique->slug);
        $faq = thBetween($html, 'id="fiche-faq-section"', '</section>');
        $schema = thNode($html, 'FAQPage');

        expect($faq)->toContain('Questions fréquentes')
            ->toContain('href="'.route('aide.index').'"')
            ->toContain('Réponse &lt;script&gt;alert(1)&lt;/script&gt;')
            ->not->toContain('<script>alert(1)')
            ->not->toContain('Question non publiée')
            ->not->toContain('Question d’ailleurs')
            ->and(substr_count($faq, '<h3 class="accordion-header"'))->toBe(4)
            ->and(substr_count($faq, 'aria-expanded="true"'))->toBe(0)
            ->and($schema['mainEntity'])->toHaveCount(4)
            ->and($schema['mainEntity'][3]['acceptedAnswer']['text'])->toBe('Réponse <script>alert(1)</script>')
            ->and($html)->not->toContain('<script>alert(1)');

        Faq::factory()->count(2)->create(['category_id' => $category->id, 'position' => 9]);
        expect(substr_count(thBetween(thHtml('/thematiques/'.$thematique->slug), 'id="fiche-faq-section"', '</section>'), '<h3 class="accordion-header"'))->toBe(5);

        $few = thCategory('corps');
        Faq::factory()->count(2)->create(['category_id' => $few->id]);
        $sparse = thHtml('/thematiques/'.Thematique::factory()->for($few)->create()->slug);
        expect($sparse)->not->toContain('id="fiche-faq-section"')->not->toContain('"FAQPage"');
    });

    it('publie un seul Article à côté du WebPage et un fil d’Ariane à quatre niveaux', function (): void {
        $category = thCategory('droits', ['label' => 'Droits & lois']);
        $thematique = Thematique::factory()->for($category)->create(['title' => 'Mes droits', 'reading_minutes' => 4, 'published_at' => '2026-10-01 08:00:00']);

        $html = thHtml('/thematiques/'.$thematique->slug);
        $article = thNode($html, 'Article');
        $canonical = route('thematiques.show', $thematique);

        expect(substr_count($html, '"@type":"Article"'))->toBe(1)
            ->and(thNode($html, 'WebPage')['@id'])->toBe($canonical.'#page')
            ->and($article['headline'])->toBe('Mes droits')
            ->and($article['articleSection'])->toBe('Droits & lois')
            ->and($article['timeRequired'])->toBe('PT4M')
            ->and($article['datePublished'])->toBe('2026-10-01')
            ->and($article['dateModified'])->toBe($thematique->updated_at->format('Y-m-d'))
            ->and($article['image'][0])->toBe($thematique->cover_url)->toStartWith('http')
            ->and($article['mainEntityOfPage'])->toBe(['@id' => $canonical.'#page'])
            ->and($article['author'])->toBe(['@id' => rtrim(config('app.url'), '/').'/#org'])
            ->and(collect(thNode($html, 'BreadcrumbList')['itemListElement'])->pluck('name')->all())->toBe(['Accueil', 'Thématiques', 'Droits & lois', 'Mes droits'])
            ->and($html)
            ->toContain('<meta property="og:type" content="article">')
            ->toContain('<meta property="og:image" content="'.$thematique->cover_url.'">')
            ->toContain('<meta property="og:image:alt" content="'.e(__('pages/thematiques.cover_alt', ['title' => 'Mes droits'])).'">');
    });

    it('borne le titre à 60 caractères et la description entre 140 et 160', function (): void {
        $custom = Thematique::factory()->create(['meta_title' => 'Titre SEO court', 'meta_description' => 'Description SEO choisie.']);
        expect(thHtml('/thematiques/'.$custom->slug))
            ->toContain('<title>Titre SEO court – Xomiton</title>')
            ->toContain('<meta name="description" content="Description SEO choisie.">');

        $long = Thematique::factory()->create([
            'title' => 'Comprendre les violences économiques et le contrôle financier dans le couple au quotidien',
            'summary' => 'Repérer le contrôle de l’argent.',
            'body' => '<p class="lead">Repérer le contrôle de l’argent.</p><p>Le contrôle financier prive de ressources. Il isole.</p>',
            'meta_title' => null,
            'meta_description' => null,
        ]);
        $html = thHtml('/thematiques/'.$long->slug);
        preg_match('~<title>(.*?)</title>~', $html, $title);
        preg_match('~<meta name="description" content="([^"]*)">~', $html, $description);
        $description = html_entity_decode($description[1], ENT_QUOTES | ENT_HTML5);

        expect(mb_strlen(html_entity_decode($title[1], ENT_QUOTES | ENT_HTML5)))->toBeLessThanOrEqual(60)
            ->and($title[1])->toStartWith('Comprendre les violences')->toEndWith('… – Xomiton')
            ->and(mb_strlen($description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160)
            ->and($description)->toStartWith('Repérer le contrôle de l’argent. Le contrôle financier prive de ressources.')
            ->toContain('Fiche Xomiton pour les filles et femmes LBTQ+ au Bénin.');
    });

    it('compte la consultation sans toucher updated_at', function (): void {
        Carbon::setTestNow('2026-10-10 10:00:00');
        $thematique = Thematique::factory()->create(['views_count' => 5]);
        Carbon::setTestNow('2026-10-12 10:00:00');

        thHtml('/thematiques/'.$thematique->slug);

        $fresh = $thematique->fresh();
        expect($fresh->views_count)->toBe(6)
            ->and($fresh->updated_at->toDateTimeString())->toBe('2026-10-10 10:00:00');

        Carbon::setTestNow();
    });

    it('ne compte ni HEAD, ni préchargement, ni robot ou aperçu de lien', function (): void {
        $thematique = Thematique::factory()->create(['views_count' => 0]);
        $uri = '/thematiques/'.$thematique->slug;

        $this->call('HEAD', $uri)->assertOk();
        $this->get($uri, ['Sec-Purpose' => 'prefetch;prerender'])->assertOk();
        foreach (['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)', 'WhatsApp/2.23.20.0 A', 'facebookexternalhit/1.1', 'curl/8.4.0', ''] as $agent) {
            $this->get($uri, ['User-Agent' => $agent])->assertOk();
        }

        expect($thematique->fresh()->views_count)->toBe(0);

        $this->get($uri, ['User-Agent' => 'Mozilla/5.0 (Linux; Android 13; CUBOT X70) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36'])->assertOk();

        expect($thematique->fresh()->views_count)->toBe(1);
    });

    it('garde une structure de titres sans saut, des icônes masquées et aucun style en ligne', function (): void {
        $category = thCategory('vbg');
        $thematique = Thematique::factory()->for($category)->create(['related_contact_roles' => [DirectoryRole::Juriste->value]]);
        Thematique::factory()->for($category)->count(3)->create();
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->create();
        Faq::factory()->count(3)->create(['category_id' => $category->id]);

        $main = thBetween(thHtml('/thematiques/'.$thematique->slug), '<main id="contenu"', '</main>');

        expect(preg_match_all('/<h1[\s>]/', $main))->toBe(1)
            ->and($main)->toContain('aria-labelledby="fiche-aide-titre"')->toContain('aria-labelledby="fiche-liees-titre"')
            ->toContain('<aside class="col-lg-4" aria-label="Aide et fiches liées">')
            ->not->toMatch('/<h[456][\s>]/')
            ->not->toContain(' style="')
            ->and(preg_match_all('~<i class="bi [^"]*"(?! aria-hidden="true")~', $main))->toBe(0);
    });
});

describe('performances', function (): void {
    it('lit la liste en 4 requêtes au plus et la fiche en 9 au plus, sans chargement paresseux', function (): void {
        $category = thCategory('vbg');
        $thematiques = Thematique::factory()->for($category)->count(8)->create();
        $current = $thematiques->first();
        $current->update(['related_ids' => $thematiques->slice(1, 3)->pluck('id')->all(), 'related_contact_roles' => [DirectoryRole::Juriste->value, DirectoryRole::PointFocal->value]]);
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->count(2)->create();
        DirectoryContact::factory()->focalNational()->create();
        Resource::factory()->pdf()->forThematique($current)->create();
        Faq::factory()->count(4)->create(['category_id' => $category->id]);
        app(Referentials::class)->categories();

        Model::preventLazyLoading();

        // Requêtes de la page seulement : réglages et fenêtre de maintenance relèvent du socle (middlewares).
        $pageQueries = static fn (): int => collect(DB::getQueryLog())
            ->filter(static fn (array $entry): bool => preg_match('/"(thematiques|categories|directory_contacts|zones|resources|faqs)"/', $entry['query']) === 1)
            ->count();

        DB::enableQueryLog();
        thHtml('/thematiques');
        $listQueries = $pageQueries();
        DB::flushQueryLog();
        thHtml('/thematiques/'.$current->slug);
        $showQueries = $pageQueries();
        DB::disableQueryLog();

        Model::preventLazyLoading(false);

        expect($listQueries)->toBeLessThanOrEqual(4)->and($showQueries)->toBeLessThanOrEqual(9);
    });
});

describe('données semées', function (): void {
    it('sert les 29 fiches du prototype, chacune avec sa FAQ et des méta bornées', function (): void {
        $this->seed(ContentSeeder::class);
        $this->seed(ThematiqueFaqSeeder::class);
        $this->seed(ThematiqueFaqSeeder::class);

        expect(Faq::query()->whereHas('category', fn ($query) => $query->where('kind', Category::KIND_THEMATIQUE))->count())->toBe(18)
            ->and(Faq::onHome()->count())->toBe(6)
            ->and(substr_count(thHtml('/thematiques?n=30'), TH_CARD))->toBe(29);

        foreach (Thematique::query()->orderBy('id')->get() as $thematique) {
            expect(mb_strlen((string) $thematique->meta_title))->toBeLessThanOrEqual(50)
                ->and(mb_strlen((string) $thematique->meta_description))->toBeGreaterThanOrEqual(140)->toBeLessThanOrEqual(160);

            $html = thHtml('/thematiques/'.$thematique->slug);
            expect($html)->toContain('id="fiche-faq-section"')->toContain('"FAQPage"')->toContain('id="fiche-liees"')
                ->and(preg_match_all('/<p class="lead/', thBetween($html, 'id="fiche"', 'Ces informations sont éducatives')))->toBe(1);
        }
    });

    it('publie les FAQ semées hors production seulement, sans jamais republier une FAQ dépubliée', function (): void {
        $thematiqueFaqs = static fn () => Faq::query()->whereHas('category', fn ($query) => $query->where('kind', Category::KIND_THEMATIQUE));
        $this->seed(ContentSeeder::class);
        $former = Faq::factory()->create(['question' => thFr('Dois-je porter plainte pour être aidée ?'), 'is_published' => true]);

        $this->seed(ThematiqueFaqSeeder::class);
        $hidden = $thematiqueFaqs()->whereKeyNot($former->getKey())->orderBy('id')->firstOrFail();
        $hidden->update(['is_published' => false]);
        $this->seed(ThematiqueFaqSeeder::class);

        expect($thematiqueFaqs()->count())->toBe(18)
            ->and($former->fresh()->question)->toBe(thFr('Dois-je porter plainte pour recevoir de l’aide ?'))
            ->and($hidden->fresh()->is_published)->toBeFalse()
            ->and($thematiqueFaqs()->where('is_published', true)->count())->toBe(17);

        $thematiqueFaqs()->delete();
        $environment = $this->app['env'];
        $this->app['env'] = 'production';
        try {
            $this->app->make(ThematiqueFaqSeeder::class)->run();
        } finally {
            $this->app['env'] = $environment;
        }

        expect($thematiqueFaqs()->count())->toBe(18)->and($thematiqueFaqs()->where('is_published', true)->count())->toBe(0);
    });

    it('montre les contacts de démonstration publics, jamais le non public ni l’inactif', function (): void {
        $this->seed(ContentSeeder::class);
        $this->seed(DemoDirectorySeeder::class);
        $this->seed(DemoDirectorySeeder::class);
        $vbg = Thematique::query()->whereHas('category', fn ($query) => $query->where('slug', 'vbg'))->orderBy('id')->firstOrFail();

        $aide = thBetween(thHtml('/thematiques/'.$vbg->slug), 'id="fiche-aide"', '</section>');

        expect(DirectoryContact::count())->toBe(count(DemoDirectorySeeder::contacts()))
            ->and($aide)->toContain('Démo – Point focal national')->toContain('Démo – Juriste')->toContain('Démo – Psychologue')
            ->not->toContain('(non publique)')->not->toContain('(inactive)')
            ->and(strpos($aide, 'Point focal national'))->toBeLessThan(strpos($aide, 'Démo – Juriste'));
    });

    it('respecte la typographie française dans les textes de la page et de la FAQ semée', function (): void {
        $texts = [...array_values(array_filter(__('pages/thematiques'), 'is_string'))];
        foreach (require database_path('seeders/data/content/thematiques_faqs.php') as $rows) {
            foreach ($rows as [$question, $answer]) {
                $texts[] = $question;
                $texts[] = $answer;
            }
        }

        foreach ($texts as $text) {
            expect($text)->not->toMatch('/ [?!:;](\s|$)/u')->not->toContain("'")->not->toMatch('/24\s?h\/24/u');
        }
    });
});

describe('redirections et sitemap', function (): void {
    it('redirige les anciennes adresses du prototype en 301', function (): void {
        $this->seed(ContentSeeder::class);
        $third = Thematique::query()->where('cover_path', 'like', '%/covers/th-3.jpg')->firstOrFail();

        $this->get('/thematiques.php')->assertStatus(301)->assertRedirect('/thematiques');
        $this->get('/thematique.php?id=3')->assertStatus(301)->assertRedirect(route('thematiques.show', $third));
        $this->get('/thematique.php?id=999')->assertStatus(301)->assertRedirect(route('thematiques.index'));
        $this->get('/thematique.php?id=abc')->assertStatus(301)->assertRedirect(route('thematiques.index'));
    });

    it('fait apparaître la liste et les fiches publiées dans le sitemap une fois livrées', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);
        $published = Thematique::factory()->create(['slug' => 'droits-et-recours']);
        Thematique::factory()->draft()->create(['slug' => 'brouillon-invisible']);
        $root = rtrim((string) config('app.url'), '/');

        $xml = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

        expect($xml)
            ->toContain('<loc>'.$root.'/thematiques</loc>')
            ->toContain('<loc>'.$root.'/thematiques/droits-et-recours</loc>')
            ->toContain($published->updated_at->toAtomString())
            ->not->toContain('brouillon-invisible')
            ->not->toContain('?cat=')->not->toContain('?q=')->not->toContain('?n=');
    });
});
