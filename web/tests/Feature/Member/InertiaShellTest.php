<?php

declare(strict_types=1);

use App\Application\Offline\OutboxAccount;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\DatabaseNotification;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Coquille Inertia de l'espace membre (P6-02, ADR-30) : réponse Inertia (première visite HTML et visite X-Inertia JSON),
 * props partagées (auth.user réduit, can, counters, flash, lang, ui), titre d'onglet neutre (<title inertia>), noindex,
 * no-store et historique chiffré, Ziggy limité au groupe « member », squelette avant hydratation, pas de SSR, 409 de
 * version, déconnexion qui efface l'historique ; /fil en Inertia sous le même middleware (gabarit membre Vue, P6-03).
 * Page de démonstration : /design-system/vue (local et recette).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->app['env'] = 'local';
    $this->member = User::factory()->withProfile([
        'gender_identity' => 'femme',
        'orientation' => 'lesbienne',
        'trusted_contact_phone' => '+22997000000',
        'trusted_contact_name' => 'Sœur',
    ])->create(['pseudo' => 'Awa_S']);
    $this->member->assignRole('membre');
});

it('rend la première visite dans la racine app.blade.php : noindex, squelette, Ziggy membre, pas de SSR', function (): void {
    $response = $this->actingAs($this->member)->get('/design-system/vue')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSee('<title inertia>'.e(__('design_system.vue.title')).' – Xomiton</title>', false)
        ->assertSee('<div id="app" data-page="', false)
        ->assertSee('xm-skeleton placeholder-glow', false)
        ->assertDontSee('property="og:title"', false);

    $html = $response->getContent();
    expect($html)->toContain('"fil":{"uri":"fil"')
        ->not->toContain('"admin.')
        ->not->toContain('"api.')
        ->not->toContain('debugbar.')
        ->not->toContain('media.signed');

    $response->assertInertia(fn (Assert $page) => $page
        ->component('DesignSystem/Index')
        ->where('auth.user.pseudo', 'Awa_S')
        ->where('auth.user.email_verified', true)
        ->missing('auth.user.email')
        ->missing('auth.user.trusted_contact_phone')
        ->missing('auth.user.profile.trusted_contact_phone')
        ->missing('auth.user.profile.trusted_contact_name')
        ->missing('auth.user.profile.gender_identity')
        ->missing('auth.user.profile.orientation')
        ->missing('auth.user.gender_identity')
        ->missing('auth.user.orientation')
        ->where('can', fn ($can): bool => $can['posts.create'] === true && $can['admin.access'] === false)
        ->where('counters.notifications', 0)
        ->where('counters.messages', 0)
        ->where('flash.toast', null)
        ->where('lang.locale', 'fr')
        ->where('ui.neutral_title', false)
        ->where('ui.neutral_label', 'Notes')
        ->where('ui.outbox.account', OutboxAccount::fingerprint($this->member))
        ->where('ui.outbox.ttl_days', (int) config('xomiton.alerts.draft_days'))
        ->where('ui.bell_poll_seconds', (int) config('xomiton.feed.bell_poll_seconds'))
        ->where('ui.sos.hold_ms', (int) config('xomiton.alerts.sos_hold_ms'))
        ->where('ui.sos.countdown_seconds', (int) config('xomiton.alerts.sos_countdown_seconds'))
        ->has('errors'));

    expect(config('inertia.ssr.enabled'))->toBeFalse()
        ->and($html)->not->toContain('+22997000000')
        ->and($html)->not->toContain('lesbienne');
});

it('répond en JSON à une visite X-Inertia avec les props partagées', function (): void {
    $version = app(App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $this->actingAs($this->member)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/design-system/vue')
        ->assertOk()
        ->assertHeader('X-Inertia', 'true')
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertJsonPath('component', 'DesignSystem/Index')
        ->assertJsonPath('url', '/design-system/vue')
        ->assertJsonPath('props.auth.user.pseudo', 'Awa_S')
        ->assertJsonPath('props.can', fn (array $can): bool => array_key_exists('peer_space.access', $can) && $can['peer_space.access'] === false)
        ->assertJsonPath('props.lang.locale', 'fr');
});

it('neutralise le titre d’onglet quand user_settings.neutral_tab_title est actif', function (): void {
    $this->member->settings()->updateOrCreate([], ['neutral_tab_title' => true]);

    $this->actingAs($this->member->fresh())->get('/design-system/vue')
        ->assertOk()
        ->assertSee('<title inertia>Notes</title>', false)
        ->assertDontSee(e(__('design_system.vue.title')).' – Xomiton</title>', false)
        ->assertInertia(fn (Assert $page) => $page
            ->where('ui.neutral_title', true)
            ->where('auth.user.settings.neutral_tab_title', true));
});

it('compte les notifications non lues et les conversations non lues hors sourdine', function (): void {
    DatabaseNotification::factory()->count(2)->create(['notifiable_id' => $this->member->id]);
    DatabaseNotification::factory()->create(['notifiable_id' => $this->member->id, 'read_at' => now()]);

    $other = User::factory()->create();
    $unread = Conversation::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $unread->id, 'user_id' => $this->member->id]);
    Message::factory()->count(3)->create(['conversation_id' => $unread->id, 'user_id' => $other->id]);

    $muted = Conversation::factory()->create();
    ConversationParticipant::factory()->muted()->create(['conversation_id' => $muted->id, 'user_id' => $this->member->id]);
    Message::factory()->create(['conversation_id' => $muted->id, 'user_id' => $other->id]);

    $read = Conversation::factory()->create();
    $last = Message::factory()->create(['conversation_id' => $read->id, 'user_id' => $other->id]);
    ConversationParticipant::factory()->create(['conversation_id' => $read->id, 'user_id' => $this->member->id, 'last_read_message_id' => $last->id]);

    $mine = Conversation::factory()->create();
    ConversationParticipant::factory()->create(['conversation_id' => $mine->id, 'user_id' => $this->member->id]);
    Message::factory()->create(['conversation_id' => $mine->id, 'user_id' => $this->member->id]);

    $this->actingAs($this->member)->get('/design-system/vue')
        ->assertInertia(fn (Assert $page) => $page
            ->where('counters.notifications', 2)
            ->where('counters.messages', 1));
});

