<?php

declare(strict_types=1);

use App\Enums\BadWordSeverity;
use App\Livewire\Admin\Moderation\Index as ModerationScreen;
use App\Models\AuditLog;
use App\Models\BadWord;
use App\Models\ContentFilterAllowedPhrase;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\AlertFixtures;
use Tests\Support\FeedFixtures;
use Tests\Support\MessagingFixtures as M;

/*
 * Filtre minimal de contenus (IOS-10, D-IOS-9, règle Apple 1.2) : 422 `content_rejected` sur les contenus publics et les
 * messages de GROUPE, jamais sur un message privé 1-à-1 ni sur un signalement ou un SOS ; masquage + file de modération ;
 * journal d'audit sans contenu ; liste blanche ; écran d'administration (permission `moderation.filter`).
 */

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->author = FeedFixtures::member();
});

it('refuse une publication avec un mot filtré, accent et casse ignorés, ou un contournement (422 content_rejected)', function (string $body): void {
    FeedFixtures::badWord('vilaine');

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => $body])
        ->assertUnprocessable()->assertJsonPath('code', 'content_rejected')->assertJsonValidationErrors('body');

    expect(Post::query()->count())->toBe(0);
})->with(['Tu es VILAÏNE', 'v.i.l.a.i.n.e', 'vilaine !!!']);

it('refuse les coordonnées personnelles dans un commentaire public et laisse passer un texte ordinaire', function (): void {
    $post = Post::factory()->create();

    FeedFixtures::as($this->author)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Écris-moi au +229 97 00 00 00'])
        ->assertUnprocessable()->assertJsonPath('code', 'content_rejected');
    FeedFixtures::as($this->author)->postJson('/api/v1/posts/'.$post->ulid.'/comments', ['body' => 'Courage, on est là pour toi.'])
        ->assertCreated();
});

it('signale un lien suspect sans le refuser (action « flag » par défaut)', function (): void {
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Regardez http://bit.ly/abc123'])->assertCreated();

    expect(ModerationReport::query()->count())->toBe(1)->and(Post::query()->sole()->status->value)->toBe('publie');
});

it('masque puis met en file de modération un texte de gravité « masquer »', function (): void {
    BadWord::query()->create(['word' => 'mechante', 'category' => 'insulte', 'severity' => BadWordSeverity::Hide, 'is_active' => true]);

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Quelle méchante idée'])->assertCreated();

    $post = Post::query()->sole()->refresh();
    expect($post->status->value)->toBe('masque')->and(ModerationReport::query()->forSubject($post)->count())->toBe(1);
});

it('filtre les messages de GROUPE mais jamais un message privé 1-à-1', function (): void {
    FeedFixtures::badWord('vilaine');
    $awa = M::member();
    $bea = M::member();
    $cleo = M::member();
    $private = M::privateConversation($awa, $bea);
    $group = M::groupConversation($awa, [$bea, $cleo]);

    M::send($awa, $group, 'Tu es vilaine')->assertUnprocessable()->assertJsonPath('code', 'content_rejected');
    M::send($awa, $group, 'Bonjour le groupe')->assertCreated();
    M::send($awa, $private, 'Tu es vilaine, appelle le +229 97 00 00 00')->assertCreated();

    expect(Message::query()->where('conversation_id', $private->id)->count())->toBe(1)
        ->and(Message::query()->where('conversation_id', $group->id)->count())->toBe(1);
});

it('n’applique jamais le filtre aux signalements, aux SOS et aux besoins', function (): void {
    FeedFixtures::badWord('vilaine');
    $key = (string) Str::uuid();

    $this->withHeader('Idempotency-Key', $key)->postJson('/api/v1/sos', [
        'zone_id' => AlertFixtures::zone('Littoral')->id,
        'message' => 'Il m’a traitée de vilaine, appelez le +229 97 00 00 00, 12 rue des Palmiers',
    ])->assertCreated();
});

it('refuse un pseudonyme injurieux ou porteur d’un numéro à l’inscription (422 content_rejected)', function (): void {
    FeedFixtures::badWord('vilaine');
    $payload = ['email' => 'x@exemple.bj', 'password' => 'Xomiton2026!', 'cgu' => true, 'privacy' => true, 'age_confirmed' => true];

    $this->postJson('/api/v1/auth/register', $payload + ['pseudo' => 'la_vilaine'])
        ->assertUnprocessable()->assertJsonPath('code', 'content_rejected')->assertJsonValidationErrors('pseudo');
    $this->postJson('/api/v1/auth/register', $payload + ['pseudo' => '22997000000'])
        ->assertUnprocessable()->assertJsonPath('code', 'content_rejected');
});

it('journalise le refus sans le contenu ni le terme', function (): void {
    FeedFixtures::badWord('vilaine');

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Tu es vilaine'])->assertUnprocessable();

    $entry = AuditLog::query()->where('action', 'content_filter.rejected')->sole();
    expect($entry->detail)->toContain('surface=post')->and($entry->detail)->not->toContain('vilaine')->and($entry->detail)->not->toContain('Tu es');
});

it('respecte la liste blanche', function (): void {
    FeedFixtures::badWord('tarte');
    ContentFilterAllowedPhrase::query()->create(['phrase' => 'Tarte au citron']);

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Une tarte au citron pour la fête'])->assertCreated();
    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Quelle tarte !'])->assertUnprocessable();
});

it('se désactive par configuration', function (): void {
    FeedFixtures::badWord('vilaine');
    config(['xomiton.moderation.content_filter.enabled' => false]);

    FeedFixtures::as($this->author)->postJson('/api/v1/posts', ['body' => 'Tu es vilaine'])->assertCreated();
});

// --- Administration ---------------------------------------------------------------------------------------------------

function filterScreen(string $role = 'admin')
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);
    test()->actingAs($user);

    return Livewire::test(ModerationScreen::class);
}

it('réserve la gestion des mots à la permission moderation.filter (pas aux modérateur·rices)', function (): void {
    filterScreen('moderateur')->call('addWord')->assertForbidden();
    filterScreen('moderateur')->call('testFilter')->assertForbidden();
});

it('ajoute un mot avec sa catégorie, gère la liste blanche, teste un texte et compte les refus', function (): void {
    filterScreen()
        ->set('wordText', 'Vilaine')->set('wordSeverity', 'masquer')->set('wordCategory', 'insulte')->call('addWord')->assertHasNoErrors()
        ->set('wordCategory', 'coordonnees')->call('addWord')->assertHasErrors(['wordCategory'])
        ->set('allowText', 'Vilaine Rue')->call('addAllowed')->assertHasNoErrors()
        ->set('testText', 'Tu es vilaine')->call('testFilter')->assertSet('testResult.outcome', 'hidden')
        ->set('testText', 'Rendez-vous rue Vilaine Rue')->call('testFilter')->assertSet('testResult.outcome', 'clean')
        ->set('testText', 'Appelle le 0197000000')->call('testFilter')->assertSet('testResult.outcome', 'blocked');

    expect(BadWord::query()->sole()->category)->toBe('insulte')->and(ContentFilterAllowedPhrase::query()->count())->toBe(1);

    FeedFixtures::badWord('bloqueur');
    FeedFixtures::as(FeedFixtures::member())->postJson('/api/v1/posts', ['body' => 'bloqueur'])->assertUnprocessable();

    $screen = filterScreen();
    expect($screen->instance()->stats['blocked'])->toBe(1);
    $screen->call('removeAllowed', ContentFilterAllowedPhrase::query()->value('id'));
    expect(ContentFilterAllowedPhrase::query()->count())->toBe(0);
});
