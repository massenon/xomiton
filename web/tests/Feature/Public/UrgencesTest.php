<?php

declare(strict_types=1);

use App\Application\Alerts\Queries\ListEmergencyContacts;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Models\Zone;
use App\Support\FrenchTypography;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Tests\Support\AlertFixtures;

/*
 * Page `/urgences` (P4-06, prototype urgences.php) : gabarit sobre (Q18 a), tu (Q47), numéros et fiches de l'annuaire
 * depuis la même source que `GET /api/v1/urgences` (ListEmergencyContacts, aucun numéro en dur), liens tel:,
 * département facultatif (inconnu ignoré), lieux sûrs (paramètre `safe_places` sinon exemples), plan de sécurité
 * mémorisé sur l'appareil (script en ligne autonome, nonce CSP), rouge réservé au SOS, SEO et sitemap.
 */

beforeEach(function (): void {
    $this->seed(ReferenceDataSeeder::class);
});

function urgHtml(string $uri = '/urgences'): string
{
    return (string) test()->get($uri)->assertOk()->getContent();
}

/** Contenu de <main> (le <head> porte le titre SEO, l'en-tête sobre le bouton « Urgences »). */
function urgMain(string $html): string
{
    preg_match('~<main id="contenu"[^>]*>(.*)</main>~s', $html, $matches);

    return $matches[1] ?? '';
}

/** @return list<string> noms des fiches affichées, dans l'ordre */
function urgContactNames(string $html): array
{
    preg_match_all('~<h3 class="h6 fw-bold mb-1" id="urg-contact-\d+">([^<]+)</h3>~', $html, $matches);

    return array_map(static fn (string $name): string => html_entity_decode($name, ENT_QUOTES), $matches[1]);
}

/** @return list<array<string, mixed>> */
function urgGraph(string $html): array
{
    preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $matches);

    return json_decode($matches[1] ?? '', true, flags: JSON_THROW_ON_ERROR)['@graph'];
}

describe('numéros d’urgence', function (): void {
    it('sert la page sobre en tu avec les numéros administrés, cliquables en tel:, sans numéro en dur', function (): void {
        Settings::set('emergency_numbers', [
            ['label' => 'Police secours', 'number' => '166'],
            ['label' => 'Ligne d’écoute test', 'number' => '+229 01 90 00 00 01'],
        ]);

        $html = urgHtml();
        $main = urgMain($html);

        expect($html)->toContain('<body class="xm-plain xm-urgences-page">')
            ->and($main)->toContain('<h1 class="h3 mb-1">'.e(__('pages/urgences.title')).'</h1>')
            ->toContain('href="tel:166"')->toContain('href="tel:+2290190000001"')
            ->toContain('Ligne d’écoute test')
            ->toContain(e(__('pages/urgences.banner.text', ['number' => '166'])))
            ->toContain('bi-headset')->toContain('bi-shield-fill-check')
            // Aucun numéro de la config par défaut quand le paramètre est renseigné (rien en dur dans la page, <head> compris).
            ->and($html)->toContain('<title>'.e(__('seo.pages.urgences.title', ['number' => '166'])).'</title>')
            ->toContain('<meta name="description" content="'.e(__('seo.pages.urgences.description', ['number' => '166'])).'">')
            ->not->toContain('117')->not->toContain('118')->not->toContain('97 38 88 47');
    });

    it('lit la même source que GET /api/v1/urgences, numéros et fiches dans le même ordre', function (): void {
        $littoral = AlertFixtures::zone('Littoral');
        $borgou = AlertFixtures::zone('Borgou');
        DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($borgou)->create(['name' => 'ZOUNON Ailleurs']);
        DirectoryContact::factory()->focalNational()->create(['name' => 'AHO National']);
        DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($littoral)->create(['name' => 'ZINSOU Littoral']);

        foreach (['', '?zone='.$littoral->id] as $query) {
            $api = $this->getJson('/api/v1/urgences'.$query)->assertOk();
            $html = urgHtml('/urgences'.$query);

            expect(urgContactNames($html))->toBe($api->json('data.contacts.*.name'));

            foreach ($api->json('data.services') as $service) {
                expect($html)->toContain('href="'.$service['tel'].'"')->toContain(e($service['number']));
            }
        }

        expect(urgContactNames(urgHtml('/urgences?zone='.$littoral->id)))->toBe(['ZINSOU Littoral', 'AHO National']);
    });

    it('ne promet aucun numéro quand aucun n’est administré', function (): void {
        Settings::set('emergency_numbers', [['label' => '', 'number' => '']]);
        config(['xomiton.assistant.emergency_numbers' => []]);

        $main = urgMain(urgHtml());

        expect($main)->toContain(e(__('pages/urgences.services.empty')))
            ->toContain(e(__('pages/urgences.banner.text_no_number')))
            ->not->toContain('href="tel:')
            ->and(urgHtml())->toContain('<title>'.e(__('seo.pages.urgences.title_plain')).'</title>')->not->toContain(':number');
    });
});

