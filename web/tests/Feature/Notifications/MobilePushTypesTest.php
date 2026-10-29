<?php

declare(strict_types=1);

use App\Application\Alerts\Dispatch\NotificationBuilder;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Auth\Listeners\NotifyValidatorsOfPendingAccount;
use App\Application\Cases\ArchiveCase;
use App\Application\Cases\Data\CaseChanges;
use App\Application\Cases\RestoreCase;
use App\Application\Cases\UpdateCase;
use App\Application\Sync\Admin\ResolveSyncConflict;
use App\Application\Validation\ManageValidationRules;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\PushMessage;
use App\Enums\UserStatus;
use App\Events\Auth\UserRegistered;
use App\Http\Middleware\HandleIdempotencyKey;
use App\Infrastructure\Notifications\FirebaseFcmSender;
use App\Models\Alert;
use App\Models\CaseRecord;
use App\Models\OnCallShift;
use App\Models\PushSubscription;
use App\Models\SyncConflict;
use App\Models\SyncDevice;
use App\Models\User;
use App\Notifications\AlertRouted;
use App\Notifications\ContentReported;
use App\Notifications\PeerPending;
use App\Notifications\RoleRequested;
use App\Notifications\RulesUpdated;
use App\Notifications\SosTriggered;
use App\Notifications\SosUnacknowledged;
use App\Notifications\SyncRequested;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\ValidationRulesSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\AlertFixtures;
use Tests\Support\Notifications\FakePushSender;

/*
 * P15-19 / P15-20 : types de push de l'application mobile unique — `alert_routed` (pair), `duty_sos` / `duty_alert`
 * (admin), `peer_pending`, `content_reported`, `sync` et `rules` (silencieux) ; `id` = ULID de l'alerte ; canal Android
 * « Astreinte » ; texte neutre en mode discret ou aperçus masqués (jamais « SOS », « VBG », « admin »).
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, ValidationRulesSeeder::class]);
    Cache::flush();
});

function fcmFor(User $user): PushSubscription
{
    return PushSubscription::factory()->forUser($user)->fcm()->create();
}

function pushTo(): FakePushSender
{
    $fake = new FakePushSender;
    app()->instance(PushSender::class, $fake);
    app()->instance(FcmSender::class, $fake);

    return $fake;
}

/** @return array<string, array{type: string, id: mixed, title: string, body: string}> messages par abonnement */
function pushedByUser(FakePushSender $fake): array
{
    $out = [];

    foreach ($fake->calls as $call) {
        $owner = PushSubscription::query()->find($call['subscription'])?->user_id;
        $out[(string) $owner] = [
            'type' => $call['message']->data['type'],
            'id' => $call['message']->data['id'],
            'title' => $call['message']->title,
            'body' => $call['message']->body,
            'silent' => $call['message']->silent,
        ];
    }

    return $out;
}

it('envoie un SOS en alert_routed au pair et en duty_sos à l’admin de garde, avec l’ULID de l’alerte', function (): void {
    $fake = pushTo();
    $zone = AlertFixtures::zone('Littoral');
    $pair = AlertFixtures::member('pair');
    $pair->forceFill(['zone_id' => $zone->id])->save();
    $admin = AlertFixtures::admin();
    $offDuty = AlertFixtures::admin();
    OnCallShift::factory()->admin()->forUser($admin)->create();
    OnCallShift::factory()->admin()->forUser(AlertFixtures::admin())->create();
    $sent = [$pair, $admin, $offDuty];
    array_map(fn (User $u) => fcmFor($u), $sent);

    test()->withHeader(HandleIdempotencyKey::HEADER, (string) Str::uuid())->postJson('/api/v1/sos', ['zone_id' => $zone->id])->assertCreated();
    $alert = Alert::query()->sole();
    $pushed = pushedByUser($fake);

    expect($pushed[(string) $pair->id]['type'])->toBe('alert_routed')
        ->and($pushed[(string) $pair->id]['id'])->toBe($alert->ulid)
        ->and($pushed[(string) $admin->id]['type'])->toBe('duty_sos')
        ->and($pushed[(string) $admin->id]['id'])->toBe($alert->ulid)
        ->and($pushed)->not->toHaveKey((string) $offDuty->id);
});

