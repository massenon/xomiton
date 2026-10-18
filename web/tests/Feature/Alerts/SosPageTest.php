<?php

declare(strict_types=1);

use App\Application\Alerts\TriggerSos;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Models\Alert;
use App\Models\UserSetting;
use App\Support\Settings;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Tests\Support\AlertFixtures;

/*
 * P5-05 : page /sos (gabarit sobre, tu) — grand bouton x-sos-button (maintien 2 s, décompte 5 s, alternatives), case
 * « Partager ma position » cochée par défaut (dernier choix du compte : user_settings.sos_share_gps), repli
 * département / commune, numéros d'urgence administrés (aucun en dur), envoi par l'API seulement, suivi `?alerte=`
 * réservé au compte propriétaire. P5-08 : bouton flottant xm-sos-fab sur les gabarits connectés, z-index 1060.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

/** Configuration JSON passée à xmSosPage (décodée depuis l'attribut x-data). */
function sosPageConfig(string $html): array
{
    expect(preg_match('/x-data="xmSosPage\(JSON\.parse\(\'(.*?)\'\)\)"/s', $html, $m))->toBe(1);

    return json_decode(json_decode('"'.$m[1].'"'), true, 512, JSON_THROW_ON_ERROR);
}

it('sert la page sobre au visiteur : grand bouton, alternatives, position cochée, envoi par l’API', function (): void {
    $response = $this->get('/sos')->assertOk();
    $html = $response->getContent();

    $response->assertSee('class="xm-plain', false)
        ->assertSee('<title>'.e(__('seo.pages.sos.title')).'</title>', false)
        ->assertSee('class="xm-sos xm-sos--lg" id="sosButton"', false)
        ->assertSee('xm-sos-wrap--inline', false)
        ->assertSee('xm-sos-panel--inline', false)
        ->assertSee('x-on:pointerup="release()"', false)
        ->assertSee('x-on:click="activate($event)"', false)
        ->assertSee('x-on:keydown.enter.prevent="ask()"', false)
        ->assertSee('aria-live="assertive"', false)
        ->assertSee('data-outbox="sos"', false)
        ->assertSee(__('alerts.sos.button_help'))
        ->assertSee(__('alerts.sos.alternatives'))
        ->assertSee(__('alerts.sos.share'))
        ->assertSee(__('alerts.sos.share_remembered_device'))
        ->assertSee(__('alerts.sos.manual.zone'))
        ->assertSee(__('alerts.sos.queued.text'))
        ->assertSee('content="index, follow', false)
        ->assertDontSee('xm-sos-fab', false)
        ->assertDontSee('<form', false);

    expect($response->headers->get('Permissions-Policy'))->toContain('geolocation=(self)')
        ->and($html)->toMatch('/<input[^>]*id="f-share_location"[^>]*checked/');

    $config = sosPageConfig($html);
    expect($config['member'])->toBeFalse()
        ->and($config['share'])->toBeTrue()
        ->and($config['initial'])->toBeNull()
        ->and($config['endpoint'])->toBe(route('api.sos.store'))
        ->and($config['showUrl'])->toBe(route('api.sos.show', ['alert' => '__ID__']))
        ->and($config['zones'])->not->toBeEmpty()
        ->and($config['zones'][0])->toHaveKeys(['id', 'name', 'communes']);
});

it('affiche les numéros d’urgence administrés en liens d’appel, jamais un numéro en dur', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte', 'number' => '+229 01 00 00 00 00']]);

    $this->get('/sos')->assertOk()
        ->assertSee('href="tel:+2290100000000"', false)
        ->assertSee(__('alerts.sos.doubt', ['number' => '+229 01 00 00 00 00']))
        ->assertDontSee('tel:117', false);
});

