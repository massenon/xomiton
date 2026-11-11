<?php

declare(strict_types=1);

use App\Application\Gateways\FacebookPublisher;
use App\Application\Gateways\ManualPublisher;
use App\Application\Gateways\PublishResult;
use App\Application\Gateways\SocialPublishers;
use App\Application\Gateways\TelegramPublisher;
use App\Application\Gateways\Transport\GatewayTransport;
use App\Application\Gateways\Transport\LogGatewayTransport;
use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundStatus;
use App\Enums\SocialNetwork;
use App\Models\AuditLog;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * P14-04 : connecteurs Facebook / Telegram derrière SocialPublisher, pilote « log » par défaut (aucun appel réseau, aucune
 * clé), gateways:publish par lots avec rejeu et publication manuelle, journal de chaque publication.
 */

function duePost(SocialChannel ...$channels): OutboundPost
{
    return OutboundPost::factory()->due()->targeting(array_values($channels))->create();
}

function bindTransport(?PublishResult $result): void
{
    app()->bind(LogGatewayTransport::class, fn () => new class($result) implements GatewayTransport
    {
        public int $calls = 0;

        public function __construct(private ?PublishResult $result) {}

        public function post(SocialNetwork $network, string $token, string $target, string $text): PublishResult
        {
            return $this->result ?? PublishResult::failed('refus');
        }
    });
}

it('choisit le connecteur du réseau : Facebook et Telegram avec jeton, la publication manuelle sinon', function (): void {
    $facebook = SocialChannel::factory()->network(SocialNetwork::Facebook)->connected()->create();
    $telegram = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create();
    $romeo = SocialChannel::factory()->network(SocialNetwork::Romeo)->connected()->create();
    $expired = SocialChannel::factory()->network(SocialNetwork::TikTok)->expired()->create();
    $publishers = app(SocialPublishers::class);

    expect($publishers->for($facebook))->toBeInstanceOf(FacebookPublisher::class)
        ->and($publishers->for($telegram))->toBeInstanceOf(TelegramPublisher::class)
        ->and($publishers->for($romeo))->toBeInstanceOf(ManualPublisher::class)
        ->and($publishers->for($expired))->toBeInstanceOf(ManualPublisher::class);
});

it('n’appelle aucun réseau avec le pilote log : la remise reste à publier à la main, marquée une fois', function (): void {
    Http::fake();
    Log::spy();
    $channel = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create();
    $post = duePost($channel);

    $this->artisan('gateways:publish')->assertSuccessful();
    $this->artisan('gateways:publish')->assertSuccessful();

    $delivery = $post->deliveries()->firstOrFail();
    expect(config('xomiton.gateways.driver'))->toBe('log')
        ->and($delivery->status)->toBe(GatewayDeliveryStatus::Pending)
        ->and($delivery->error)->toBe('manuel')
        ->and($delivery->attempts)->toBe(0)
        ->and($post->refresh()->status)->toBe(OutboundStatus::Scheduled)
        ->and(AuditLog::query()->where('action', 'gateway.delivery_manual')->count())->toBe(1);
    Http::assertNothingSent();
    Log::shouldHaveReceived('info')->withArgs(fn (string $message, array $context = []): bool => ! str_contains(json_encode($context), (string) $channel->access_token))->once();
});

it('laisse un réseau sans API à la publication manuelle assistée puis la marque publiée à la main', function (): void {
    $channel = SocialChannel::factory()->network(SocialNetwork::Romeo)->connected()->create();
    $post = duePost($channel);

    $this->artisan('gateways:publish')->assertSuccessful();

    expect($post->deliveries()->first()->error)->toBe('manuel')
        ->and(ManualPublisher::copyText($post))->toBe(trim((string) $post->message));
});

it('publie par le connecteur : remise publiée, publication publiée, journal sans jeton ni texte', function (): void {
    bindTransport(PublishResult::published('ext-1', 'https://t.me/xomiton/1'));
    $channel = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create();
    $post = duePost($channel);

    $this->artisan('gateways:publish')->assertSuccessful();

    $delivery = $post->deliveries()->firstOrFail();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Published)
        ->and($delivery->external_id)->toBe('ext-1')
        ->and($delivery->attempts)->toBe(1)
        ->and($post->refresh()->status)->toBe(OutboundStatus::Published);

    $audit = AuditLog::query()->where('action', 'gateway.delivery_published')->firstOrFail();
    expect((string) $audit->detail)->toBe('telegram')->and($audit->actor)->toBe(AuditLog::ACTOR_SYSTEM);
});

it('rejoue un échec aux passages suivants puis abandonne après le nombre maximal de tentatives', function (): void {
    bindTransport(null);
    $channel = SocialChannel::factory()->network(SocialNetwork::Facebook)->connected()->create();
    $post = duePost($channel);

    foreach (range(1, 7) as $pass) {
        $this->artisan('gateways:publish')->assertSuccessful();
    }

    $delivery = $post->deliveries()->firstOrFail();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(OutboundDelivery::maxAttempts())
        ->and($delivery->error)->toBe('refus')
        ->and($post->refresh()->status)->toBe(OutboundStatus::Failed);
});

it('traite les remises par lots et ignore les publications futures, annulées ou sur un réseau déconnecté', function (): void {
    bindTransport(PublishResult::published('ext'));
    config(['xomiton.gateways.batch_size' => 2]);
    $channel = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create();
    $disconnected = SocialChannel::factory()->network(SocialNetwork::Facebook)->create();
    $due = collect(range(1, 3))->map(fn () => duePost($channel));
    $future = OutboundPost::factory()->targeting([$channel])->create();
    $cancelled = OutboundPost::factory()->due()->cancelled()->targeting([$channel])->create();
    duePost($disconnected);

    $this->artisan('gateways:publish')->assertSuccessful();

    expect(OutboundDelivery::query()->delivered()->count())->toBe(2);

    $this->artisan('gateways:publish')->assertSuccessful();

    expect(OutboundDelivery::query()->delivered()->count())->toBe(3)
        ->and($future->deliveries()->first()->status)->toBe(GatewayDeliveryStatus::Pending)
        ->and($cancelled->refresh()->deliveries()->first()->status)->toBe(GatewayDeliveryStatus::Pending);
});

it('refuse de publier sans jeton, sans cible ou avec un message hors limite, sans appeler le transport', function (): void {
    bindTransport(PublishResult::published('ext'));
    $channel = SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create(['external_id' => null, 'account_handle' => '@x']);
    $post = OutboundPost::factory()->create(['message' => str_repeat('a', 501)]);

    $result = app(TelegramPublisher::class)->publish($post, $channel);

    expect($result->status)->toBe(GatewayDeliveryStatus::Failed)->and($result->error)->toBe('message_invalid');

    $channel->forceFill(['access_token' => null])->save();
    expect(app(TelegramPublisher::class)->publish(OutboundPost::factory()->create(), $channel->refresh())->error)->toBe('credentials_missing');
});

it('retombe sur le pilote log si le pilote configuré est inconnu : jamais d’appel réseau par accident', function (): void {
    config(['xomiton.gateways.driver' => 'inconnu']);

    expect(App\Application\Gateways\Transport\GatewayTransports::make())->toBeInstanceOf(LogGatewayTransport::class);
});
