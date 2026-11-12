<?php

declare(strict_types=1);

use App\Application\Admin\Gateways\ManageGateways;
use App\Application\Gateways\OutboundContent;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use App\Enums\SocialNetwork;
use App\Models\AuditLog;
use App\Models\CccTool;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
 * P19-11 : diffusion des BD publiques par les passerelles — Telegram (`sendMediaGroup`), Facebook (photos non publiées
 * puis `attached_media`, repli une photo), lien `/bd/{key}` avec UTM, idempotence par canal, calendrier en lot, erreurs
 * lisibles (401, 400, 429) sans jeton. Aucun appel réseau réel : Http::fake() partout, Http::preventStrayRequests().
 */

const TELEGRAM_TOKEN = '123456:SECRET-bot-token-telegram';

const FACEBOOK_TOKEN = 'EAAG-secret-page-token-facebook';

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['xomiton.gateways.driver' => 'http']);
});

/** BD publique `vn02` (sextorsion) avec les planches réelles du pilote (fichiers WebP de public/assets). */
function publicComic(string $key = 'vn02', int $position = 1): CccTool
{
    $data = json_decode((string) file_get_contents(database_path('seeders/data/content/violences_numeriques/sextorsion.json')), true);

    return CccTool::factory()->create([
        'kind' => CccKind::Comic,
        'key' => $key,
        'title' => $data['bd']['title'],
        'description' => $data['summary'],
        'pages' => $data['bd']['pages'],
        'chapters' => null,
        'duration_minutes' => null,
        'position' => $position,
        'is_published' => true,
        'is_public' => true,
    ]);
}

function telegramChannel(): SocialChannel
{
    return SocialChannel::factory()->network(SocialNetwork::Telegram)->connected()->create(['access_token' => TELEGRAM_TOKEN, 'external_id' => '@xomiton_test']);
}

function facebookChannel(): SocialChannel
{
    return SocialChannel::factory()->network(SocialNetwork::Facebook)->connected()->create(['access_token' => FACEBOOK_TOKEN, 'external_id' => '1029384756']);
}

function dueComicPost(CccTool $tool, array $channels, array $attributes = []): OutboundPost
{
    return OutboundPost::factory()->due()->targeting($channels)->create($attributes + [
        'kind' => OutboundKind::Bd,
        'reference' => $tool->key,
        'message' => ManageGateways::comicMessage($tool),
    ]);
}

/** @return array<string, mixed> parties multipart par nom */
function multipartParts(Request $request): array
{
    $parts = [];

    foreach ($request->data() as $part) {
        $parts[$part['name']] = $part;
    }

    return $parts;
}

function telegramOk(int $count = 5): array
{
    return ['ok' => true, 'result' => array_map(fn (int $i): array => ['message_id' => 42 + $i], range(0, $count - 1))];
}

it('prépare le message d’une BD : texte de partage, lien /bd/{key} avec UTM, visuel puis 4 planches avec leur alt', function (): void {
    $tool = publicComic();
    $post = new OutboundPost(['kind' => OutboundKind::Bd, 'reference' => 'vn02', 'message' => ManageGateways::comicMessage($tool)]);

    $message = app(OutboundContent::class)->for($post, SocialNetwork::Telegram);

    expect($message->text)->toStartWith('Quelqu’un te menace de diffuser des photos intimes')
        ->and($message->link)->toBe(route('bd.show', ['key' => 'vn02']).'?utm_source=telegram&utm_medium=social&utm_campaign=bd')
        ->and(array_column($message->media, 'path'))->toBe([
            'assets/images/violences-numeriques/sextorsion/partage.webp',
            'assets/images/violences-numeriques/sextorsion/planche-1.webp',
            'assets/images/violences-numeriques/sextorsion/planche-2.webp',
            'assets/images/violences-numeriques/sextorsion/planche-3.webp',
            'assets/images/violences-numeriques/sextorsion/planche-4.webp',
        ])
        ->and($message->media[1]['alt'])->toStartWith('Sena, assis·e dans une cour')
        ->and(app(OutboundContent::class)->for($post, SocialNetwork::Facebook)->link)->toContain('utm_source=facebook');

    // BD réservée aux membres : ni lien public ni image.
    $tool->forceFill(['is_public' => false])->save();
    $private = app(OutboundContent::class)->for($post, SocialNetwork::Telegram);
    expect($private->link)->toBeNull()->and($private->media)->toBe([]);
});