it('reprend le dernier choix de partage et le département du profil d’un·e membre', function (): void {
    $member = AlertFixtures::member();
    $zone = AlertFixtures::zone('Littoral');
    $member->forceFill(['zone_id' => $zone->id])->save();
    UserSetting::query()->updateOrCreate(['user_id' => $member->id], ['sos_share_gps' => false]);

    $response = $this->actingAs($member)->get('/sos')->assertOk()
        ->assertSee(__('alerts.sos.share_remembered_account'))
        ->assertSee('content="noindex', false);

    $config = sosPageConfig($response->getContent());
    expect($config['member'])->toBeTrue()
        ->and($config['share'])->toBeFalse()
        ->and($config['profile']['zone_id'])->toBe($zone->id)
        ->and($response->getContent())->not->toMatch('/<input[^>]*id="f-share_location"[^>]*checked/');
});

it('retrouve le SOS du compte connecté par ?alerte= (bouton flottant, rechargement), sans cache', function (): void {
    $member = AlertFixtures::member();
    $alert = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $member->id]))->alert;

    $response = $this->actingAs($member)->get('/sos?alerte='.$alert->ulid)->assertOk();
    $config = sosPageConfig($response->getContent());

    expect($config['initial']['data']['id'])->toBe($alert->ulid)
        ->and($config['initial']['data']['type'])->toBe('sos')
        ->and($config['initial']['data'])->toHaveKeys(['notified', 'primary_contact', 'can_cancel_until'])
        ->and($config['initial']['data'])->not->toHaveKey('sos_token')
        ->and($config['initial']['meta']['poll_interval_seconds'])->toBe(10)
        ->and($response->headers->get('Cache-Control'))->toContain('no-store');

    $this->get('/sos?alerte='.Str::lower($alert->ulid))->assertOk();
    expect(sosPageConfig($this->get('/sos?alerte='.Str::lower($alert->ulid))->getContent())['initial']['data']['id'])->toBe($alert->ulid);
});

it('n’affiche jamais le SOS d’un autre compte, un SOS anonyme ou un identifiant invalide', function (): void {
    $owner = AlertFixtures::member();
    $other = AlertFixtures::member();
    $alert = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $owner->id]))->alert;
    $anonymous = app(TriggerSos::class)(AlertFixtures::sos())->alert;

    expect(sosPageConfig($this->actingAs($other)->get('/sos?alerte='.$alert->ulid)->assertOk()->getContent())['initial'])->toBeNull()
        ->and(sosPageConfig($this->actingAs($other)->get('/sos?alerte='.$anonymous->ulid)->getContent())['initial'])->toBeNull()
        ->and(sosPageConfig($this->actingAs($other)->get('/sos?alerte[]=x')->assertOk()->getContent())['initial'])->toBeNull()
        ->and(sosPageConfig($this->actingAs($other)->get('/sos?alerte=pas-un-ulid')->getContent())['initial'])->toBeNull();

    auth()->logout();
    expect(sosPageConfig($this->get('/sos?alerte='.$alert->ulid)->assertOk()->getContent())['initial'])->toBeNull();
});

it('pose le bouton SOS flottant sur les gabarits connectés, jamais pour un visiteur, sur /sos ni en connexion', function (): void {
    $member = AlertFixtures::member();

    $this->get('/')->assertDontSee('xm-sos-fab', false);
    $this->get('/connexion')->assertDontSee('xm-sos-fab', false);

    foreach (['/', '/fil'] as $uri) {
        $this->actingAs($member)->get($uri)->assertOk()
            ->assertSee('x-data="xmSosFab(', false)
            ->assertSee('x-on:sos-fab-send="onSend($event.detail)" data-outbox="sos"', false)
            ->assertSee('class="xm-sos-wrap no-print xm-sos-wrap--fixed xm-sos-fab"', false)
            ->assertSee('class="xm-sos" id="xmSosFab"', false)
            ->assertSee('sos?alerte=__ID__', false);
    }

    $this->actingAs($member)->get('/sos')->assertDontSee('xmSosFab', false);
});

it('place le bouton flottant à z-index 1060, au-dessus des modales, dans web/ et le design system', function (): void {
    $components = File::get(resource_path('css/app/components.css'));
    $theme = File::get(resource_path('css/theme/xomiton.css'));
    $designSystem = File::get(base_path('../design-system/blade/components/sos-button.blade.php'));

    expect($components)->toMatch('/\.xm-sos-wrap--fixed \{[^}]*z-index: 1060;/')
        ->and($theme)->toMatch('/\.xm-sos-fab \{[^}]*z-index:1060;/')
        ->and($designSystem)->toContain('z-index:1060')
        ->and($components.$theme.$designSystem)->not->toMatch('/sos[^{]*\{[^}]*z-index: ?1040/');
});