describe('fiches de l’annuaire', function (): void {
    it('affiche les seules fiches d’urgence actives et publiques, avec Appeler et WhatsApp, sans e-mail ni notes', function (): void {
        $littoral = AlertFixtures::zone('Littoral');
        $public = DirectoryContact::factory()->role(DirectoryRole::Juriste)->inZone($littoral)->withNotes('note interne')
            ->create(['name' => 'ADJOVI Rose', 'phone' => '+229 01 61 00 00 01', 'whatsapp' => '+229 01 61 00 00 02', 'availability' => 'Lun–Ven 8 h–17 h']);
        DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($littoral)->hidden()->create(['name' => 'MASQUÉE Fiche']);
        DirectoryContact::factory()->role(DirectoryRole::Psychologue)->inZone($littoral)->inactive()->create(['name' => 'INACTIVE Fiche']);
        DirectoryContact::factory()->role(DirectoryRole::PairAnimateur)->inZone($littoral)->create(['name' => 'PAIR Publique']);
        DirectoryContact::factory()->focalNational()->create(['name' => 'AHO National']);

        $main = urgMain(urgHtml());

        expect(urgContactNames($main))->toBe(['AHO National', 'ADJOVI Rose'])
            // Page indexable : noms et numéros hors des extraits des moteurs de recherche (Q59 c provisoire).
            ->and($main)->toContain('<ul class="row g-3 list-unstyled mb-0" data-nosnippet>')
            ->toContain('href="tel:+2290161000001"')
            ->toContain('href="https://wa.me/2290161000002"')
            ->toContain('Lun–Ven 8 h–17 h')
            ->toContain(e(DirectoryRole::Juriste->label()))
            ->toContain(e(__('pages/urgences.contacts.national')))
            ->not->toContain((string) $public->email)->not->toContain('note interne')
            ->not->toContain('MASQUÉE')->not->toContain('INACTIVE')->not->toContain('PAIR Publique');
    });

    it('ignore un département inconnu, inactif ou mal formé sans erreur ni redirection', function (): void {
        $inactive = Zone::factory()->create(['is_active' => false]);
        DirectoryContact::factory()->focalNational()->create(['name' => 'AHO National']);

        foreach (['abc', '0', '-3', '999999', (string) $inactive->id, 'zone[]=1'] as $value) {
            $main = urgMain(urgHtml('/urgences?zone='.$value));

            expect(urgContactNames($main))->toBe(['AHO National'])
                ->and($main)->not->toContain('Contacts du département');
        }

        $this->get('/urgences?zone[]=1')->assertOk();
    });

    it('propose le choix du département par un formulaire GET sans soumission au changement', function (): void {
        $littoral = AlertFixtures::zone('Littoral');
        $main = urgMain(urgHtml('/urgences?zone='.$littoral->id));

        expect($main)->toContain('<form class="row g-2 align-items-end mb-3 no-print" method="get" action="'.route('urgences').'#urgences-contacts">')
            ->toContain('<option value="'.$littoral->id.'" selected>Littoral</option>')
            ->toContain(e(__('pages/urgences.contacts.zone_current', ['zone' => 'Littoral'])))
            ->not->toContain('onchange')->not->toContain('x-on:change');
    });

    it('signale les fiches au-delà du plafond et dit quand l’annuaire est vide', function (): void {
        expect(urgMain(urgHtml()))->toContain(e(__('pages/urgences.contacts.empty')));

        DirectoryContact::factory()->count(ListEmergencyContacts::MAX_CONTACTS + 1)->role(DirectoryRole::Juriste)->create();
        ListEmergencyContacts::invalidate();

        $main = urgMain(urgHtml());

        expect(urgContactNames($main))->toHaveCount(ListEmergencyContacts::MAX_CONTACTS)
            ->and($main)->toContain(e(__('pages/urgences.contacts.truncated')));
    });

    it('réserve l’annuaire complet et l’assistant membre aux personnes connectées', function (): void {
        $guest = urgMain(urgHtml());

        expect($guest)->not->toContain('href="'.route('annuaire').'"')
            ->toContain('href="'.route('connexion').'"')
            ->toContain('data-bs-target="#offcanvasChat"')
            ->and(urgHtml())->toContain('id="offcanvasChat"');

        $this->actingAs(User::factory()->create());
        $html = urgHtml();

        expect(urgMain($html))->toContain('href="'.route('annuaire').'"')->toContain('href="'.route('assistant').'"')
            ->not->toContain('data-bs-target="#offcanvasChat"')
            ->and($html)->not->toContain('id="offcanvasChat"');
    });
});