it('publie sur Telegram un album sendMediaGroup : 5 photos JPEG converties, légende sur la première seulement, sans alt', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(telegramOk())]);
    Log::spy();
    $channel = telegramChannel();
    $post = dueComicPost(publicComic(), [$channel]);

    $this->artisan('gateways:publish')->assertSuccessful();

    Http::assertSentCount(1);
    Http::assertSent(function (Request $request): bool {
        $parts = multipartParts($request);
        $media = json_decode((string) $parts['media']['contents'], true);

        expect($request->url())->toBe('https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMediaGroup')
            ->and($request->isMultipart())->toBeTrue()
            ->and($parts['chat_id']['contents'])->toBe('@xomiton_test')
            ->and($media)->toHaveCount(5)
            ->and(array_column($media, 'media'))->toBe(['attach://photo0', 'attach://photo1', 'attach://photo2', 'attach://photo3', 'attach://photo4'])
            ->and($media[0]['type'])->toBe('photo')
            ->and($media[0]['caption'])->toStartWith('Quelqu’un te menace')
            ->and($media[0]['caption'])->toEndWith('/bd/vn02?utm_source=telegram&utm_medium=social&utm_campaign=bd')
            ->and(mb_strlen($media[0]['caption']))->toBeLessThanOrEqual(1024)
            ->and(array_key_exists('caption', $media[1]))->toBeFalse()
            ->and(json_encode($media))->not->toContain('alt');

        foreach (range(0, 4) as $i) {
            expect($parts['photo'.$i]['filename'])->toEndWith('.jpg')
                ->and(substr((string) $parts['photo'.$i]['contents'], 0, 3))->toBe("\xFF\xD8\xFF"); // JPEG, pas WebP
        }

        expect($parts['photo0']['filename'])->toBe('partage.jpg')->and($parts['photo1']['filename'])->toBe('planche-1.jpg');

        return true;
    });

    $delivery = $post->deliveries()->firstOrFail();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Published)
        ->and($delivery->external_id)->toBe('42')
        ->and($delivery->external_url)->toBe('https://t.me/xomiton_test/42')
        ->and($post->refresh()->status)->toBe(OutboundStatus::Published);
    Log::shouldNotHaveReceived('info');
    Log::shouldNotHaveReceived('error');
});

it('raccourcit la légende Telegram à 1 024 caractères sans couper le lien', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(telegramOk())]);
    $channel = telegramChannel();
    dueComicPost(publicComic(), [$channel], ['message' => str_repeat('Texte long. ', 41)]);

    $message = new App\Application\Gateways\OutboundMessage(str_repeat('a', 1200), 'https://xomiton.app/bd/vn02?utm_source=telegram');
    expect(mb_strlen($message->fitted(1024)))->toBe(1024)->and($message->fitted(1024))->toEndWith("…\n\nhttps://xomiton.app/bd/vn02?utm_source=telegram");

    $this->artisan('gateways:publish')->assertSuccessful();
    Http::assertSent(fn (Request $request): bool => mb_strlen((string) json_decode((string) multipartParts($request)['media']['contents'], true)[0]['caption']) <= 1024);
});

