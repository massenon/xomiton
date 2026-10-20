<?php

declare(strict_types=1);

use App\Models\Follow;
use App\Models\Post;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\View;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Gabarit membre Vue (P6-03) : /fil et les autres écrans membres provisoires sont des pages Inertia `Member/Placeholder`
 * dans MemberLayout (resources/js/layouts/MemberLayout.vue) ; props partagées `shell` (carte profil réelle, SOS flottant
 * sans position, lien d'administration, jeton CSRF du bandeau de vérification ; carte profil en cache court) ; espace
 * pair et collecte réservés à peer_space.access ; lien /sos avant le montage de Vue ; titre d'onglet neutre ;
 * déconnexion Inertia ; gabarit Blade provisoire de P3 supprimé.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->member = User::factory()->create(['pseudo' => 'Awa_S']);
    $this->member->assignRole('membre');
});

it('sert /fil en Inertia dans le gabarit membre Vue, colonnes du fil et props du gabarit', function (): void {
    $response = $this->actingAs($this->member)->get('/fil')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<div id="app" data-page="', false)
        ->assertSee('<title inertia>Fil d’actualité – Xomiton</title>', false)
        ->assertSee('data-xm-sos-fallback><a class="xm-sos" href="'.route('sos').'" aria-label="'.e(__('alerts.sos.fab_title')).'">', false)
        ->assertDontSee('id="memberHeader"', false)
        ->assertDontSee('x-data="xmSosFab(', false)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Feed/Index') // écran réel du fil depuis le lot fil-ecrans (P7-03, tests/Feature/Member/FeedPagesTest.php)
            ->where('title', 'Fil d’actualité')
            ->where('auth.user.pseudo', 'Awa_S')
            ->where('shell.stats', ['posts' => 0, 'followers' => 0, 'following' => 0])
            ->where('shell.sos.endpoint', '/api/v1/sos')
            ->where('shell.sos.number', '117')
            ->where('shell.admin_url', null)
            ->where('shell.csrf_token', fn (string $token): bool => strlen($token) === 40)
            ->where('ui.bell_poll_seconds', (int) config('xomiton.feed.bell_poll_seconds')));

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('sert chaque écran membre provisoire en Inertia, titre de la route même avec un paramètre d’adresse', function (string $uri, string $title, string $prototype): void {
    $this->actingAs($this->member)->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Placeholder')
            ->where('title', $title)
            ->where('prototype', $prototype)
            ->where('aside', false));
})->with([
    // /messages : écran réel depuis le lot messagerie (P8-01, tests/Feature/Member/MessagesPageTest.php).
    // /groupes, /pages : écrans réels depuis le lot groupes-pages (P8-02, P8-03, tests/Feature/Member/CommunityPagesTest.php).
    // /evenements : écran réel depuis le lot evenements (P8-04, tests/Feature/Member/EventsPagesTest.php).
    // /forums : écran réel depuis le lot forums (P8-05, tests/Feature/Member/ForumsPagesTest.php).
    // /qui-suivre : écran réel depuis le lot qui-suivre (P8-06, tests/Feature/Member/WhoToFollowPageTest.php).
    // /profil : écran réel depuis le lot profil (P8-07, tests/Feature/Member/ProfilePagesTest.php).
    // /parametres, /notifications : écrans réels depuis le lot parametres-notifications (P8-09, P8-10,
    // tests/Feature/Member/SettingsNotificationsPagesTest.php).
    // /assistant : écran réel depuis le lot assistant (P8-11, tests/Feature/Member/AssistantPageTest.php).
    // /annuaire : écran réel depuis le lot annuaire-membre (P8-12, tests/Feature/Member/DirectoryPageTest.php).
    // /espace-pair, /collecte : écrans réels depuis le lot espace-pair (P9-03, P9-04, tests/Feature/Member/PeerSpacePagesTest.php).
    ['/medias', 'Médiathèque', 'albums.php'],
    ['/ccc', 'Outils CCC', 'ccc.php'],
]);

it('réserve l’espace pair et la collecte à peer_space.access : 403 pour un·e membre, écran réel pour un·e pair (lot espace-pair, P9-03, P9-04)', function (string $uri, string $component): void {
    $this->actingAs($this->member)->get($uri)->assertForbidden();

    $peer = User::factory()->create();
    $peer->assignRole('pair');

    $this->actingAs($peer)->get($uri)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component($component));
})->with([
    ['/espace-pair', 'PeerSpace/Home'],
    ['/collecte', 'PeerSpace/Collecte'],
]);