it('choisit l’audience : admin d’abord pour l’équipe, pair d’abord pour une fiche liée à un compte, rien pour l’auteur·e', function (): void {
    $adminAndPair = AlertFixtures::admin();
    $adminAndPair->assignRole('pair');

    expect(NotificationBuilder::audience(RecipientTarget::account($adminAndPair, RecipientKind::Team)))->toBe('admin')
        ->and(NotificationBuilder::audience(RecipientTarget::account($adminAndPair, RecipientKind::Focal)))->toBe('pair')
        ->and(NotificationBuilder::audience(RecipientTarget::account(AlertFixtures::member('pair'), RecipientKind::Team)))->toBe('pair')
        ->and(NotificationBuilder::audience(RecipientTarget::account(AlertFixtures::admin(), RecipientKind::Team)))->toBe('admin')
        ->and(NotificationBuilder::audience(RecipientTarget::account(AlertFixtures::member('membre'), RecipientKind::Team)))->toBeNull()
        ->and(NotificationBuilder::audience(RecipientTarget::account($adminAndPair, RecipientKind::Author)))->toBeNull();

    $retired = AlertFixtures::admin();
    $retired->roles->first(fn ($role) => $role->name === 'admin')?->revokePermissionTo('admin_mobile.access');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    expect(NotificationBuilder::audience(RecipientTarget::account($retired, RecipientKind::Team)))->toBeNull();
});

it('donne les types historiques sans audience et les types d’astreinte avec', function (): void {
    $user = AlertFixtures::admin();
    $make = fn (?string $audience, string $class) => match ($class) {
        'sos' => new SosTriggered(7, 'SIG-1', 'Cotonou', 'Littoral', audience: $audience, alertUlid: '01HZ'),
        'routed' => new AlertRouted(7, 'SIG-1', 'Signalement', 'Littoral', now(), audience: $audience, alertUlid: '01HZ'),
        'unack' => new SosUnacknowledged(7, 'SIG-1', 6, 'Littoral', audience: $audience, alertUlid: '01HZ'),
    };
    $type = fn (string $class, ?string $audience): array => [$make($audience, $class)->toPush($user)->data['type'], $make($audience, $class)->toPush($user)->data['id']];

    expect($type('sos', null))->toBe(['sos', 7])
        ->and($type('sos', 'admin'))->toBe(['duty_sos', '01HZ'])
        ->and($type('sos', 'pair'))->toBe(['alert_routed', '01HZ'])
        ->and($type('routed', null))->toBe(['alert', 7])
        ->and($type('routed', 'admin'))->toBe(['duty_alert', '01HZ'])
        ->and($type('routed', 'pair'))->toBe(['alert_routed', '01HZ'])
        ->and($type('unack', 'admin'))->toBe(['duty_sos', '01HZ'])
        ->and($type('unack', 'pair'))->toBe(['alert_routed', '01HZ']);
});

it('met le texte du push en neutre en mode discret ou aperçus masqués, sans SOS, VBG ni admin', function (): void {
    $pair = AlertFixtures::member('pair');
    $pair->profile->forceFill(['discreet_mode' => true])->save();
    $open = AlertFixtures::member('pair');
    $open->profile->forceFill(['discreet_mode' => false])->save();
    $masking = AlertFixtures::member('pair');
    $masking->settings()->update(['mask_sensitive_notifications' => true]);
    $masking->refresh();

    $notification = new SosTriggered(7, 'SIG-1', 'Cotonou', 'Littoral', audience: 'pair', alertUlid: '01HZ');
    $discreet = $notification->toPush($pair->fresh());
    $visible = $notification->toPush($open->fresh());
    $masked = $notification->toPush($masking->fresh());

    foreach ([$discreet, $masked] as $message) {
        expect($message->title)->toBe('Xomiton')
            ->and($message->title.' '.$message->body)->not->toMatch('/sos|vbg|admin|cotonou/i');
    }

    expect($visible->body)->toContain('Cotonou');

    // Sans audience (contact de l'annuaire sans espace mobile), le mode discret du profil ne change pas le texte historique.
    $legacy = (new SosTriggered(7, 'SIG-1', 'Cotonou', 'Littoral'))->toPush($pair->fresh());
    expect($legacy->body)->toContain('Cotonou');
});

