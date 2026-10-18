<?php

declare(strict_types=1);

use App\Application\Offline\OutboxAccount;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Http\Middleware\TagGuestResponse;
use App\Models\Alert;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Support\AlertFixtures;

/*
 * Hors connexion web (P5-10, ADR-26) : seules les pages rendues sans compte portent `X-Xm-Audience: guest` (les seules
 * que le service worker garde) ; la balise xm-offline donne l'empreinte du compte ; un rejeu de la boîte d'envoi qui
 * annonce un autre compte que la session (ou `guest`) part sans compte (BindOutboxAccount).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class]);
    Notification::fake();
});

function outboxSos(?string $account): Illuminate\Testing\TestResponse
{
    $headers = [HandleIdempotencyKey::HEADER => (string) Str::uuid()];

    if ($account !== null) {
        $headers[OutboxAccount::HEADER] = $account;
    }

    return test()->flushHeaders()->withHeaders($headers)->postJson('/api/v1/sos', ['message' => 'Mis en file hors connexion.']);
}

it('marque les pages rendues sans compte ni message flash, jamais une page de session', function (): void {
    $this->get('/urgences')->assertOk()->assertHeader(TagGuestResponse::HEADER, TagGuestResponse::GUEST);
    $this->get('/signaler')->assertOk()->assertHeader(TagGuestResponse::HEADER, TagGuestResponse::GUEST);

    $this->withSession(['_flash.old' => ['status'], 'status' => 'Message'])->get('/urgences')
        ->assertOk()->assertHeaderMissing(TagGuestResponse::HEADER);

    $member = AlertFixtures::member();
    $html = (string) $this->actingAs($member)->get('/urgences')->assertOk()->assertHeaderMissing(TagGuestResponse::HEADER)->getContent();

    expect($html)->toContain('data-account="'.OutboxAccount::fingerprint($member).'"')
        ->and(OutboxAccount::fingerprint($member))->toMatch('/^[0-9a-f]{32}$/')
        ->and(OutboxAccount::fingerprint(null))->toBe('');
});

it('donne une empreinte vide et la durée de vie des envois aux pages sans compte', function (): void {
    config(['xomiton.alerts.draft_days' => 5]);

    expect((string) $this->get('/urgences')->getContent())->toContain('data-account="" data-ttl-days="5"');
});

it('rattache un rejeu au compte seulement quand il annonce ce compte', function (): void {
    $member = AlertFixtures::member();
    Sanctum::actingAs($member);

    outboxSos(OutboxAccount::GUEST)->assertCreated();
    outboxSos(null)->assertCreated();
    outboxSos(str_repeat('0', 32))->assertCreated();
    outboxSos(OutboxAccount::fingerprint($member))->assertCreated();

    expect(Alert::query()->orderBy('id')->pluck('user_id')->all())
        ->toBe([null, $member->id, null, $member->id]);
});

it('ne change rien à un envoi sans compte', function (): void {
    outboxSos(OutboxAccount::GUEST)->assertCreated();
    outboxSos('autre')->assertCreated();

    expect(Alert::query()->whereNotNull('user_id')->count())->toBe(0);
});

it('traite sans compte un signalement mis en file sans compte', function (): void {
    $member = AlertFixtures::member();
    Sanctum::actingAs($member);

    $payload = [
        'type' => 'report',
        'category' => 'physique',
        'description' => AlertFixtures::DESCRIPTION,
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        'anonymous' => false,
    ];
    $post = fn (?string $account): Illuminate\Testing\TestResponse => test()->flushHeaders()
        ->withHeaders(array_filter([HandleIdempotencyKey::HEADER => (string) Str::uuid(), OutboxAccount::HEADER => $account]))
        ->postJson('/api/v1/signalements', $payload)->assertCreated();

    $post(OutboxAccount::GUEST);
    $post(OutboxAccount::fingerprint($member));

    expect(Alert::query()->orderBy('id')->pluck('user_id')->all())->toBe([null, $member->id]);
});