it('inclut /sos dans le plan du site : la page n’est plus provisoire', function (): void {
    $this->app->detectEnvironment(static fn (): string => 'production');

    $this->get('/sitemap.xml')->assertOk()->assertSee('<loc>'.rtrim((string) config('app.url'), '/').'/sos</loc>', false);
});

it('définit les textes de la page en tutoyant, avec l’espace insécable avant « : ; ? ! »', function (): void {
    foreach (['alerts.sos.title', 'alerts.sos.share', 'alerts.sos.manual.auto', 'alerts.sos.done.safe', 'alerts.sos.queued.text', 'alerts.sos.fab.offline', 'components.sos.sending'] as $key) {
        expect(Lang::has($key, 'fr', false))->toBeTrue($key);
    }

    $texts = array_merge(Arr::dot(trans('alerts.sos')), Arr::dot(['c' => trans('components.sos')]));

    foreach ($texts as $key => $text) {
        expect($text)->not->toMatch('/ [:;?!](?![a-z_])/u', (string) $key)
            ->and($text)->not->toMatch('/\b(vous|votre|vos)\b/iu', (string) $key);
    }

    expect(trans('alerts.sos.queued.text'))->toBe('Tu es hors ligne. Ton alerte partira dès que le réseau revient.');
});

it('rend x-sos-button en grand format géré par l’hôte et x-alerts.notified alimenté par la page', function (): void {
    $this->blade('<x-sos-button :fixed="false" size="lg" panel="inline" :managed="true" action="sos-send" id="p" />')
        ->assertSee('class="xm-sos xm-sos--lg" id="p"', false)
        ->assertSee('\\u0022managed\\u0022:true', false)
        ->assertSee('\\u0022floating\\u0022:false', false)
        ->assertSee('xm-sos-panel--inline', false)
        ->assertSee('class="btn btn-dark btn-lg flex-fill"', false)
        ->assertSee(__('common.close'));

    $this->blade('<x-sos-button />')
        ->assertSee('class="xm-sos" id="xmSos"', false)
        ->assertSee('\\u0022floating\\u0022:true', false)
        ->assertDontSee('xm-sos-panel--inline', false);

    $this->blade('<x-alerts.notified bind="notifiedOptions()" />')
        ->assertSee('x-data="xmNotified(Object.assign(JSON.parse(', false)
        ->assertSee(', notifiedOptions()))"', false);
    $this->blade('<x-alerts.notified />')->assertSee('x-data="xmNotified(JSON.parse(', false);
});

