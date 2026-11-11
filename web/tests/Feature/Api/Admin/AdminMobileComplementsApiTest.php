<?php

declare(strict_types=1);

use App\Enums\AlertType;
use App\Enums\DeliveryStatus;
use App\Enums\MemberType;
use App\Enums\ModerationKind;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Enums\PostStatus;
use App\Enums\UserStatus;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\AuditLog;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\Profile;
use App\Models\RoleInvitation;
use App\Models\User;
use App\Models\UserWarning;
use Database\Seeders\EmailTemplatesSeeder;
use Database\Seeders\ReferenceDataSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\Support\FeedFixtures as F;

/*
 * Compléments de l'API admin mobile (P15-17, P15-18, P15-24) : personnes prévenues dans le détail d'une alerte, filtre des
 * inscriptions par nature (`kind`), détail / avertissement / décision unique de la modération, messages privés jamais
 * proposés à la modération mobile.
 */

beforeEach(function (): void {
    $this->seed([ReferenceDataSeeder::class, RolesAndPermissionsSeeder::class, EmailTemplatesSeeder::class, SettingsSeeder::class]);
    Notification::fake();
    $this->admin = F::member('admin');
    $this->admin->syncRoles(['membre', 'admin']);
});

function gapCall(User $user, string $method, string $uri, array $body = []): Illuminate\Testing\TestResponse
{
    $headers = in_array($method, ['POST', 'PUT', 'DELETE'], true) ? ['Idempotency-Key' => (string) Str::uuid()] : [];

    return F::as($user)->withHeaders($headers)->json($method, '/api/v1/admin/'.$uri, $body);
}

it('montre les personnes prévenues dans le détail d’une alerte, sans nom d’admin ni numéro du contact de confiance', function (): void {
    $alert = Alert::factory()->ofType(AlertType::Sos)->create();
    $teamMember = F::member('pair');
    AlertNotification::factory()->forAlert($alert)->toUser($teamMember)->purpose(NotificationPurpose::SosTriggered)->create(['status' => DeliveryStatus::Sent, 'sent_at' => now()]);
    AlertNotification::factory()->forAlert($alert)->channel(NotificationChannel::Sms)->purpose(NotificationPurpose::SosTriggered)->create(['status' => DeliveryStatus::Sent, 'sent_at' => now()]);

    $response = gapCall($this->admin, 'GET', 'alerts/'.$alert->ulid)->assertOk();
    $notified = $response->json('data.notified');

    expect($notified)->not->toBeEmpty()
        ->and(array_keys($notified[0]))->toContain('key', 'kind', 'label', 'role', 'state', 'state_label', 'seen_at')
        ->and($response->getContent())->not->toContain($teamMember->email)->not->toContain('user:');

    // La liste n'expose pas les personnes prévenues (résumés légers).
    expect(gapCall($this->admin, 'GET', 'alerts')->json('data.0'))->not->toHaveKey('notified');
});

it('filtre les inscriptions en attente par nature : demande de pair, inscription, pair·e invité·e', function (): void {
    $request = User::factory()->create(['status' => UserStatus::Pending]);
    $request->syncRoles(['membre']);
    Profile::factory()->create(['user_id' => $request->id, 'member_type' => MemberType::Peer]);

    $invited = User::factory()->create(['status' => UserStatus::Pending]);
    $invited->syncRoles(['membre']);
    Profile::factory()->create(['user_id' => $invited->id, 'member_type' => MemberType::Member]);
    RoleInvitation::query()->create([
        'role' => 'pair', 'code_hash' => RoleInvitation::hash('ABCD2345EFGH'), 'code_hint' => 'EFGH', 'expires_at' => now()->addDay(),
        'invited_by' => $this->admin->id, 'status' => RoleInvitation::STATUS_REDEEMED, 'user_id' => $invited->id,
    ]);

    $plain = User::factory()->create(['status' => UserStatus::Pending, 'registration_flags' => 'disposable_email']);
    $plain->syncRoles(['membre']);
    Profile::factory()->create(['user_id' => $plain->id, 'member_type' => MemberType::Member]);

    $ids = fn (string $kind): array => collect(gapCall($this->admin, 'GET', 'registrations?kind='.$kind)->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

    expect($ids('registration'))->toBe([$plain->id])
        ->and($ids('peer_request'))->toBe(collect([$request->id, $invited->id])->sort()->values()->all())
        ->and($ids('invited'))->toBe([$invited->id]);

    $all = gapCall($this->admin, 'GET', 'registrations')->assertOk()->assertJsonPath('meta.pending', 3);
    $rows = collect($all->json('data'))->keyBy('id');
    expect($rows[$invited->id]['invited'])->toBeTrue()->and($rows[$plain->id]['invited'])->toBeFalse()
        ->and($rows[$plain->id]['flags'])->toBe(['disposable_email']);

    gapCall($this->admin, 'GET', 'registrations?kind=bidule')->assertStatus(422);
});

it('sert le détail d’un signalement et avertit l’auteur·e avec un motif obligatoire (journal source=mobile)', function (): void {
    $author = F::member('membre');
    $post = Post::factory()->create(['user_id' => $author->id]);
    $report = ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $author->id, 'reason' => 'Insultes', 'excerpt' => 'Un propos']);
    ModerationReport::factory()->forSubject($post)->create();

    gapCall($this->admin, 'GET', 'moderation/reports/'.$report->id)->assertOk()
        ->assertJsonPath('data.id', $report->id)->assertJsonPath('data.reports', 2)->assertJsonPath('data.can.warn', true)
        ->assertJsonMissingPath('data.reporter');

    gapCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/warn', [])->assertStatus(422);
    gapCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/warn', ['reason' => 'ab'])->assertStatus(422);
    gapCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/warn', ['reason' => 'Merci de rester respectueux.'])
        ->assertOk()->assertJsonPath('meta.closed', 2)->assertJsonPath('data.action', ModerationReport::ACTION_WARN);

    expect(UserWarning::query()->where('user_id', $author->id)->count())->toBe(1)
        ->and($post->fresh()->status)->toBe(PostStatus::Published)
        ->and(AuditLog::query()->where('action', 'moderation.warned')->latest('id')->first()->detail)->toContain('source=mobile');

    gapCall($this->admin, 'POST', 'moderation/reports/'.$report->id.'/warn', ['reason' => 'Encore une fois.'])->assertStatus(422);
});