it('remplit la carte profil avec les vrais compteurs et le département du profil', function (): void {
    $zone = Zone::factory()->create(['name' => 'Littoral']);
    $this->member->forceFill(['zone_id' => $zone->id])->save();
    Post::factory()->count(2)->create(['user_id' => $this->member->id]);
    Post::factory()->hidden()->create(['user_id' => $this->member->id]); // masquée par la modération : non comptée
    Post::factory()->create(); // publication d'un autre compte
    Follow::factory()->count(3)->create(['followed_id' => $this->member->id]);
    Follow::factory()->create(['follower_id' => $this->member->id]);

    $this->actingAs($this->member->fresh())->get('/fil')
        ->assertInertia(fn (Assert $page) => $page
            ->where('shell.zone', 'Littoral')
            ->where('shell.stats', ['posts' => 2, 'followers' => 3, 'following' => 1])
            ->where('shell.sos.zone_id', $zone->id)
            ->where('shell.sos.commune_id', null));
});

it('garde la carte profil en cache court : pas de recomptage à chaque visite, relu après un changement de département', function (): void {
    config(['xomiton.feed.shell_stats_cache_seconds' => 60]);
    $this->actingAs($this->member)->get('/fil')->assertInertia(fn (Assert $page) => $page->where('shell.stats.posts', 0));

    Post::factory()->create(['user_id' => $this->member->id]);
    $this->actingAs($this->member)->get('/fil')->assertInertia(fn (Assert $page) => $page->where('shell.stats.posts', 0));

    $zone = Zone::factory()->create(['name' => 'Borgou']);
    $this->member->forceFill(['zone_id' => $zone->id])->save();
    $this->actingAs($this->member->fresh())->get('/fil')
        ->assertInertia(fn (Assert $page) => $page->where('shell.zone', 'Borgou')->where('shell.stats.posts', 1));

    config(['xomiton.feed.shell_stats_cache_seconds' => 0]);
    Post::factory()->create(['user_id' => $this->member->id]);
    $this->actingAs($this->member->fresh())->get('/fil')->assertInertia(fn (Assert $page) => $page->where('shell.stats.posts', 2));
});

it('ne transmet au SOS flottant ni position ni coordonnée : endpoint, zone, commune et numéro seulement', function (): void {
    $this->member->settings()->updateOrCreate([], ['sos_share_gps' => true]);

    $this->actingAs($this->member->fresh())->get('/fil')
        ->assertInertia(fn (Assert $page) => $page
            ->where('shell.sos', fn ($sos): bool => array_keys(collect($sos)->all()) === ['endpoint', 'zone_id', 'commune_id', 'number']));
});

it('donne le lien d’administration aux seuls comptes admin.access et l’espace pair aux pairs', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $peer = User::factory()->create();
    $peer->assignRole('pair');

    $this->actingAs($admin)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page->where('shell.admin_url', '/admin')->where('can', fn ($can): bool => $can['admin.access'] === true));

    $this->actingAs($peer)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page->where('shell.admin_url', null)->where('can', fn ($can): bool => $can['peer_space.access'] === true && $can['admin.access'] === false));

    $this->actingAs($this->member)->get('/fil')
        ->assertInertia(fn (Assert $page) => $page->where('can', fn ($can): bool => $can['peer_space.access'] === false));
});

it('rappelle de confirmer l’e-mail : compte non vérifié et routes du bandeau dans Ziggy', function (): void {
    $unverified = User::factory()->unverified()->create();

    $html = $this->actingAs($unverified)->get('/fil')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.email_verified', false))
        ->getContent();

    expect($html)->toContain('"otp.resend":{"uri":"code-de-verification\/renvoyer"')
        ->toContain('"otp":{"uri":"code-de-verification"');
});

it('neutralise le titre d’onglet du fil quand user_settings.neutral_tab_title est actif', function (): void {
    $this->member->settings()->updateOrCreate([], ['neutral_tab_title' => true]);

    $this->actingAs($this->member->fresh())->get('/fil')
        ->assertSee('<title inertia>Notes</title>', false)
        ->assertDontSee('Fil d’actualité – Xomiton', false);
});

it('déconnecte depuis le gabarit Vue : 409 vers l’accueil, session fermée, historique effacé', function (): void {
    $this->app['env'] = 'testing';
    $this->actingAs($this->member)
        ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Referer' => url('/fil')])
        ->post('/deconnexion')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'))
        ->assertSessionHas('inertia.clear_history', true);

    $this->assertGuest('web');
    $this->flushHeaders()->get('/fil')->assertRedirect(route('connexion'));
});

it('ne garde plus le gabarit Blade membre provisoire de P3', function (): void {
    expect(View::exists('components.layouts.member'))->toBeFalse()
        ->and(View::exists('partials.member-header'))->toBeFalse()
        ->and(View::exists('partials.member-sidenav'))->toBeFalse();
});
