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
    $channel->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.app'])->save();

    return $channel;
}

it('réserve /admin/passerelles à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(gatewayUser($role))->get('/admin/passerelles')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('affiche les six réseaux, le bandeau d’information et aucun jeton', function (): void {
    $telegram = channel(SocialNetwork::Telegram);
    $telegram->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.app', 'access_token' => 'jeton-secret-123'])->save();

    $this->actingAs(gatewayUser())->get('/admin/passerelles')->assertOk()
        ->assertSee('Facebook')->assertSee('Roméo')->assertSee('GanXo')->assertSee('Tinder')->assertSee('TikTok')->assertSee('Telegram')
        ->assertSee('@xomiton.app')->assertSee('Aucun connecteur automatique')->assertDontSee('jeton-secret-123');
});

it('connecte un réseau avec un jeton chiffré en base, valide l’identifiant et journalise sans le jeton', function (): void {
    $facebook = channel(SocialNetwork::Facebook);
    $screen = gatewayScreen();

    $screen->call('openConnect', $facebook->id)->set('account', 'x')->call('connect')->assertHasErrors(['account']);
    $screen->set('account', 'xomiton.app')->set('token', 'jeton-secret-123')->call('connect')->assertHasNoErrors();

    $facebook->refresh();
    expect($facebook->is_connected)->toBeTrue()->and($facebook->account_handle)->toBe('@xomiton.app')->and($facebook->access_token)->toBe('jeton-secret-123')
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
    $telegram->forceFill(['is_connected' => true, 'account_handle' => '@xomiton.app', 'access_token' => 'jeton-secret-123'])->save();
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
    expect($posts)->toHaveCount(3)->and($posts[1]->reference)->toBe('bd77')->and($posts[1]->message)->toStartWith('BD sur le consentement')
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

/*
 * P19-11 : calendrier BD — aperçu du message (texte de partage, lien UTM, images), refus d'une seconde diffusion sur le
 * même réseau (sauf « Republier »), planification en lot, identifiant de page ou de canal, erreur lisible dans la file,
 * permission `gateways.publish` revérifiée à chaque action.
 */

function screenComic(string $key = 'vn02', int $position = 1): CccTool
{
    $data = json_decode((string) file_get_contents(database_path('seeders/data/content/violences_numeriques/sextorsion.json')), true);

    return CccTool::factory()->create([
        'kind' => CccKind::Comic, 'key' => $key, 'title' => $data['bd']['title'], 'description' => $data['summary'], 'pages' => $data['bd']['pages'],
        'chapters' => null, 'duration_minutes' => null, 'position' => $position, 'is_published' => true, 'is_public' => true,
    ]);
}

it('prévisualise le message d’une BD pour le réseau choisi et refuse une seconde diffusion sans « Republier »', function (): void {
    $telegram = connectedChannel(SocialNetwork::Telegram);
    screenComic();
    $future = now()->addDay()->format('Y-m-d\TH:i');
    $screen = gatewayScreen();

    $screen->set('postForm', ['kind' => 'bd', 'reference' => 'vn02', 'message' => '', 'channels' => [(string) $telegram->id], 'at' => $future, 'republish' => false])
        ->assertSee('Aperçu pour Telegram')
        ->assertSee('Quelqu’un te menace de diffuser des photos intimes')
        ->assertSee('/bd/vn02?utm_source=telegram&amp;utm_medium=social&amp;utm_campaign=bd', false)
        ->assertSee('assets/images/violences-numeriques/sextorsion/partage.webp', false)
        ->assertSee('Sena, assis·e dans une cour');

    $screen->call('schedule')->assertHasNoErrors();
    $post = OutboundPost::query()->sole();
    expect($post->message)->toStartWith('Quelqu’un te menace')->and($post->is_republish)->toBeFalse();

    $screen->set('postForm', ['kind' => 'bd', 'reference' => 'vn02', 'message' => '', 'channels' => [(string) $telegram->id], 'at' => $future, 'republish' => false])
        ->call('schedule')->assertHasErrors(['postForm.channels']);
    expect(OutboundPost::query()->count())->toBe(1);

    $screen->set('postForm.republish', true)->call('schedule')->assertHasNoErrors();
    expect(OutboundPost::query()->count())->toBe(2)->and(OutboundPost::query()->latest('id')->first()->is_republish)->toBeTrue();
});

it('planifie le calendrier BD en lot après aperçu, puis n’a plus rien à ajouter', function (): void {
    $telegram = connectedChannel(SocialNetwork::Telegram);
    screenComic('vn01', 1);
    screenComic('vn02', 2);
    $screen = gatewayScreen();
    $start = now('Africa/Porto-Novo')->addDays(2)->format('Y-m-d');

    $screen->set('seriesForm', ['channels' => [], 'date' => $start, 'time' => '10:00', 'every' => '3'])->call('previewSeries')->assertHasErrors(['seriesForm.channels']);
    $screen->set('seriesForm.channels', [(string) $telegram->id])->set('seriesForm.every', '0')->call('previewSeries')->assertHasErrors(['seriesForm.every']);
    $screen->set('seriesForm.every', '3')->call('previewSeries')->assertHasNoErrors()->assertSet('seriesPreview', true)->assertSee('2 BD à planifier');
    expect(OutboundPost::query()->count())->toBe(0);

    $screen->call('scheduleSeries')->assertHasNoErrors()->assertSet('seriesPreview', false);
    $posts = OutboundPost::query()->orderBy('scheduled_at')->get();
    expect($posts->pluck('reference')->all())->toBe(['vn01', 'vn02'])
        ->and($posts[0]->scheduled_at->setTimezone('Africa/Porto-Novo')->format('Y-m-d H:i'))->toBe($start.' 10:00')
        ->and($posts[1]->scheduled_at->diffInDays($posts[0]->scheduled_at, true))->toEqual(3.0);

    $screen->set('seriesForm', ['channels' => [(string) $telegram->id], 'date' => $start, 'time' => '10:00', 'every' => '3'])
        ->call('scheduleSeries')->assertHasErrors(['seriesForm.date']);
    expect(OutboundPost::query()->count())->toBe(2);
});

it('enregistre l’identifiant de la page ou du canal et affiche l’erreur lisible d’une remise', function (): void {
    config(['xomiton.gateways.driver' => 'http']);
    $telegram = channel(SocialNetwork::Telegram);
    $screen = gatewayScreen();

    $screen->call('openConnect', $telegram->id)->set('account', 'xomiton_bj')->set('token', '123:abc')->set('externalId', 'pas un id')->call('connect')->assertHasErrors(['externalId']);
    $screen->set('externalId', '-1001234567890')->call('connect')->assertHasNoErrors();
    expect($telegram->fresh()->external_id)->toBe('-1001234567890');

    $post = OutboundPost::factory()->targeting([$telegram->fresh()])->create();
    $post->deliveries()->update(['status' => GatewayDeliveryStatus::Failed->value, 'error' => 'unauthorized (HTTP 401) : Unauthorized', 'attempts' => 1]);

    $screen->call('$refresh')
        ->assertSee('Facebook et Telegram publient automatiquement')
        ->assertSee('reconnectez le compte — HTTP 401 : Unauthorized')
        ->assertDontSee('123:abc');
});

it('revérifie gateways.publish à chaque action du calendrier BD', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('gateways.connect');
    $telegram = connectedChannel(SocialNetwork::Telegram);
    screenComic();
    $this->actingAs($user);
    $screen = Livewire::test(GatewaysScreen::class)->assertOk()->assertDontSee('Calendrier BD');

    $screen->set('seriesForm', ['channels' => [(string) $telegram->id], 'date' => now()->addDays(2)->format('Y-m-d'), 'time' => '10:00', 'every' => '3'])
        ->call('scheduleSeries')->assertForbidden();
    Livewire::test(GatewaysScreen::class)->call('previewSeries')->assertForbidden();
    Livewire::test(GatewaysScreen::class)
        ->set('postForm', ['kind' => 'bd', 'reference' => 'vn02', 'message' => '', 'channels' => [(string) $telegram->id], 'at' => now()->addDay()->format('Y-m-d\TH:i'), 'republish' => true])
        ->call('schedule')->assertForbidden();
    expect(OutboundPost::query()->count())->toBe(0);
});