it('envoie le canal Astreinte aux types d’astreinte et sans bloc notification aux types silencieux', function (): void {
    $sender = new FirebaseFcmSender;
    $payload = new ReflectionMethod($sender, 'payload');
    $build = fn (PushMessage $message): array => $payload->invoke($sender, 'token-xyz', $message);
    $message = fn (string $type, string $category, bool $silent = false): PushMessage => new PushMessage('T', 'B', null, ['type' => $type, 'id' => '01HZ', 'url' => null, 'category' => $category], null, $silent);

    $duty = $build($message('duty_sos', 'sos'));
    $routed = $build($message('alert_routed', 'alertes'));
    $legacy = $build($message('sos', 'sos'));
    $community = $build($message('new_message', 'messages'));
    $pending = $build($message('peer_pending', 'systeme'));
    $silent = $build($message('rules', 'systeme', silent: true));

    expect($duty['android']['notification']['channel_id'])->toBe('duty')->and($duty['android']['priority'])->toBe('HIGH')
        ->and($routed['android']['notification']['channel_id'])->toBe('duty')
        ->and($legacy['android']['notification']['channel_id'])->toBe('sos_alerts')
        ->and($community['android']['notification']['channel_id'])->toBe('community')
        ->and($pending['android']['notification']['channel_id'])->toBe('community')->and($pending['android']['priority'])->toBe('NORMAL')
        ->and($duty['data'])->toBe(['type' => 'duty_sos', 'id' => '01HZ', 'category' => 'sos'])
        ->and($silent)->not->toHaveKey('notification')
        ->and($silent['data'])->toBe(['type' => 'rules', 'id' => '01HZ', 'category' => 'systeme'])
        ->and($silent['android']['collapse_key'])->toBe('rules');
});

it('prévient en peer_pending les comptes qui valident un compte resté en attente, jamais pour un compte actif', function (): void {
    Notification::fake();
    $admin = AlertFixtures::admin();
    $moderator = AlertFixtures::member('moderateur');
    $pending = User::factory()->withProfile()->create(['status' => UserStatus::Pending]);
    $active = User::factory()->withProfile()->create(['status' => UserStatus::Active]);

    (new NotifyValidatorsOfPendingAccount)->handle(new UserRegistered($active));
    Notification::assertNothingSent();

    (new NotifyValidatorsOfPendingAccount)->handle(new UserRegistered($pending));

    Notification::assertSentTo($admin, PeerPending::class, fn (PeerPending $n): bool => $n->userId === $pending->id
        && $n->toPush($admin)->data['type'] === 'peer_pending'
        && $n->toPush($admin)->data['id'] === $pending->id);
    Notification::assertNotSentTo($moderator, PeerPending::class);
});

it('rend peer_pending neutre en mode discret et pour une demande du rôle pair', function (): void {
    $admin = AlertFixtures::admin();
    $admin->profile->forceFill(['discreet_mode' => true])->save();

    $pending = (new PeerPending(5, 'Awa'))->toPush($admin->fresh());
    $request = (new RoleRequested(9, 'Awa'))->toPush($admin->fresh());

    expect($pending->data['type'])->toBe('peer_pending')
        ->and($request->data['type'])->toBe('peer_pending')
        ->and($pending->title.$pending->body.$request->title.$request->body)->not->toMatch('/sos|vbg|admin|awa/i');

    $admin->profile->forceFill(['discreet_mode' => false])->save();
    expect((new PeerPending(5, 'Awa'))->toPush($admin->fresh())->body)->toContain('Awa');
});

it('pousse content_reported en FCM aux seuls comptes de l’espace Admin mobile, jamais en Web Push', function (): void {
    $admin = AlertFixtures::admin();
    $moderator = AlertFixtures::member('moderateur');
    foreach ([$admin, $moderator] as $user) {
        fcmFor($user);
        PushSubscription::factory()->forUser($user)->create();
    }

    $notification = new ContentReported(12, 'Publication', 'Harcèlement');

    expect($notification->via($admin))->toContain('fcm')->not->toContain('webpush')
        ->and($notification->via($moderator))->toContain('database')->not->toContain('fcm')->not->toContain('webpush')
        ->and($notification->toPush($admin)->data)->toMatchArray(['type' => 'content_reported', 'id' => 12]);

    $admin->profile->forceFill(['discreet_mode' => true])->save();
    $masked = $notification->toPush($admin->fresh());
    expect($masked->title.$masked->body)->not->toMatch('/harc|publication/i');
});