describe('lieux sûrs et plan de sécurité', function (): void {
    it('affiche les lieux d’exemple du prototype, puis ceux du paramètre safe_places sans badge', function (): void {
        $main = urgMain(urgHtml());

        expect($main)->toContain(e(__('pages/urgences.places.examples')))
            ->toContain('Office central de protection des mineurs (OCPM)')
            ->toContain('bi-geo-alt-fill text-primary');

        Settings::set('safe_places', [
            ['name' => 'Maison des femmes de Parakou', 'where' => 'Parakou', 'text' => 'Accueil de jour.'],
            ['where' => 'sans nom'],
            'texte libre',
        ]);
        $main = urgMain(urgHtml());

        expect($main)->toContain('Maison des femmes de Parakou')->toContain('Accueil de jour.')
            ->not->toContain('OCPM')->not->toContain('sans nom')->not->toContain('texte libre')
            ->not->toContain('>'.e(__('pages/urgences.places.examples')).'<');
    });

    it('rend le plan à cocher avec un script en ligne autonome (nonce), outils masqués sans JavaScript', function (): void {
        $html = urgHtml();
        $main = urgMain($html);

        expect(substr_count($main, 'data-plan-item="'))->toBe(8)
            // Indices 0 à 7 comme le prototype (`data-i`) : un état `xm_plan_v1` déjà enregistré coche les mêmes étapes.
            ->and($main)->toContain('id="plan0" data-plan-item="0"')->toContain('data-plan-item="7"')->not->toContain('data-plan-item="8"')
            ->and($main)->toContain('data-xm-safety-plan data-key="xm_plan_v1"')
            ->toContain('<div class="no-print" data-plan-tools hidden>')
            ->toContain('data-plan-progress hidden')
            ->toContain('aria-live="polite"')
            ->toContain(e(__('pages/urgences.plan.local_only')))
            ->toMatch('~<script nonce="[^"]+">\(function \(\) \{~')
            ->toContain('localStorage.getItem(key)')
            ->not->toContain('fetch(')->not->toContain('onclick=');

        preg_match('~<script nonce="([^"]+)">\(function~', $main, $nonce);
        expect($this->get('/urgences')->headers->get('Content-Security-Policy'))->toContain("'nonce-");
        expect($nonce[1] ?? '')->not->toBe('');
    });
});

describe('gabarit, SEO et sitemap', function (): void {
    it('garde le rouge pour le seul accès SOS', function (): void {
        DirectoryContact::factory()->role(DirectoryRole::PointFocal)->create(['name' => 'POINT Focal']);
        Settings::set('emergency_numbers', [['label' => 'Pompiers / secours', 'number' => '118']]);

        // Hors offcanvas de l'assistant (dialogue distinct, son propre lien SOS).
        $main = (string) strstr(urgMain(urgHtml()), '<div class="offcanvas', true);
        $banner = (string) strstr($main, '<header>', true);
        $rest = substr($main, strlen($banner));

        expect($banner)->toContain('alert-danger')->toContain('btn btn-danger fw-bold" href="'.route('sos').'"')
            ->and($rest)->not->toMatch('/(?:bg|text|btn|border|alert)-danger/')
            // En-tête sobre : le lien « Urgences » vers la page courante est neutre et porte aria-current.
            ->and(urgHtml())->toMatch('~<a class="btn btn-sm fw-bold btn-secondary-soft" href="'.preg_quote(route('urgences'), '~').'"\s+aria-current="page"\s*>~')
            ->and((string) $this->get('/signaler')->getContent())->toContain('btn btn-sm fw-bold btn-danger-soft" href="'.route('urgences').'"');
    });

    it('produit titre, description, BreadcrumbList et canonical sans paramètre, indexable pour un·e visiteur·se', function (): void {
        $littoral = AlertFixtures::zone('Littoral');
        $html = urgHtml('/urgences?zone='.$littoral->id);
        $crumbs = collect(urgGraph($html))->firstWhere('@type', 'BreadcrumbList');

        expect($html)->toContain('<title>'.e(__('seo.pages.urgences.title', ['number' => '117'])).'</title>')
            ->toContain('<link rel="canonical" href="'.route('urgences').'">')
            ->toContain('content="index, follow, max-image-preview:large"')
            ->and(array_column($crumbs['itemListElement'], 'name'))->toBe([__('seo.home'), __('pages/urgences.title')]);
    });

    it('fait apparaître /urgences dans le sitemap', function (): void {
        $this->app->detectEnvironment(static fn (): string => 'production');
        Settings::set('seo.allow_indexing', true);

        expect((string) $this->get('/sitemap.xml')->assertOk()->getContent())
            ->toContain('<loc>'.rtrim((string) config('app.url'), '/').'/urgences</loc>');
    });

    it('tutoie et respecte la typographie française dans les textes de la page', function (): void {
        $lines = collect((array) trans('pages/urgences'))->flatten()->map(static fn (mixed $line): string => (string) $line);

        foreach ($lines as $line) {
            expect($line)->not->toMatch('/\b[Vv]ous\b/u')
                ->not->toMatch('/ [?!:;](?![a-z])/u')
                ->not->toContain("'");
        }

        expect(__('pages/urgences.places.title'))->toBe('Où aller'.FrenchTypography::NBSP.'?');
    });
});