it('publie sur Facebook : photos non publiées avec alt, puis une publication attached_media, jeton en en-tête seulement', function (): void {
    $uploads = 0;
    Http::fake(function (Request $request) use (&$uploads) {
        if (str_ends_with($request->url(), '/1029384756/photos')) {
            $uploads++;

            return Http::response(['id' => 'ph'.$uploads]);
        }

        return Http::response(['id' => '1029384756_777']);
    });
    $channel = facebookChannel();
    $post = dueComicPost(publicComic(), [$channel]);

    $this->artisan('gateways:publish')->assertSuccessful();

    Http::assertSentCount(6);
    $recorded = Http::recorded()->map(fn (array $pair) => $pair[0]);

    foreach ($recorded as $request) {
        expect($request->url())->not->toContain(FACEBOOK_TOKEN)
            ->and($request->header('Authorization')[0] ?? '')->toBe('Bearer '.FACEBOOK_TOKEN)
            ->and($request->url())->toStartWith('https://graph.facebook.com/v21.0/1029384756/');
    }

    $photos = $recorded->filter(fn (Request $request): bool => str_ends_with($request->url(), '/photos'))->values();
    expect($photos)->toHaveCount(5);
    $first = multipartParts($photos[0]);
    expect($first['published']['contents'])->toBe('false')
        ->and($first['alt_text_custom']['contents'])->toStartWith('Sena et Nadia, souriantes')
        ->and(substr((string) $first['source']['contents'], 0, 3))->toBe("\xFF\xD8\xFF");

    $feed = $recorded->last();
    $data = $feed->data();
    expect($feed->url())->toEndWith('/1029384756/feed')
        ->and($data['message'])->toStartWith('Quelqu’un te menace')
        ->and($data['message'])->toContain('/bd/vn02?utm_source=facebook&utm_medium=social&utm_campaign=bd')
        ->and(array_values(array_filter(array_keys($data), fn (string $key): bool => str_starts_with($key, 'attached_media'))))
        ->toBe(['attached_media[0]', 'attached_media[1]', 'attached_media[2]', 'attached_media[3]', 'attached_media[4]'])
        ->and(json_decode($data['attached_media[0]'], true))->toBe(['media_fbid' => 'ph1'])
        ->and(json_decode($data['attached_media[4]'], true))->toBe(['media_fbid' => 'ph5']);

    $delivery = $post->deliveries()->firstOrFail();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Published)
        ->and($delivery->external_id)->toBe('1029384756_777')
        ->and($delivery->external_url)->toBe('https://www.facebook.com/1029384756_777');
});

it('replie Facebook sur une photo + lien quand la publication multi-photos est refusée', function (): void {
    Http::fake(function (Request $request) {
        if (str_ends_with($request->url(), '/feed')) {
            return Http::response(['error' => ['message' => 'Invalid parameter', 'code' => 100]], 400);
        }

        $parts = multipartParts($request);

        return ($parts['published']['contents'] ?? '') === 'true'
            ? Http::response(['id' => 'ph-final', 'post_id' => '1029384756_888'])
            : Http::response(['id' => 'ph-hidden']);
    });
    $post = dueComicPost(publicComic(), [facebookChannel()]);

    $this->artisan('gateways:publish')->assertSuccessful();

    $last = Http::recorded()->last()[0];
    $parts = multipartParts($last);
    expect($last->url())->toEndWith('/photos')
        ->and($parts['published']['contents'])->toBe('true')
        ->and($parts['caption']['contents'])->toContain('utm_source=facebook')
        ->and($parts['source']['filename'])->toBe('partage.jpg')
        ->and($post->deliveries()->firstOrFail()->external_id)->toBe('1029384756_888');
});

it('publie une photo + lien d’emblée quand la publication multi-photos est désactivée', function (): void {
    config(['xomiton.gateways.facebook.multi_photo' => false]);
    Http::fake(['graph.facebook.com/*' => Http::response(['id' => 'ph', 'post_id' => '1029384756_9'])]);
    dueComicPost(publicComic(), [facebookChannel()]);

    $this->artisan('gateways:publish')->assertSuccessful();

    Http::assertSentCount(1);
});

it('traduit les refus du réseau en statut et message lisibles, sans jeton : 401, 400, 429', function (): void {
    Http::fake(['api.telegram.org/*' => Http::sequence()
        ->push(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)
        ->push(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: chat not found for bot'.TELEGRAM_TOKEN], 400)
        ->push(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests: retry after 35', 'parameters' => ['retry_after' => 35]], 429),
    ]);
    $channel = telegramChannel();
    $post = dueComicPost(publicComic(), [$channel]);
    $delivery = $post->deliveries()->firstOrFail();

    $this->artisan('gateways:publish')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Failed)
        ->and($delivery->error)->toBe('unauthorized (HTTP 401) : Unauthorized')
        ->and($delivery->attempts)->toBe(1)
        ->and($channel->refresh()->last_error)->toBe('unauthorized (HTTP 401) : Unauthorized')
        ->and(ManageGateways::deliveryError($delivery))->toBe(__('admin_gateways.delivery_errors.unauthorized').' — HTTP 401 : Unauthorized');

    $this->artisan('gateways:publish')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->error)->toStartWith('rejected (HTTP 400) : Bad Request: chat not found')
        ->and($delivery->error)->not->toContain(TELEGRAM_TOKEN)
        ->and($delivery->attempts)->toBe(2);

    $this->artisan('gateways:publish')->assertSuccessful();
    $delivery->refresh();
    expect($delivery->error)->toStartWith('rate_limited (HTTP 429)')
        ->and($delivery->error)->toContain('35 s')
        ->and($delivery->attempts)->toBe(2); // limite de débit : aucune tentative consommée

    $audits = AuditLog::query()->where('action', 'gateway.delivery_failed')->get();
    expect($audits)->toHaveCount(3)->and(json_encode($audits->toArray()))->not->toContain(TELEGRAM_TOKEN);
});

