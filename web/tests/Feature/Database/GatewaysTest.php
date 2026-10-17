<?php

declare(strict_types=1);

use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use App\Enums\SocialNetwork;
use App\Models\Conversation;
use App\Models\GatewayTemplate;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\PeerRoom;
use App\Models\SocialChannel;
use Database\Seeders\SocialChannelsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/*
 * Lot 9 « passerelles » : une création par factory et une règle métier par table
 * (DATABASE_DICTIONARY.md §8). Tourne avec RefreshDatabase (tests/Pest.php).
 */

it('crée une passerelle par réseau, chiffre ses jetons et refuse un réseau en double', function (): void {
    $channel = SocialChannel::factory()->network(SocialNetwork::Facebook)->connected()->create([
        'access_token' => 'tok_secret',
    ]);

    $raw = DB::table('social_channels')->where('id', $channel->id)->value('access_token');

    expect($raw)->not->toBe('tok_secret')
        ->and($channel->fresh()->access_token)->toBe('tok_secret')
        ->and($channel->toArray())->not->toHaveKey('access_token')
        ->and($channel->fresh()->network)->toBe(SocialNetwork::Facebook)
        ->and($channel->supports_api)->toBeTrue()
        ->and($channel->canPublishViaApi())->toBeTrue()
        ->and($channel->connector)->not->toBeNull()
        ->and(SocialChannel::query()->connected()->count())->toBe(1);

    expect(fn () => SocialChannel::factory()->network(SocialNetwork::Facebook)->create())
        ->toThrow(QueryException::class);
});

it('repère un jeton expiré et les réseaux sans API', function (): void {
    $expired = SocialChannel::factory()->network(SocialNetwork::Telegram)->expired()->create();
    $manual = SocialChannel::factory()->network(SocialNetwork::Romeo)->connected()->create();

    expect($expired->hasExpiredToken())->toBeTrue()
        ->and($expired->canPublishViaApi())->toBeFalse()
        ->and($manual->isManualOnly())->toBeTrue()
        ->and($manual->canPublishViaApi())->toBeFalse()
        ->and(SocialChannel::query()->withApi()->count())->toBe(1)
        ->and(SocialNetwork::fromPrototype('Roméo'))->toBe(SocialNetwork::Romeo)
        ->and(SocialNetwork::fromPrototype('Inconnu'))->toBeNull();
});

it('seed les 6 réseaux déconnectés et les 3 modèles du prototype sans doublon', function (): void {
    $this->seed(SocialChannelsSeeder::class);

    $facebook = SocialChannel::query()->ofNetwork(SocialNetwork::Facebook)->firstOrFail();
    $facebook->update(['is_connected' => true, 'account_handle' => '@xomiton.bj', 'connected_at' => now()]);
    GatewayTemplate::query()->where('title', 'Aide – SOS')->update(['is_active' => false]);

    $this->seed(SocialChannelsSeeder::class);

    expect(SocialChannel::query()->count())->toBe(6)
        ->and(SocialChannel::query()->withApi()->pluck('network')->map->value->sort()->values()->all())
        ->toBe(['facebook', 'telegram', 'tiktok'])
        ->and(SocialChannel::query()->connected()->count())->toBe(1)
        ->and($facebook->fresh()->account_handle)->toBe('@xomiton.bj')
        ->and(SocialChannel::query()->ofNetwork(SocialNetwork::Romeo)->value('label'))->toBe('Roméo')
        ->and(GatewayTemplate::query()->count())->toBe(3)
        ->and(GatewayTemplate::query()->active()->count())->toBe(2)
        ->and(GatewayTemplate::query()->inCategory(GatewayTemplate::CATEGORY_SOS)->value('position'))->toBe(3);
});

it('crée un modèle de message clé actif et ordonné', function (): void {
    $template = GatewayTemplate::factory()->create(['position' => 2]);
    GatewayTemplate::factory()->inactive()->create(['position' => 1]);

    expect($template->fresh()->is_active)->toBeTrue()
        ->and(mb_strlen($template->body))->toBeLessThanOrEqual(GatewayTemplate::BODY_MAX_LENGTH)
        ->and(GatewayTemplate::query()->active()->ordered()->first()?->id)->toBe($template->id)
        ->and($template->reference())->toBe((string) $template->id);
});

