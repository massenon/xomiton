<?php

declare(strict_types=1);

use App\Models\DatabaseNotification;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\EventReminder;
use App\Notifications\InvitationReceived;
use Database\Seeders\NewsletterTaxonomySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;

/*
 * Écrans Vue « Paramètres » (P8-09, prototype settings.php) et « Notifications » (P8-10, prototype notifications.php) :
 * pages Inertia `Member/Settings` et `Member/Notifications` dans le gabarit membre, état initial de chaque section sous
 * la forme de l'API me/* (P6-06, P8-09), titre d'onglet neutre appliqué par le gabarit Inertia (D8), filtres de la
 * page des notifications (`?filtre=`, lecture tolérante) et état lu en base.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, NewsletterTaxonomySeeder::class]);
    $this->zone = Zone::factory()->create(['name' => 'Zou']);
    $this->member = User::factory()->withProfile(['bio' => 'Bonjour'], ['theme' => 'dark', 'notification_digest' => 'weekly', 'sos_share_gps' => false])
        ->create(['pseudo' => 'Awa_S', 'name' => 'Awa Sossou', 'zone_id' => $this->zone->id]);
    $this->member->assignRole('membre');
});

it('sert /parametres : sections du prototype dans la forme de l’API me/*', function (): void {
    $this->member->createToken('Pixel 7');

    $this->actingAs($this->member)->get('/parametres')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Settings')
            ->where('title', 'Paramètres')
            ->where('identity.name', 'Awa Sossou')
            ->where('identity.pseudo', 'Awa_S')
            ->where('identity.bio', 'Bonjour')
            ->where('identity.zone_id', $this->zone->id)
            ->where('zones.0.name', 'Zou')
            ->where('privacy', ['visibility' => 'community', 'show_zone' => true, 'searchable' => true, 'sos_share_gps' => false, 'sensitive_consent' => false])
            ->where('visibilities.0', ['value' => 'public', 'label' => 'Tout le monde'])
            ->where('notifications.categories.0', ['key' => 'securite', 'locked' => true])
            ->where('notifications.categories.1', ['key' => 'sos', 'locked' => true])
            ->where('notifications.categories.2', ['key' => 'alertes', 'locked' => false])
            ->where('notifications.prefs.securite', ['in_app' => true, 'email' => true, 'push' => true])
            ->where('notifications.channels', ['in_app', 'email', 'push'])
            ->where('notifications.digest', 'weekly')
            ->where('notifications.digests', ['never', 'daily', 'weekly'])
            ->where('security.discreet', false)
            ->where('appearance.theme', 'dark')
            ->where('language', ['current' => 'fr', 'available' => ['fr'], 'planned' => ['en', 'fon', 'yo']])
            ->where('newsletter.subscribed', false)
            ->has('newsletter.available_interests')
            ->where('sessions.0.name', 'Pixel 7')
            ->where('devices', [])
            ->where('limits.password_min', 10)
            ->where('limits.bio_max', 200)
            ->where('can.delete_account', true));
});

it('ne propose pas la suppression du compte à l’administration (403 sur DELETE me)', function (): void {
    $admin = User::factory()->withProfile()->create();
    $admin->assignRole('admin');

    $this->actingAs($admin)->get('/parametres')
        ->assertInertia(fn (Assert $page) => $page->component('Member/Settings')->where('can.delete_account', false));
});

it('applique le titre d’onglet neutre dès la page suivante (gabarit Inertia, D8)', function (): void {
    $this->actingAs($this->member)->get('/parametres')->assertOk()->assertSee('<title inertia>Paramètres – Xomiton</title>', false);

    $this->actingAs($this->member)->patchJson('/api/v1/me/security', ['neutral_tab_title' => true])->assertOk();

    $this->actingAs($this->member->fresh())->get('/parametres')
        ->assertOk()
        ->assertSee('<title inertia>'.__('seo.noindex.neutral').'</title>', false)
        ->assertDontSee('Paramètres – Xomiton', false)
        ->assertInertia(fn (Assert $page) => $page->where('ui.neutral_title', true)->where('security.neutral_tab_title', true));
});

it('sert /notifications : filtres avec badge de non lues, liste de la cloche, état lu en base', function (): void {
    DatabaseNotification::factory()->forUser($this->member)->inCategory('communaute')->create(['type' => InvitationReceived::class]);
    DatabaseNotification::factory()->forUser($this->member)->inCategory('evenements')->read()->create(['type' => EventReminder::class]);
    DatabaseNotification::factory()->forUser(User::factory()->create())->create();

    $this->actingAs($this->member)->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Member/Notifications')
            ->where('title', 'Notifications')
            ->where('tab', 'all')
            ->where('tabs', [
                ['key' => 'all', 'param' => null],
                ['key' => 'invitations', 'param' => 'invitations'],
                ['key' => 'events', 'param' => 'evenements'],
                ['key' => 'exchanges', 'param' => 'echanges'],
                ['key' => 'system', 'param' => 'systeme'],
            ])
            ->where('counts.all', ['all' => 2, 'unread' => 1])
            ->where('counts.invitations', ['all' => 1, 'unread' => 1])
            ->where('counts.events', ['all' => 1, 'unread' => 0])
            ->has('notifications.data', 2)
            ->where('notifications.meta.total', 2)
            ->where('per_page', 20));

    $this->actingAs($this->member)->get('/notifications?filtre=evenements')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'events')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.tab', 'events')
            ->where('notifications.data.0.read', true));

    // Lecture tolérante : filtre inconnu → « Toutes ».
    $this->actingAs($this->member)->get('/notifications?filtre=inconnu')
        ->assertOk()->assertInertia(fn (Assert $page) => $page->where('tab', 'all')->has('notifications.data', 2));
});

it('neutralise sur /notifications le texte d’une notification sensible quand les aperçus sont masqués (ou le mode discret actif)', function (string $how): void {
    DatabaseNotification::factory()->forUser($this->member)->sensitive()
        ->create(['data' => ['title' => 'Alerte de Cotonou', 'body' => 'Détail de l’alerte', 'url' => '/alertes']]);

    $this->actingAs($this->member)->get('/notifications')
        ->assertInertia(fn (Assert $page) => $page->where('notifications.data.0.title', 'Alerte de Cotonou'));

    if ($how === 'aperçus masqués') {
        $this->member->settings()->update(['mask_sensitive_notifications' => true]);
    } else {
        // Le mode discret masque aussi les aperçus (UpdateSecuritySettings).
        $this->actingAs($this->member)->patchJson('/api/v1/me/security', ['discreet' => true])->assertOk();
    }

    app('auth')->forgetGuards();
    $this->actingAs($this->member->fresh())->get('/notifications')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('notifications.data.0.title', __('notifications.discreet.title'))
            ->where('notifications.data.0.body', __('notifications.discreet.body'))
            ->where('notifications.data.0.sensitive', true));
})->with(['aperçus masqués', 'mode discret']);

it('classe une notification sur la seule colonne category, comme le filtre SQL qui la liste', function (): void {
    // Ligne ancienne ou importée : colonne vide, catégorie seulement dans `data` → onglet « Système », celui qui la compte.
    DatabaseNotification::factory()->forUser($this->member)->inCategory('evenements')
        ->create(['category' => null, 'data' => ['title' => 'Ancienne', 'category' => 'evenements']]);

    $this->actingAs($this->member)->get('/notifications?filtre=systeme')
        ->assertInertia(fn (Assert $page) => $page
            ->where('tab', 'system')
            ->has('notifications.data', 1)
            ->where('notifications.data.0.tab', 'system')
            ->where('counts.system', ['all' => 1, 'unread' => 1]));
});

it('exige la connexion pour /parametres et /notifications', function (string $uri): void {
    $this->get($uri)->assertRedirect('/connexion');
})->with(['/parametres', '/notifications']);