it('arrête un canal limité en débit pour le passage et plafonne les publications par canal et par passage', function (): void {
    config(['xomiton.gateways.per_channel_per_run' => 2]);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 1]])]);
    $channel = telegramChannel();
    $posts = collect(range(1, 3))->map(fn (int $i) => OutboundPost::factory()->due()->targeting([$channel])->create());

    $this->artisan('gateways:publish')->assertSuccessful();
    Http::assertSentCount(2);
    expect(OutboundDelivery::query()->delivered()->count())->toBe(2);

    $this->artisan('gateways:publish')->assertSuccessful();
    expect(OutboundDelivery::query()->delivered()->count())->toBe(3)
        ->and($posts->every(fn (OutboundPost $post): bool => $post->refresh()->status === OutboundStatus::Published))->toBeTrue();
});

it('ne publie pas le reste d’un canal après une limite de débit dans le même passage', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429, 'description' => 'Too Many Requests', 'parameters' => ['retry_after' => 5]], 429)]);
    $channel = telegramChannel();
    OutboundPost::factory()->due()->targeting([$channel])->count(2)->create();

    $this->artisan('gateways:publish')->assertSuccessful();

    Http::assertSentCount(1);
});

it('réduit une panne de connexion à transport_error, sans l’adresse qui contient le jeton', function (): void {
    Http::fake(fn () => throw new ConnectionException('cURL error 28 for https://api.telegram.org/bot'.TELEGRAM_TOKEN.'/sendMediaGroup'));
    Log::spy();
    $post = dueComicPost(publicComic(), [telegramChannel()]);

    $this->artisan('gateways:publish')->assertSuccessful();

    $delivery = $post->deliveries()->firstOrFail();
    expect($delivery->error)->toBe('transport_error')->and($delivery->status)->toBe(GatewayDeliveryStatus::Failed);
    Log::shouldNotHaveReceived('error');
    expect(json_encode(AuditLog::query()->get()->toArray()))->not->toContain(TELEGRAM_TOKEN);
});

it('ne publie jamais deux fois la même BD sur le même canal, sauf republication explicite', function (): void {
    Http::fake(['api.telegram.org/*' => Http::response(telegramOk())]);
    $channel = telegramChannel();
    $tool = publicComic();
    $first = dueComicPost($tool, [$channel]);
    $this->artisan('gateways:publish')->assertSuccessful();
    expect($first->deliveries()->firstOrFail()->status)->toBe(GatewayDeliveryStatus::Published);

    // Seconde publication de la même BD (créée sans passer par l'écran) : close sans appel.
    $again = dueComicPost($tool, [$channel]);
    $this->artisan('gateways:publish')->assertSuccessful();
    $delivery = $again->deliveries()->firstOrFail();
    expect($delivery->status)->toBe(GatewayDeliveryStatus::Failed)
        ->and($delivery->error)->toBe('already_published')
        ->and($delivery->attempts)->toBe(OutboundDelivery::maxAttempts())
        ->and($again->refresh()->status)->toBe(OutboundStatus::Failed);
    Http::assertSentCount(1);

    // La planification la refuse aussi ; « Republier » l'autorise et part réellement.
    $admin = User::factory()->create();
    $manage = app(ManageGateways::class);
    $data = ['kind' => 'bd', 'reference' => 'vn02', 'message' => '', 'channel_ids' => [$channel->id], 'scheduled_at' => now()->addHour()];
    expect(fn () => $manage->schedule($data, $admin))->toThrow(ContentRefused::class);

    $republish = $manage->schedule($data + ['republish' => true], $admin);
    expect($republish->is_republish)->toBeTrue();
    $this->travel(2)->hours();
    $this->artisan('gateways:publish')->assertSuccessful();
    Http::assertSentCount(2);
    expect($republish->deliveries()->firstOrFail()->status)->toBe(GatewayDeliveryStatus::Published);
});

