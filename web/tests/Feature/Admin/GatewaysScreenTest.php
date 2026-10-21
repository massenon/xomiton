<?php

declare(strict_types=1);

use App\Application\Gateways\ManualPublisher;
use App\Application\Gateways\SocialPublishers;
use App\Enums\CccKind;
use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundStatus;
use App\Enums\SocialNetwork;
use App\Livewire\Admin\Gateways\Index as GatewaysScreen;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\GatewayTemplate;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\PeerRoom;
use App\Models\SocialChannel;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SocialChannelsSeeder;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/*
 * P12-08 : Passerelles /admin/passerelles (Livewire) — `gateways.connect` (admin, superadmin), cartes des six réseaux
 * (connecter / déconnecter, jeton chiffré jamais relu), planification, file (marquer publié à la main, annuler, retirer),
 * modèles de messages clés, salons entre pair·es. Aucun connecteur réel : ManualPublisher seulement.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SocialChannelsSeeder::class]);
});

function gatewayUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function gatewayScreen(string $role = 'admin')
{
    test()->actingAs(gatewayUser($role));

    return Livewire::test(GatewaysScreen::class);
}

function channel(SocialNetwork $network): SocialChannel
{
    return SocialChannel::query()->ofNetwork($network)->firstOrFail();
}

function connectedChannel(SocialNetwork $network): SocialChannel
{
    $channel = channel($network);
    $channel->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.bj'])->save();

    return $channel;
}

it('réserve /admin/passerelles à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(gatewayUser($role))->get('/admin/passerelles')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('affiche les six réseaux, le bandeau d’information et aucun jeton', function (): void {
    $telegram = channel(SocialNetwork::Telegram);
    $telegram->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.bj', 'access_token' => 'jeton-secret-123'])->save();

    $this->actingAs(gatewayUser())->get('/admin/passerelles')->assertOk()
        ->assertSee('Facebook')->assertSee('Roméo')->assertSee('GanXo')->assertSee('Tinder')->assertSee('TikTok')->assertSee('Telegram')
        ->assertSee('@xomiton.bj')->assertSee('Aucun connecteur automatique')->assertDontSee('jeton-secret-123');
});

it('connecte un réseau avec un jeton chiffré en base, valide l’identifiant et journalise sans le jeton', function (): void {
    $facebook = channel(SocialNetwork::Facebook);
    $screen = gatewayScreen();

    $screen->call('openConnect', $facebook->id)->set('account', 'x')->call('connect')->assertHasErrors(['account']);
    $screen->set('account', 'xomiton.bj')->set('token', 'jeton-secret-123')->call('connect')->assertHasNoErrors();

    $facebook->refresh();
    expect($facebook->is_connected)->toBeTrue()->and($facebook->account_handle)->toBe('@xomiton.bj')->and($facebook->access_token)->toBe('jeton-secret-123')
        ->and($facebook->connected_by)->not->toBeNull();
    // Chiffré au repos : la valeur brute ne contient jamais le jeton en clair.
    expect((string) DB::table('social_channels')->where('id', $facebook->id)->value('access_token'))->not->toContain('jeton-secret-123');
    $log = AuditLog::query()->where('action', 'gateway.connected')->firstOrFail();
    expect(json_encode($log->toArray()))->not->toContain('jeton-secret-123');

    // Réseau sans API : aucun jeton conservé même s'il est fourni.
    $romeo = channel(SocialNetwork::Romeo);
    $screen->call('openConnect', $romeo->id)->set('account', '@xomiton_bj')->set('token', 'ignore-moi')->call('connect')->assertHasNoErrors();
    expect($romeo->fresh()->access_token)->toBeNull()->and($romeo->fresh()->is_connected)->toBeTrue();
});

it('déconnecte un réseau et efface son jeton, sauf si une publication planifiée le vise encore', function (): void {
    $telegram = channel(SocialNetwork::Telegram);
    $telegram->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.bj', 'access_token' => 'jeton-secret-123'])->save();
    $post = OutboundPost::factory()->targeting([$telegram])->create();
    $screen = gatewayScreen();

    $screen->call('disconnect', $telegram->id);
    expect($telegram->fresh()->is_connected)->toBeTrue();

    $screen->call('cancelPost', $post->id)->call('removePost', $post->id)->call('disconnect', $telegram->id);
    $telegram->refresh();
    expect($telegram->is_connected)->toBeFalse()->and($telegram->access_token)->toBeNull()->and($telegram->account_handle)->toBeNull();
});

it('planifie une publication sur les seuls réseaux connectés : BD, message clé ou texte personnalisé', function (): void {
    $telegram = connectedChannel(SocialNetwork::Telegram);
    $facebook = channel(SocialNetwork::Facebook);
    $bd = CccTool::factory()->create(['kind' => CccKind::Comic, 'key' => 'bd77', 'title' => 'BD sur le consentement']);
    $template = GatewayTemplate::factory()->create(['title' => 'Modèle SOS', 'body' => 'Tu es en danger ? Utilise le bouton SOS Xomiton, une personne de confiance te répond.']);
    $screen = gatewayScreen();
    $future = now()->addDay()->format('Y-m-d\TH:i');

    // Réseau non connecté : refusé.
    $screen->set('postForm', ['kind' => 'custom', 'reference' => '', 'message' => 'Un message personnalisé suffisamment long.', 'channels' => [(string) $facebook->id], 'at' => $future])
        ->call('schedule')->assertHasErrors(['postForm.channels.0']);

    // Message trop court / trop long, date passée.
    $screen->set('postForm', ['kind' => 'custom', 'reference' => '', 'message' => 'Court', 'channels' => [(string) $telegram->id], 'at' => $future])->call('schedule')->assertHasErrors(['postForm.message']);
    $screen->set('postForm.message', str_repeat('a', 501))->call('schedule')->assertHasErrors(['postForm.message']);
    $screen->set('postForm', ['kind' => 'custom', 'reference' => '', 'message' => 'Un message personnalisé suffisamment long.', 'channels' => [(string) $telegram->id], 'at' => now()->subDay()->format('Y-m-d\TH:i')])->call('schedule')->assertHasErrors(['postForm.at']);
    expect(OutboundPost::query()->count())->toBe(0);

    $screen->set('postForm', ['kind' => 'custom', 'reference' => '', 'message' => 'Un message personnalisé suffisamment long.', 'channels' => [(string) $telegram->id], 'at' => $future])->call('schedule')->assertHasNoErrors();
    $screen->set('postForm', ['kind' => 'bd', 'reference' => 'bd77', 'message' => '', 'channels' => [(string) $telegram->id], 'at' => $future])->call('schedule')->assertHasNoErrors();
    $screen->set('postForm', ['kind' => 'template', 'reference' => (string) $template->id, 'message' => '', 'channels' => [(string) $telegram->id], 'at' => $future])->call('schedule')->assertHasNoErrors();

    $posts = OutboundPost::query()->orderBy('id')->get();
    expect($posts)->toHaveCount(3)->and($posts[1]->reference)->toBe('bd77')->and($posts[1]->message)->toBe('BD sur le consentement')
        ->and($posts[2]->message)->toBe($template->body)->and($posts[0]->deliveries()->count())->toBe(1)
        ->and($posts[0]->deliveries()->first()->status)->toBe(GatewayDeliveryStatus::Pending);
    $screen->assertSee('BD sur le consentement')->assertSee('Modèle SOS');
});

it('marque une remise publiée à la main : la publication passe à « publiée » quand toutes ses remises le sont', function (): void {
    $telegram = connectedChannel(SocialNetwork::Telegram);
    $romeo = connectedChannel(SocialNetwork::Romeo);
    $post = OutboundPost::factory()->targeting([$telegram, $romeo])->create(['message' => 'Texte prêt à copier pour les réseaux.']);
    [$first, $second] = $post->deliveries()->orderBy('id')->get()->all();
    $screen = gatewayScreen();

    expect(app(SocialPublishers::class)->for($romeo))->toBeInstanceOf(ManualPublisher::class)
        ->and(ManualPublisher::copyText($post))->toBe('Texte prêt à copier pour les réseaux.');

    $screen->call('markDelivered', $first->id);
    expect($first->fresh()->status)->toBe(GatewayDeliveryStatus::Manual)->and($first->fresh()->marked_by)->not->toBeNull()->and($post->fresh()->status)->toBe(OutboundStatus::Scheduled);
    $screen->call('markDelivered', $second->id);
    expect($post->fresh()->status)->toBe(OutboundStatus::Published)->and($post->fresh()->published_at)->not->toBeNull();

    // Une publication publiée ne se retire pas, une remise déjà livrée ne se remarque pas.
    $screen->call('removePost', $post->id);
    expect(OutboundPost::query()->whereKey($post->id)->exists())->toBeTrue();
    $screen->call('markDelivered', $first->id);
    expect(AuditLog::query()->where('action', 'gateway.delivery_marked')->count())->toBe(2);
});

it('annule puis retire une publication planifiée et interdit d’agir sur une publication annulée', function (): void {
    $telegram = connectedChannel(SocialNetwork::Telegram);
    $post = OutboundPost::factory()->targeting([$telegram])->create();
    $delivery = $post->deliveries()->firstOrFail();
    $screen = gatewayScreen();

    $screen->call('cancelPost', $post->id);
    expect($post->fresh()->status)->toBe(OutboundStatus::Cancelled);
    $screen->call('markDelivered', $delivery->id);
    expect($delivery->fresh()->status)->toBe(GatewayDeliveryStatus::Pending);
    $screen->call('removePost', $post->id);
    expect(OutboundPost::query()->count())->toBe(0)->and(OutboundDelivery::query()->count())->toBe(0);
});

it('gère les modèles de messages clés et les salons entre pair·es', function (): void {
    $screen = gatewayScreen();

    $screen->call('newTemplate')->set('templateForm', ['title' => 'Ok', 'body' => 'Trop court', 'is_active' => true])->call('saveTemplate')->assertHasErrors(['templateForm.title', 'templateForm.body']);
    $screen->set('templateForm', ['title' => 'Prévention', 'body' => 'Le consentement est libre, enthousiaste et réversible, à chaque fois.', 'is_active' => true])->call('saveTemplate')->assertHasNoErrors();
    $template = GatewayTemplate::query()->where('title', 'Prévention')->firstOrFail();
    $screen->call('deleteTemplate', $template->id);
    expect(GatewayTemplate::query()->where('title', 'Prévention')->exists())->toBeFalse();

    $screen->call('newRoom')->set('roomForm', ['name' => 'Salon', 'description' => '', 'is_moderated' => true, 'allow_anonymous' => true, 'max_members' => '4', 'is_active' => true])->call('saveRoom')->assertHasErrors(['roomForm.max_members']);
    $screen->set('roomForm.max_members', '501')->call('saveRoom')->assertHasErrors(['roomForm.max_members']);
    $screen->set('roomForm.max_members', '40')->call('saveRoom')->assertHasNoErrors();
    $room = PeerRoom::query()->where('name', 'Salon')->firstOrFail();
    expect($room->max_members)->toBe(40)->and($room->slug)->not->toBe('');
    $screen->call('toggleRoom', $room->id);
    expect($room->fresh()->is_active)->toBeFalse();
});