it('planifie une publication, la relie à ses réseaux et retrouve celles à publier', function (): void {
    $facebook = SocialChannel::factory()->network(SocialNetwork::Facebook)->connected()->create();
    $telegram = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create();
    $template = GatewayTemplate::factory()->create();

    $due = OutboundPost::factory()->fromTemplate($template)->due()->targeting([$facebook, $telegram])->create();
    OutboundPost::factory()->bd()->create();
    OutboundPost::factory()->published()->create();

    expect($due->fresh()->kind)->toBe(OutboundKind::Template)
        ->and($due->fresh()->status)->toBe(OutboundStatus::Scheduled)
        ->and($due->template()?->id)->toBe($template->id)
        ->and($due->message)->toBe($template->body)
        ->and($due->isDue())->toBeTrue()
        ->and($due->deliveries()->count())->toBe(2)
        ->and($due->channels()->pluck('network')->map->value->sort()->values()->all())->toBe(['facebook', 'telegram'])
        ->and(OutboundPost::query()->due()->count())->toBe(1)
        ->and(OutboundPost::query()->scheduled()->count())->toBe(2)
        ->and(OutboundPost::query()->ofKind(OutboundKind::Bd)->first()?->reference)->toBeIn(['bd1', 'bd2'])
        ->and($facebook->posts()->count())->toBe(1)
        ->and(OutboundKind::fromPrototype('tpl'))->toBe(OutboundKind::Template)
        ->and(OutboundStatus::fromPrototype('planifié'))->toBe(OutboundStatus::Scheduled);
});

it('refuse deux remises sur le même réseau et supprime les remises avec la publication', function (): void {
    $delivery = OutboundDelivery::factory()->create();
    $post = $delivery->post;

    expect(fn () => OutboundDelivery::factory()->forPost($post)->onChannel($delivery->channel)->create())
        ->toThrow(QueryException::class);

    OutboundDelivery::factory()->forPost($post)->published()->create();
    OutboundDelivery::factory()->forPost($post)->manual()->create();
    OutboundDelivery::factory()->forPost($post)->exhausted()->create();

    expect(OutboundDelivery::query()->pending()->count())->toBe(1)
        ->and(OutboundDelivery::query()->delivered()->count())->toBe(2)
        ->and(OutboundDelivery::query()->failed()->count())->toBe(1)
        ->and(OutboundDelivery::query()->retryable()->count())->toBe(1)
        ->and($delivery->fresh()->status)->toBe(GatewayDeliveryStatus::Pending)
        ->and($delivery->canRetry())->toBeTrue()
        ->and(OutboundDelivery::query()->where('status', GatewayDeliveryStatus::Manual->value)->first()?->marker)->not->toBeNull();

    $post->delete();

    expect(OutboundDelivery::query()->count())->toBe(0);
});

it('crée un salon de pair·es avec un slug unique dérivé du nom et rattaché à une conversation de groupe', function (): void {
    $room = PeerRoom::factory()->withConversation()->create(['name' => 'Salon écoute', 'slug' => null]);
    $twin = PeerRoom::factory()->create(['name' => 'Salon écoute', 'slug' => null]);
    PeerRoom::factory()->inactive()->create();

    expect($room->slug)->toBe('salon-ecoute')
        ->and($twin->slug)->toBe('salon-ecoute-2')
        ->and($room->conversation)->toBeInstanceOf(Conversation::class)
        ->and($room->conversation->kind->isGroup())->toBeTrue()
        ->and($room->conversation->created_by)->toBe($room->created_by)
        ->and($room->max_members)->toBe(PeerRoom::DEFAULT_MAX_MEMBERS)
        ->and($room->isFull())->toBeFalse()
        ->and($room->acceptsNewMembers())->toBeTrue()
        ->and($twin->acceptsNewMembers())->toBeFalse()
        ->and(PeerRoom::query()->active()->count())->toBe(2)
        ->and(PeerRoom::query()->whereSlug('salon-ecoute')->first()?->id)->toBe($room->id);

    expect(fn () => PeerRoom::factory()->create(['slug' => 'salon-ecoute']))->toThrow(QueryException::class);
});
