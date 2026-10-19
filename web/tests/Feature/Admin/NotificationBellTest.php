<?php

declare(strict_types=1);

use App\Livewire\Admin\NotificationBell;
use App\Models\DatabaseNotification;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Livewire;

/*
 * P10-03 : cloche de l'administration — onglets Non lus · Lues · Toutes, sources de l'équipe seulement (SOS, alertes,
 * modération, système), ouverture = lecture + lien interne, « Tout marquer comme lu », polling 30 s, lien vers le
 * journal d'audit selon la permission, texte neutralisé si la personne masque le contenu sensible.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
    $this->actingAs($this->admin);
});

function bellNotification(User $user, string $category, array $attributes = []): DatabaseNotification
{
    return DatabaseNotification::factory()->forUser($user)->inCategory($category)->create($attributes);
}

it('compte et liste les seules notifications de l’équipe, non lues par défaut', function (): void {
    bellNotification($this->admin, DatabaseNotification::CATEGORY_SOS, ['data' => ['title' => 'Alerte SOS · Cotonou', 'body' => '', 'url' => '/admin/alertes']]);
    bellNotification($this->admin, DatabaseNotification::CATEGORY_MODERATION, ['data' => ['title' => 'Contenu signalé', 'body' => 'Publication', 'url' => '/admin/moderation']]);
    bellNotification($this->admin, DatabaseNotification::CATEGORY_SYSTEM, ['data' => ['title' => 'Conflit de synchro', 'body' => '', 'url' => '/admin/synchronisation'], 'read_at' => now()]);
    bellNotification($this->admin, DatabaseNotification::CATEGORY_MESSAGES, ['data' => ['title' => 'Nouveau message privé', 'body' => '', 'url' => '/messages']]);
    bellNotification(User::factory()->create(), DatabaseNotification::CATEGORY_SOS, ['data' => ['title' => 'SOS d’un autre compte', 'body' => '', 'url' => '/']]);

    Livewire::test(NotificationBell::class)
        ->assertSet('tab', 'unread')
        ->assertViewHas('unread', 2)
        ->assertSee(['Alerte SOS · Cotonou', 'Contenu signalé'])
        ->assertDontSee(['Conflit de synchro', 'Nouveau message privé', 'SOS d’un autre compte'])
        ->assertSeeHtml('aria-selected="true"')
        ->call('setTab', 'read')
        ->assertSee('Conflit de synchro')
        ->assertDontSee('Contenu signalé')
        ->call('setTab', 'all')
        ->assertSee(['Alerte SOS · Cotonou', 'Contenu signalé', 'Conflit de synchro'])
        ->call('setTab', 'n’importe')
        ->assertSet('tab', 'unread');
});

it('ouvre une notification : la marque comme lue et suit son lien interne seulement', function (): void {
    $internal = bellNotification($this->admin, DatabaseNotification::CATEGORY_ALERTS, ['data' => ['title' => 'Alerte routée', 'body' => '', 'url' => '/admin/alertes']]);
    $external = bellNotification($this->admin, DatabaseNotification::CATEGORY_ALERTS, ['data' => ['title' => 'Lien externe', 'body' => '', 'url' => 'https://exemple.invalid/piege']]);

    Livewire::test(NotificationBell::class)->call('open', $internal->id)->assertRedirect('/admin/alertes');
    Livewire::test(NotificationBell::class)->call('open', $external->id)->assertNoRedirect();

    // « /\hote » est normalisé en « //hote » par les navigateurs : refusé, comme « //hote » et « javascript: ».
    foreach (['/\\exemple.invalid/piege', '//exemple.invalid', 'javascript:alert(1)', "/admin\t/x"] as $url) {
        $trap = bellNotification($this->admin, DatabaseNotification::CATEGORY_ALERTS, ['data' => ['title' => 'Piège', 'body' => '', 'url' => $url]]);
        Livewire::test(NotificationBell::class)->call('open', $trap->id)->assertNoRedirect();
    }

    config(['app.url' => 'https://xomiton.test']);
    $absolute = bellNotification($this->admin, DatabaseNotification::CATEGORY_ALERTS, ['data' => ['title' => 'Absolu', 'body' => '', 'url' => 'https://xomiton.test/admin/alertes']]);
    Livewire::test(NotificationBell::class)->call('open', $absolute->id)->assertRedirect('https://xomiton.test/admin/alertes');

    expect($internal->fresh()->read_at)->not->toBeNull()
        ->and($external->fresh()->read_at)->not->toBeNull();
});

it('n’ouvre jamais la notification d’un autre compte', function (): void {
    $other = bellNotification(User::factory()->create(), DatabaseNotification::CATEGORY_SOS);

    Livewire::test(NotificationBell::class)->call('open', $other->id)->assertNoRedirect();

    expect($other->fresh()->read_at)->toBeNull();
});

it('marque tout comme lu dans les catégories de l’équipe, sans toucher aux autres', function (): void {
    $sos = bellNotification($this->admin, DatabaseNotification::CATEGORY_SOS);
    $message = bellNotification($this->admin, DatabaseNotification::CATEGORY_MESSAGES);

    Livewire::test(NotificationBell::class)
        ->call('markAllRead')
        ->assertDispatched('toast', type: 'success', message: 'Notifications marquées comme lues.')
        ->assertViewHas('unread', 0)
        ->assertSee('Aucune notification dans cette liste.');

    expect($sos->fresh()->read_at)->not->toBeNull()->and($message->fresh()->read_at)->toBeNull();
});

it('annonce le nombre de non-lues dans le nom accessible du bouton (sans aria-label figé)', function (): void {
    bellNotification($this->admin, DatabaseNotification::CATEGORY_SOS);
    bellNotification($this->admin, DatabaseNotification::CATEGORY_ALERTS);

    $html = Livewire::test(NotificationBell::class)->html();
    expect($html)->toContain('<span class="visually-hidden" data-xm-bell-label>Notifications, 2 non lues</span>')
        ->not->toContain('aria-label="Notifications"');

    Livewire::test(NotificationBell::class)->call('markAllRead')
        ->assertSeeHtml('<span class="visually-hidden" data-xm-bell-label>Notifications</span>');
});

it('rafraîchit toutes les 30 s et lie le journal d’audit selon la permission', function (): void {
    Livewire::test(NotificationBell::class)
        ->assertSeeHtml('wire:poll.30s')
        ->assertSeeHtml('href="'.route('admin.journal').'"');

    $moderator = User::factory()->create();
    $moderator->syncRoles(['moderateur']);
    $this->actingAs($moderator);

    Livewire::test(NotificationBell::class)->assertDontSeeHtml('href="'.route('admin.journal').'"');
});

it('neutralise une notification sensible quand la personne masque le contenu sensible', function (): void {
    $this->admin->settings()->updateOrCreate([], ['mask_sensitive_notifications' => true]);
    bellNotification($this->admin, DatabaseNotification::CATEGORY_SOS, ['is_sensitive' => true, 'data' => ['title' => 'SOS de Awa à Abomey', 'body' => 'Détail', 'url' => '/admin/alertes']]);

    Livewire::test(NotificationBell::class)
        ->assertDontSee('SOS de Awa à Abomey')
        ->assertSee(__('notifications.discreet.title'));
});
