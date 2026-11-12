<?php

declare(strict_types=1);

use App\Application\Community\Events\EventApproval;
use App\Enums\ContentStatus;
use App\Livewire\Admin\Events\Index as EventsScreen;
use App\Livewire\Admin\Suggestions\Index as SuggestionsScreen;
use App\Models\AuditLog;
use App\Models\Event;
use App\Models\Suggestion;
use App\Models\User;
use App\Notifications\TeamNotice;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Support\FeedFixtures as F;

/*
 * P18-14 : événements — type (groupe de parole, atelier, rencontre, autre ; « Autre » par défaut) côté API et membre,
 * calendrier admin du mois par semaine, file d'approbation **désactivée par défaut** (activée : événement d'un·e membre
 * « en attente », invisible jusqu'à la décision, approuver / refuser avec motif et notification). Boîte à suggestions :
 * formulaire (API, page Vue), anonyme possible, limiteur, inbox admin (statuts, réponse en notification), journal.
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config(['xomiton.display_timezone' => 'Africa/Porto-Novo']);
    $this->travelTo(now()->setTimezone('UTC')->setDate(2026, 11, 12)->setTime(12, 0));
    $this->admin = User::factory()->create();
    $this->admin->syncRoles(['admin']);
    $this->moderator = User::factory()->create();
    $this->moderator->syncRoles(['moderateur']);
    $this->member = F::member();
});

function p18Event(array $overrides = []): array
{
    return $overrides + ['title' => 'Cercle de parole', 'starts_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'place' => 'Cotonou'];
}

it('enregistre le type d’un événement (Autre par défaut) et le sert dans l’API et les options', function (): void {
    F::as($this->member)->postJson('/api/v1/events', p18Event(['type' => 'groupe_parole']))->assertCreated()
        ->assertJsonPath('data.type', 'groupe_parole')
        ->assertJsonPath('data.type_label', 'Groupe de parole')
        ->assertJsonPath('data.is_pending', false)
        ->assertJsonPath('data.status', 'publie');
    F::as($this->member)->postJson('/api/v1/events', p18Event(['title' => 'Sans type']))->assertCreated()->assertJsonPath('data.type', 'autre');
    F::as($this->member)->postJson('/api/v1/events', p18Event(['type' => 'fete']))->assertUnprocessable()->assertJsonValidationErrors('type');

    $this->actingAs($this->member)->get('/evenements')->assertOk()->assertInertia(fn ($page) => $page
        ->has('create.types', 4)->where('create.requires_approval', false));
});

it('garde la file d’approbation désactivée par défaut : un événement de membre est publié aussitôt', function (): void {
    expect(EventApproval::required())->toBeFalse();

    F::as($this->member)->postJson('/api/v1/events', p18Event())->assertCreated()->assertJsonPath('data.status', 'publie');
    $event = Event::query()->sole();
    F::as(F::member())->getJson('/api/v1/events/'.$event->ulid)->assertOk();
});

it('file activée : l’événement d’un·e membre attend, invisible des autres, la modération publie directement', function (): void {
    $this->actingAs($this->admin);
    Livewire::test(EventsScreen::class)->call('toggleApproval');
    expect(EventApproval::required())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'settings.events_approval')->count())->toBe(1);

    F::as($this->member)->postJson('/api/v1/events', p18Event())->assertCreated()
        ->assertJsonPath('data.status', 'en_attente')->assertJsonPath('data.is_pending', true);
    $event = Event::query()->sole();

    $other = F::member();
    F::as($other)->getJson('/api/v1/events/'.$event->ulid)->assertNotFound();
    F::as($other)->getJson('/api/v1/events')->assertOk()->assertJsonCount(0, 'data');
    F::as($this->member)->getJson('/api/v1/events/'.$event->ulid)->assertOk();

    F::as($this->moderator)->postJson('/api/v1/events', p18Event(['title' => 'Par la modération']))->assertCreated()->assertJsonPath('data.status', 'publie');
});

it('approuve ou refuse un événement en attente (motif obligatoire), notifie l’organisateur·rice', function (): void {
    Notification::fake();
    EventApproval::set(true, $this->admin);
    F::as($this->member)->postJson('/api/v1/events', p18Event())->assertCreated();
    F::as($this->member)->postJson('/api/v1/events', p18Event(['title' => 'Deuxième']))->assertCreated();
    [$first, $second] = Event::query()->orderBy('id')->get()->all();
    $this->actingAs($this->moderator);

    Livewire::test(EventsScreen::class)->assertSee('Cercle de parole')->call('approve', $first->id);
    expect($first->refresh()->status)->toBe(ContentStatus::Published);
    F::as(F::member())->getJson('/api/v1/events/'.$first->ulid)->assertOk();

    $this->actingAs($this->moderator);
    Livewire::test(EventsScreen::class)->call('askRefuse', $second->id)->call('refuse')->assertHasErrors('reason');
    Livewire::test(EventsScreen::class)->call('askRefuse', $second->id)->set('reason', 'Lieu non précisé.')->call('refuse')->assertHasNoErrors();
    expect($second->refresh()->status)->toBe(ContentStatus::Hidden)
        ->and(AuditLog::query()->whereIn('action', ['event.approved', 'event.refused'])->count())->toBe(2);

    Notification::assertSentTo($this->member, TeamNotice::class, fn (TeamNotice $n): bool => $n->kind === TeamNotice::EVENT_APPROVED);
    Notification::assertSentTo($this->member, TeamNotice::class, fn (TeamNotice $n): bool => $n->kind === TeamNotice::EVENT_REFUSED && $n->reason === 'Lieu non précisé.');
});

it('réserve les décisions et le réglage : la modération décide, seul events.manage règle la file', function (): void {
    $this->actingAs($this->moderator);
    Livewire::test(EventsScreen::class)->call('toggleApproval')->assertForbidden();
    expect(EventApproval::required())->toBeFalse();

    $this->actingAs($this->member)->get('/admin/evenements')->assertForbidden();
});

it('affiche le calendrier du mois par semaine, avec type et navigation', function (): void {
    Event::factory()->create(['title' => 'Atelier couture', 'type' => 'atelier', 'starts_at' => now()->addDays(2)]);
    Event::factory()->create(['title' => 'Le mois prochain', 'starts_at' => now()->addMonth()->startOfMonth()->addDays(3)]);
    $this->actingAs($this->moderator);

    Livewire::test(EventsScreen::class)
        ->assertSee('Atelier couture')->assertSee('Atelier')->assertDontSee('Le mois prochain')
        ->assertSee('Semaine du 9 novembre au 15 novembre')
        ->call('shift', 1)->assertSet('month', '2026-12')->assertSee('Le mois prochain')->assertDontSee('Atelier couture');
});

it('reçoit une suggestion (anonyme possible), valide le message et limite par compte', function (): void {
    config(['xomiton.rate_limits.suggestions_per_day' => 2]);

    F::as($this->member)->postJson('/api/v1/suggestions', ['category' => 'inconnue', 'message' => 'court'])
        ->assertUnprocessable()->assertJsonValidationErrors(['category', 'message']);
    F::as($this->member)->postJson('/api/v1/suggestions', ['category' => 'securite', 'message' => str_repeat('a', 1001)])
        ->assertUnprocessable()->assertJsonValidationErrors(['message']);
    F::as($this->member)->postJson('/api/v1/suggestions', ['category' => 'securite', 'message' => 'Ajouter un bouton pour masquer l’application.', 'is_anonymous' => true])
        ->assertCreated()->assertJsonPath('data.status', 'nouvelle');
    F::as($this->member)->postJson('/api/v1/suggestions', ['category' => 'contenu', 'message' => 'Plus de fiches sur la santé mentale.'])->assertCreated();
    F::as($this->member)->postJson('/api/v1/suggestions', ['category' => 'contenu', 'message' => 'Une troisième idée pour aujourd’hui.'])->assertTooManyRequests();

    $suggestion = Suggestion::query()->orderBy('id')->first();
    expect($suggestion->is_anonymous)->toBeTrue()->and($suggestion->user_id)->toBe($this->member->id);
    $log = AuditLog::query()->where('action', 'suggestions.submitted')->first();
    expect((string) $log->detail)->toBe('category=securite');

    $this->actingAs($this->member)->get('/boite-a-idees')->assertOk()->assertInertia(fn ($page) => $page->component('Member/Suggestion')->has('options.categories', 4));
});

it('inbox admin : anonymat respecté, statut, réponse envoyée en notification, journal', function (): void {
    Notification::fake();
    $suggestion = Suggestion::query()->create(['user_id' => $this->member->id, 'category' => 'fonctionnalite', 'message' => 'Une idée anonyme très utile.', 'is_anonymous' => true, 'status' => 'nouvelle']);
    $this->actingAs($this->moderator);

    Livewire::test(SuggestionsScreen::class)->assertSee('Une idée anonyme très utile.')->assertSee('Anonyme')->assertDontSee($this->member->pseudo);

    Livewire::test(SuggestionsScreen::class)
        ->call('open', $suggestion->id)
        ->set('newStatus', 'retenue')->call('saveStatus')
        ->call('sendReply')->assertHasErrors('reply')
        ->set('reply', 'Merci, nous l’ajoutons à la feuille de route.')->call('sendReply')->assertHasNoErrors();

    $suggestion->refresh();
    expect($suggestion->suggestionStatus()->value)->toBe('retenue')
        ->and($suggestion->reply)->toBe('Merci, nous l’ajoutons à la feuille de route.')
        ->and(AuditLog::query()->whereIn('action', ['suggestions.status_changed', 'suggestions.replied'])->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'suggestions.replied')->value('detail'))->toBeNull();
    Notification::assertSentTo($this->member, TeamNotice::class, fn (TeamNotice $n): bool => $n->kind === TeamNotice::SUGGESTION_REPLIED);
});

it('réserve l’inbox à suggestions.manage et verrouille la suggestion ouverte', function (): void {
    $this->actingAs($this->member)->get('/admin/suggestions')->assertForbidden();
    $this->actingAs($this->moderator)->get('/admin/suggestions')->assertOk();
    expect(fn () => Livewire::test(SuggestionsScreen::class)->set('suggestionId', 1))->toThrow(Exception::class);
});

it('rattrape les permissions P18-10 à P18-14 sur une base déjà semée, de façon idempotente', function (): void {
    Spatie\Permission\Models\Role::findByName('admin', 'web')->revokePermissionTo(['forums.manage', 'announcements.manage', 'events.manage', 'suggestions.manage']);
    Spatie\Permission\Models\Role::findByName('moderateur', 'web')->revokePermissionTo('suggestions.manage');
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    $migration = require database_path('migrations/2026_12_06_100600_grant_p18_community_permissions.php');

    $migration->up();
    $migration->up();
    app(Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

    $admin = Spatie\Permission\Models\Role::findByName('admin', 'web');
    $moderator = Spatie\Permission\Models\Role::findByName('moderateur', 'web');
    expect($admin->hasPermissionTo('forums.manage'))->toBeTrue()
        ->and($admin->hasPermissionTo('announcements.manage'))->toBeTrue()
        ->and($admin->hasPermissionTo('events.manage'))->toBeTrue()
        ->and($moderator->hasPermissionTo('suggestions.manage'))->toBeTrue()
        ->and($moderator->hasPermissionTo('forums.manage'))->toBeFalse()
        ->and(Spatie\Permission\Models\Role::findByName('membre', 'web')->hasPermissionTo('suggestions.manage'))->toBeFalse();
});
