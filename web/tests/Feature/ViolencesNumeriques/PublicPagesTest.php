<?php

declare(strict_types=1);

use App\Application\Content\ViolencesNumeriques\ImportViolencesNumeriques;
use App\Application\Seo\SitemapBuilder;
use App\Models\CccTool;
use App\Models\Profile;
use App\Models\Thematique;
use App\Models\User;
use App\Models\UserSetting;

/*
 * P19-03 / P19-04 : pages publiques des violences numériques — carrefour /violences-numeriques (6 catégories, 23 sujets,
 * « à venir » sans lien), fiche /thematiques/{slug} (FAQ propres, BD liée, fil d'Ariane vers le carrefour, références
 * juridiques seulement si remplies), liste /bd (BD publiques seulement) et visionneuse /bd/{key} (404 pour une BD non
 * publique ou masquée), mode discret, plan du site.
 */

beforeEach(function (): void {
    app(ImportViolencesNumeriques::class)();
});

it('sert le carrefour : 6 catégories, 23 sujets, tous reliés à leur fiche', function (): void {
    $html = (string) $this->get('/violences-numeriques')->assertOk()->getContent();

    expect(substr_count($html, 'id="vn-cat-'))->toBe(6)
        ->and(substr_count($html, 'class="card h-100 xm-vn-topic'))->toBe(23)
        ->and(substr_count($html, 'is-upcoming'))->toBe(0);
    expect($html)->toContain(route('thematiques.show', ['slug' => 'sextorsion']))
        ->toContain(route('bd.show', ['key' => 'vn02']))
        ->toContain(route('thematiques.show', ['slug' => 'revenge-porn']))
        ->toContain(route('signaler'))->toContain(route('sos'))->toContain(route('urgences'))->toContain(route('bd.index'));
});

it('sert la fiche d’un sujet avec ses questions, sa BD et le fil d’Ariane du carrefour', function (): void {
    $html = (string) $this->get('/thematiques/sextorsion')->assertOk()->getContent();

    expect($html)->toContain('Si je paie une seule fois')
        ->toContain(route('bd.show', ['key' => 'vn02']))
        ->toContain(route('violences-numeriques'))
        ->toContain('Ce contenu évoque un chantage')
        ->not->toContain('id="fiche-loi"')
        ->not->toContain(__('pages/violences_numeriques.legal_validated'));
});

it('affiche les références juridiques nettoyées, avec la mention de validation, seulement si elles sont remplies', function (): void {
    Thematique::query()->where('slug', 'sextorsion')->firstOrFail()
        ->forceFill(['legal_references' => '<p>Texte de loi saisi par l’administration.</p><script>alert(1)</script>'])->save();

    $html = (string) $this->get('/thematiques/sextorsion')->assertOk()->getContent();

    expect($html)->toContain('id="fiche-loi"')
        ->toContain('Texte de loi saisi par l’administration.')
        ->toContain(e(__('pages/violences_numeriques.legal_validated')))
        ->not->toContain('<script>alert(1)</script>');
});

it('liste les BD publiques seulement et refuse en 404 une BD réservée aux membres ou masquée', function (): void {
    CccTool::factory()->comic()->create(['key' => 'bd1', 'title' => 'BD réservée aux membres']);
    CccTool::query()->where('key', 'vn08')->update(['is_published' => false]);

    $html = (string) $this->get('/bd')->assertOk()->getContent();
    expect($html)->toContain(route('bd.show', ['key' => 'vn01']))
        ->not->toContain('BD réservée aux membres')
        ->not->toContain(route('bd.show', ['key' => 'vn08']));

    $this->get('/bd/bd1')->assertNotFound();
    $this->get('/bd/vn08')->assertNotFound();
    $this->get('/bd/inconnue')->assertNotFound();
    CccTool::query()->where('key', 'vn07')->update(['is_public' => false]);
    $this->get('/bd/vn07')->assertNotFound();
});

it('sert la visionneuse : planches avec légende et texte alternatif, boutons, compteur, liens d’aide et fiche', function (): void {
    $tool = CccTool::query()->where('key', 'vn02')->firstOrFail();
    $html = (string) $this->get('/bd/vn02')->assertOk()->getContent();

    expect(substr_count($html, 'data-xm-comic-page'))->toBe(4);
    foreach ($tool->pages as $index => $page) {
        expect($html)->toContain('id="planche-'.($index + 1).'"')
            ->toContain('alt="'.e($page['alt']).'"')
            ->toContain(e($page['caption']));
    }
    expect(substr_count($html, 'loading="lazy"'))->toBeGreaterThanOrEqual(3)
        ->and($html)->toContain('fetchpriority="high"')
        ->toContain("x-data='xmComic(")
        ->toContain(e(__('pages/bd.viewer.previous')))->toContain(e(__('pages/bd.viewer.next')))
        ->toContain('aria-live="polite"')
        ->toContain('Ce contenu évoque un chantage')
        ->toContain(route('thematiques.show', ['slug' => 'sextorsion']))
        ->toContain(route('signaler'))->toContain(route('sos'))->toContain(route('urgences'))
        ->toContain('index, follow');
});

it('respecte le mode discret d’une personne connectée : titre neutre et planches repliées', function (): void {
    $user = User::factory()->create();
    Profile::query()->updateOrCreate(['user_id' => $user->id], ['discreet_mode' => true]);
    UserSetting::query()->updateOrCreate(['user_id' => $user->id], ['neutral_tab_title' => true]);

    $html = (string) $this->actingAs($user->fresh())->get('/bd/vn02')->assertOk()->getContent();

    expect($html)->toContain('<title>'.e(__('seo.noindex.neutral')))
        ->toContain('<details class="card">')
        ->toContain('noindex');
});

it('ajoute le carrefour, la liste des BD, les BD publiques et les fiches au plan du site', function (): void {
    config(['app.env' => 'production', 'seo.allow_indexing' => true]);
    CccTool::factory()->comic()->create(['key' => 'bd1']);
    SitemapBuilder::flush();

    $locs = collect(app(SitemapBuilder::class)->urls())->pluck('loc');
    $root = rtrim((string) config('app.url'), '/');

    expect($locs)->toContain($root.'/violences-numeriques', $root.'/bd', $root.'/bd/vn01', $root.'/thematiques/sextorsion', $root.'/bd/vn23')
        ->not->toContain($root.'/bd/bd1');
});

it('relie le carrefour depuis le pied de page public', function (): void {
    expect((string) $this->get('/')->assertOk()->getContent())->toContain(route('violences-numeriques'));
});