it('réveille les pair·es en silence (rules) quand une règle change, une fois par rafale', function (): void {
    $fake = pushTo();
    $admin = AlertFixtures::admin();
    $pair = AlertFixtures::member('pair');
    $noToken = AlertFixtures::member('pair');
    $member = AlertFixtures::member('membre');
    $adminOnly = AlertFixtures::admin();
    foreach ([$pair, $member, $adminOnly] as $user) {
        fcmFor($user);
    }
    PushSubscription::factory()->forUser($noToken)->create();

    app(ManageValidationRules::class)->setActivation($admin, ['date_not_future' => false]);

    $pushed = pushedByUser($fake);
    expect($pushed)->toHaveCount(1)
        ->and($pushed[(string) $pair->id])->toMatchArray(['type' => 'rules', 'silent' => true, 'title' => '', 'body' => ''])
        ->and($pair->notifications()->count())->toBe(0);

    // Un second réglage tout de suite après : pas de nouveau réveil (anti-rafale) ; plus tard : oui.
    app(ManageValidationRules::class)->setActivation($admin, ['date_not_future' => true]);
    expect($fake->calls)->toHaveCount(1);

    $this->travel(30)->seconds();
    Cache::flush();
    app(ManageValidationRules::class)->setActivation($admin, ['date_not_future' => false]);
    expect($fake->calls)->toHaveCount(2);
});

it('ne réveille personne quand le réglage ne change rien', function (): void {
    $fake = pushTo();
    $admin = AlertFixtures::admin();
    fcmFor(AlertFixtures::member('pair'));

    app(ManageValidationRules::class)->setActivation($admin, ['required' => true]);

    expect($fake->calls)->toBeEmpty();
});

it('ne sert jamais un push silencieux en Web Push ni dans la cloche, sans tenir compte des préférences', function (): void {
    $pair = AlertFixtures::member('pair');
    $pair->settings()->update(['notification_prefs' => ['systeme' => ['in_app' => false, 'email' => false, 'push' => false]]]);
    PushSubscription::factory()->forUser($pair)->create();

    expect((new RulesUpdated)->via($pair->fresh()))->toBe([])
        ->and((new SyncRequested)->via($pair->fresh()))->toBe([]);

    fcmFor($pair);

    expect((new RulesUpdated)->via($pair->fresh()))->toBe(['fcm'])
        ->and((new SyncRequested)->via($pair->fresh()))->toBe(['fcm']);
});

it('réveille le pair (sync) quand quelqu’un d’autre modifie, archive, rétablit ou tranche pour son dossier, pas quand il le fait lui-même', function (): void {
    $fake = pushTo();
    $admin = AlertFixtures::admin();
    $pair = AlertFixtures::member('pair');
    fcmFor($pair);
    $case = CaseRecord::factory()->collectedBy($pair)->create();

    app(UpdateCase::class)($pair, $case, new CaseChanges(['referral' => 'Juriste']));
    expect($fake->calls)->toBeEmpty();

    app(UpdateCase::class)($admin, $case->fresh(), new CaseChanges(['referral' => 'Psychologue']));
    expect($fake->calls)->toHaveCount(1)
        ->and($fake->calls[0]['message']->data)->toBe(['type' => 'sync', 'id' => null, 'url' => null, 'category' => 'systeme'])
        ->and($fake->calls[0]['message']->silent)->toBeTrue();

    // Dédoublonné (anti-rafale), puis archivage et rétablissement une fois le délai passé.
    app(ArchiveCase::class)($admin, $case->fresh());
    expect($fake->calls)->toHaveCount(1);
    Cache::flush();
    app(RestoreCase::class)($admin, $case->fresh());
    expect($fake->calls)->toHaveCount(2);

    // Conflit tranché par l'administration pour l'appareil du pair.
    Cache::flush();
    $device = SyncDevice::factory()->create(['user_id' => $pair->id, 'conflicts_count' => 1]);
    $conflict = SyncConflict::factory()->create(['device_id' => $device->id, 'subject_type' => $case->getMorphClass(), 'subject_id' => $case->id, 'field' => 'notes']);
    app(ResolveSyncConflict::class)($admin, $conflict, 'serveur');
    expect($fake->calls)->toHaveCount(3);

    // Tranché par le pair lui-même : aucun réveil.
    Cache::flush();
    $own = SyncConflict::factory()->create(['device_id' => $device->id, 'subject_type' => $case->getMorphClass(), 'subject_id' => $case->id, 'field' => 'notes']);
    app(ResolveSyncConflict::class)($pair, $own, 'serveur');
    expect($fake->calls)->toHaveCount(3);
});