it('réunit masquer, conserver et avertir dans une décision unique', function (): void {
    $author = F::member('membre');
    $hidden = Post::factory()->create(['user_id' => $author->id]);
    $kept = Post::factory()->create(['user_id' => $author->id]);
    $warned = Post::factory()->create(['user_id' => $author->id]);
    $reports = collect([$hidden, $kept, $warned])->map(fn (Post $post) => ModerationReport::factory()->forSubject($post)->create(['reported_user_id' => $author->id]));

    gapCall($this->admin, 'POST', 'moderation/reports/'.$reports[0]->id.'/decision', ['action' => 'hide'])->assertOk()->assertJsonPath('data.hidden', true);
    gapCall($this->admin, 'POST', 'moderation/reports/'.$reports[1]->id.'/decision', ['action' => 'keep'])->assertOk()->assertJsonPath('data.status', 'rejete');
    gapCall($this->admin, 'POST', 'moderation/reports/'.$reports[2]->id.'/decision', ['action' => 'warn'])->assertStatus(422);
    gapCall($this->admin, 'POST', 'moderation/reports/'.$reports[2]->id.'/decision', ['action' => 'warn', 'reason' => 'Rappel des règles.'])->assertOk()->assertJsonPath('data.action', ModerationReport::ACTION_WARN);
    gapCall($this->admin, 'POST', 'moderation/reports/'.$reports[2]->id.'/decision', ['action' => 'suspend', 'reason' => 'Non.'])->assertStatus(422);

    expect($hidden->fresh()->status)->toBe(PostStatus::Hidden)->and($kept->fresh()->status)->toBe(PostStatus::Published);
});

it('ne propose jamais les messages privés à la modération mobile : ni liste, ni détail, ni décision, ni compteur', function (): void {
    $message = Message::factory()->create();
    $private = ModerationReport::factory()->forSubject($message, ModerationKind::Message)->create(['reason' => 'Harcèlement', 'excerpt' => 'Contenu privé']);
    $post = Post::factory()->create();
    $public = ModerationReport::factory()->forSubject($post)->create();

    $list = gapCall($this->admin, 'GET', 'moderation/reports?state=all')->assertOk();
    expect(collect($list->json('data'))->pluck('id')->all())->toBe([$public->id])
        ->and($list->getContent())->not->toContain('Contenu privé');
    gapCall($this->admin, 'GET', 'moderation/reports')->assertJsonPath('meta.pending', 1);
    gapCall($this->admin, 'GET', 'dashboard')->assertJsonPath('data.pending_moderation', 1);

    foreach ([['GET', ''], ['POST', '/hide'], ['POST', '/dismiss'], ['POST', '/restore'], ['POST', '/warn'], ['POST', '/decision']] as [$method, $suffix]) {
        gapCall($this->admin, $method, 'moderation/reports/'.$private->id.$suffix, ['reason' => 'Rappel des règles.', 'action' => 'keep'])->assertNotFound();
    }

    expect($private->fresh()->isPending())->toBeTrue();
});