it('rattache le SOS du bouton flottant au compte par la session web (Sanctum stateful), puis le suit sur /sos?alerte=', function (): void {
    $member = AlertFixtures::member();
    $referer = rtrim((string) config('app.url'), '/').'/fil';

    // L'hôte d'APP_URL est toujours stateful, même avec SANCTUM_STATEFUL_DOMAINS renseigné (config/sanctum.php).
    expect(EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/api/v1/sos', 'POST', server: ['HTTP_REFERER' => $referer])))->toBeTrue()
        ->and(EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/api/v1/sos', 'POST', server: ['HTTP_REFERER' => 'https://ailleurs.example/'])))->toBeFalse();

    $response = $this->withSession([auth()->guard('web')->getName() => $member->getKey()])
        ->withHeaders(['Referer' => $referer, HandleIdempotencyKey::HEADER => (string) Str::uuid()])
        ->postJson('/api/v1/sos', [])
        ->assertCreated()
        ->assertJsonMissingPath('data.sos_token')
        ->assertJsonPath('data.located', false);

    $alert = Alert::query()->where('ulid', $response->json('data.id'))->firstOrFail();
    expect($alert->user_id)->toBe($member->id);

    $config = sosPageConfig($this->get('/sos?alerte='.$alert->ulid)->assertOk()->getContent());
    expect($config['initial']['data']['id'])->toBe($alert->ulid)
        ->and($config['initial']['data']['located'])->toBeFalse();
});

it('dit d’après l’API si une position est jointe, sans jamais la redonner', function (): void {
    $member = AlertFixtures::member();
    $located = app(TriggerSos::class)(AlertFixtures::sos(['userId' => $member->id, 'lat' => 6.3654, 'lng' => 2.4183, 'shareLocation' => true]))->alert;

    $data = sosPageConfig($this->actingAs($member)->get('/sos?alerte='.$located->ulid)->getContent())['initial']['data'];
    expect($data['located'])->toBeTrue()
        ->and(json_encode($data))->not->toContain('6.3654');
});

it('confirme ce que l’envoi transmet vraiment : sans position pour le bouton flottant, selon la case sur /sos', function (): void {
    $member = AlertFixtures::member();

    $fab = $this->actingAs($member)->get('/fil')->assertOk();
    expect(preg_match('/x-data="xmSos\(JSON\.parse\(\'(.*?)\'\)\)"/s', $fab->getContent(), $m))->toBe(1);
    $button = json_decode(json_decode('"'.$m[1].'"'), true, 512, JSON_THROW_ON_ERROR);
    expect($button['texts']['confirmText'])->toBe(__('alerts.sos.fab.confirm'))
        ->and($button['texts']['confirmText'])->toContain('sans ta position');

    $page = $this->get('/sos')->assertOk();
    $page->assertSee(', { confirm: () => (sosConfirmText()) }))"', false);
    $config = sosPageConfig($page->getContent());
    expect($config['texts']['confirmShared'])->toBe(__('alerts.sos.confirm_shared'))
        ->and($config['texts']['confirmUnshared'])->toBe(__('alerts.sos.confirm_unshared'))
        ->and(__('components.sos.confirm_text'))->not->toContain('position');
});

it('ouvre le panneau du bouton sans focus automatique (Espace maintenue n’active pas « Annuler »)', function (): void {
    $this->blade('<x-sos-button />')
        ->assertSee('x-trap.noscroll.noautofocus.noreturn="open"', false)
        ->assertSee('tabindex="-1" data-sos-title', false);
});

it('garde un lien vers /sos à la place du bouton flottant sans JavaScript', function (): void {
    $this->actingAs(AlertFixtures::member())->get('/fil')->assertOk()
        ->assertSee('<div class="xm-sos-wrap xm-sos-wrap--fixed xm-sos-fab" x-init="$el.remove()">', false)
        ->assertSee('<a class="xm-sos" href="'.route('sos').'" aria-label="'.e(__('alerts.sos.fab_title')).'">', false)
        ->assertSee('x-cloak="x-cloak"', false);
});

it('lit les durées du bouton dans la configuration et masque l’étape sans numéro d’urgence', function (): void {
    config([
        'xomiton.alerts.sos_hold_ms' => 1500,
        'xomiton.alerts.sos_countdown_seconds' => 3,
        'xomiton.alerts.sos_locate_seconds' => 6,
        'xomiton.alerts.sos_manual_seconds' => 7,
        'xomiton.assistant.emergency_numbers' => [],
    ]);
    Settings::set('emergency_numbers', []);

    $response = $this->get('/sos')->assertOk()
        ->assertSee('\\u0022hold\\u0022:1500', false)
        ->assertSee('\\u0022countdown\\u0022:3', false)
        ->assertSee(__('alerts.sos.countdown_hint', ['seconds' => 3]))
        ->assertSee(__('alerts.sos.done.steps.4'))
        ->assertDontSee(Str::before(trans('alerts.sos.done.steps.3'), ':number'))
        ->assertDontSee('class="text-danger small', false)
        ->assertSee('alert alert-warning', false);

    $config = sosPageConfig($response->getContent());
    expect($config['manualSeconds'])->toBe(7)->and($config['locateTimeout'])->toBe(6000);

    Settings::set('emergency_numbers', [['label' => 'Police', 'number' => '117']]);
    $this->get('/sos')->assertSee(__('alerts.sos.done.steps.3', ['number' => '117']));
});