it('partage le toast et le statut flashés avec un identifiant, type ramené à un ToastType', function (): void {
    $this->actingAs($this->member)
        ->withSession(['toast' => 'Ta publication est en ligne.', 'toast_type' => 'success', 'status' => 'Profil enregistré.', 'status_type' => 'light" onclick="x'])
        ->get('/design-system/vue')
        ->assertInertia(fn (Assert $page) => $page
            ->where('flash.toast.message', 'Ta publication est en ligne.')
            ->where('flash.toast.type', 'success')
            ->where('flash.toast.id', fn (string $id): bool => strlen($id) === 16)
            ->where('flash.status.message', 'Profil enregistré.')
            ->where('flash.status.type', 'primary')
            ->where('flash.status.id', fn (string $id): bool => strlen($id) === 16));
});

it('ne garde aucune page membre en cache et chiffre l’historique Inertia', function (): void {
    $version = app(App\Http\Middleware\HandleInertiaRequests::class)->version(request());

    $html = $this->actingAs($this->member)->get('/design-system/vue')->assertOk();
    expect($html->headers->get('Cache-Control'))->toContain('no-store')->toContain('private')
        ->and($html->viewData('page')['encryptHistory'])->toBeTrue()
        ->and($html->viewData('page')['clearHistory'])->toBeFalse();

    $json = $this->actingAs($this->member)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => (string) $version, 'X-Requested-With' => 'XMLHttpRequest'])
        ->get('/design-system/vue')
        ->assertOk()
        ->assertJsonPath('encryptHistory', true);
    expect($json->headers->get('Cache-Control'))->toContain('no-store');
});

it('sert /fil en Inertia sous le middleware membre (critère reporté à P6-03) : noindex, no-store', function (): void {
    $response = $this->actingAs($this->member)->get('/fil')
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<div id="app" data-page="', false)
        ->assertInertia(fn (Assert $page) => $page->component('Member/Placeholder'));

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});

it('déconnexion depuis Inertia : 409 vers l’accueil, historique effacé à la visite Inertia suivante', function (): void {
    $this->app['env'] = 'testing'; // jeton CSRF non vérifié en test (hors « local »)
    $this->actingAs($this->member)
        ->withHeaders(['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest'])
        ->post('/deconnexion')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', route('home'))
        ->assertSessionHas('inertia.clear_history', true)
        ->assertSessionHas('toast', __('auth.logout.done'));

    $this->assertGuest('web');

    $this->app['env'] = 'local';
    $page = $this->flushHeaders()->actingAs($this->member)->get('/design-system/vue')->assertOk()->viewData('page');
    expect($page['clearHistory'])->toBeTrue();
});

it('déconnexion sans Inertia : redirection classique vers l’accueil', function (): void {
    $this->app['env'] = 'testing';
    $this->actingAs($this->member)->post('/deconnexion')
        ->assertRedirect(route('home'))
        ->assertSessionHas('inertia.clear_history', true);
});

it('demande la connexion sans session et répond 404 hors local et recette', function (): void {
    $this->get('/design-system/vue')->assertRedirect(route('connexion'));

    $this->app['env'] = 'production';
    $this->actingAs($this->member)->get('/design-system/vue')->assertNotFound();
});

it('renvoie 409 et l’adresse à recharger quand la version des assets a changé', function (): void {
    // Version d'assets factice (sans manifest Vite) : Inertia la dérive de app.asset_url quand elle est définie.
    config(['app.asset_url' => 'https://assets.xomiton.test']);
    expect(app(App\Http\Middleware\HandleInertiaRequests::class)->version(request()))->not->toBeNull();

    $this->actingAs($this->member)
        ->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => 'ancienne-version'])
        ->get('/design-system/vue')
        ->assertStatus(409)
        ->assertHeader('X-Inertia-Location', url('/design-system/vue'));
});