it('n’envoie rien pour une publication annulée avant son départ', function (): void {
    Http::fake();
    $post = dueComicPost(publicComic(), [telegramChannel()]);

    app(ManageGateways::class)->cancel($post, User::factory()->create());
    $this->artisan('gateways:publish')->assertSuccessful();

    Http::assertNothingSent();
    expect($post->refresh()->status)->toBe(OutboundStatus::Cancelled);
});

it('planifie en lot les BD publiques pas encore diffusées : une tous les N jours à la même heure du Bénin', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-11-12 08:00', 'Africa/Porto-Novo'));
    $telegram = telegramChannel();
    $facebook = facebookChannel();
    $a = publicComic('vn01', 1);
    $b = publicComic('vn02', 2);
    $c = publicComic('vn03', 3);
    CccTool::factory()->comic()->create(['key' => 'ssr9', 'is_public' => false]); // réservée aux membres : jamais
    OutboundPost::factory()->published()->create(['kind' => OutboundKind::Bd, 'reference' => 'vn03'])
        ->deliveries()->create(['social_channel_id' => $telegram->id, 'status' => GatewayDeliveryStatus::Published]);
    $manage = app(ManageGateways::class);
    $admin = User::factory()->create();
    $data = ['channel_ids' => [$telegram->id, $facebook->id], 'starts_at' => CarbonImmutable::parse('2026-11-14 10:00', 'Africa/Porto-Novo')->utc(), 'every_days' => 3];

    $plan = $manage->planComicSeries($data);
    expect(OutboundPost::query()->scheduled()->count())->toBe(0)->and($plan)->toHaveCount(3);

    $posts = $manage->scheduleComicSeries($data, $admin);

    expect(array_map(fn (OutboundPost $post): string => (string) $post->reference, $posts))->toBe(['vn01', 'vn02', 'vn03'])
        ->and(array_map(fn (OutboundPost $post): string => $post->scheduled_at->setTimezone('Africa/Porto-Novo')->format('Y-m-d H:i'), $posts))
        ->toBe(['2026-11-14 10:00', '2026-11-17 10:00', '2026-11-20 10:00'])
        ->and($posts[0]->deliveries()->count())->toBe(2)
        ->and($posts[2]->deliveries()->pluck('social_channel_id')->all())->toBe([$facebook->id]) // déjà sur Telegram
        ->and($posts[1]->message)->toStartWith('Quelqu’un te menace') // share.text de vn02
        ->and(AuditLog::query()->where('action', 'gateway.series_scheduled')->count())->toBe(1);

    // Tout est planifié : un second lot n'ajoute rien.
    expect(fn () => $manage->scheduleComicSeries($data, $admin))->toThrow(ContentRefused::class);
    expect(OutboundPost::query()->scheduled()->count())->toBe(3);

    // Une publication annulée libère la BD pour un nouveau lot.
    $manage->cancel($posts[1], $admin);
    expect(array_map(fn (array $slot): string => (string) $slot['tool']->key, $manage->planComicSeries($data)))->toBe(['vn02']);

    // Écart hors bornes ou date passée : refusé.
    expect(fn () => $manage->planComicSeries(['every_days' => 0] + $data))->toThrow(ContentRefused::class)
        ->and(fn () => $manage->planComicSeries(['starts_at' => now()->subDay()] + $data))->toThrow(ContentRefused::class);
    unset($a, $b, $c);
});

it('laisse les réseaux sans API à la publication manuelle avec le texte, le lien UTM du réseau et sans appel', function (): void {
    Http::fake();
    $romeo = SocialChannel::factory()->network(SocialNetwork::Romeo)->connected()->create();
    $post = dueComicPost(publicComic(), [$romeo]);

    $this->artisan('gateways:publish')->assertSuccessful();

    $delivery = $post->deliveries()->with(['post', 'channel'])->firstOrFail();
    expect($delivery->error)->toBe('manuel')
        ->and(app(ManageGateways::class)->copyText($delivery))->toContain('/bd/vn02?utm_source=romeo&utm_medium=social&utm_campaign=bd');
    Http::assertNothingSent();
});
