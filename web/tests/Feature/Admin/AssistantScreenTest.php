<?php

declare(strict_types=1);

use App\Application\Assistant\AskAssistant;
use App\Application\Assistant\AssistantProvider;
use App\Application\Assistant\AssistantQuery;
use App\Application\Shared\Answer;
use App\Livewire\Admin\Assistant\Index as AssistantScreen;
use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SettingsSeeder;
use Livewire\Livewire;

/*
 * P12-07 : Assistant LLM /admin/assistant (Livewire) — `assistant_kb.manage` (admin, superadmin), interrupteur du
 * fournisseur (désactivé : la base répond aux membres), consigne, garde-fous, base de connaissances, conversations
 * signalées (lecture journalisée, « traitée »), statistiques sans texte.
 */

beforeEach(function (): void {
    $this->seed([RolesAndPermissionsSeeder::class, SettingsSeeder::class]);
});

function assistantUser(string $role = 'admin'): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

function assistantScreen(string $role = 'admin')
{
    test()->actingAs(assistantUser($role));

    return Livewire::test(AssistantScreen::class);
}

it('réserve /admin/assistant à l’administration : 403 pour le modérateur, le pair et le membre', function (string $role): void {
    $this->actingAs(assistantUser($role))->get('/admin/assistant')->assertForbidden();
})->with(['moderateur', 'pair', 'membre']);

it('ouvre /admin/assistant à l’admin avec l’état du fournisseur', function (): void {
    $this->actingAs(assistantUser())->get('/admin/assistant')->assertOk()->assertSee('Fournisseur actif')->assertSee('kb');
});

it('désactive le fournisseur : le pilote n’est plus appelé pour un membre, la base répond', function (): void {
    $calls = 0;
    $this->app->instance(AssistantProvider::class, new class($calls) implements AssistantProvider
    {
        public function __construct(private int &$calls) {}

        public function name(): string
        {
            return 'fake';
        }

        public function answer(AssistantQuery $query): Answer
        {
            $this->calls++;

            return new Answer(text: 'Réponse du fournisseur', kind: Answer::KIND_FALLBACK);
        }
    });
    $member = User::factory()->create();
    AssistantKb::factory()->withKeywords(['contraception'])->create(['question' => 'Contraception ?', 'answer' => 'Réponse de la base de connaissances.']);

    $ask = fn (): Answer => app(AskAssistant::class)('Parle-moi de la contraception', $member);

    expect($ask()->text)->toBe('Réponse du fournisseur')->and($calls)->toBe(1);

    assistantScreen()->call('setEnabled', false);
    expect(Settings::bool('assistant_enabled', true))->toBeFalse()->and($ask()->text)->toContain('base de connaissances')->and($calls)->toBe(1);

    assistantScreen()->call('setEnabled', true);
    expect($ask()->text)->toBe('Réponse du fournisseur')->and($calls)->toBe(2);
    expect(AuditLog::query()->whereIn('action', ['assistant.disabled', 'assistant.enabled'])->count())->toBe(2);
});

it('valide la consigne système (100 à 6000 caractères), l’enregistre et la rétablit', function (): void {
    $screen = assistantScreen();

    $screen->set('prompt', 'Trop court')->call('savePrompt')->assertHasErrors(['prompt']);
    $screen->set('prompt', str_repeat('a', 6001))->call('savePrompt')->assertHasErrors(['prompt']);

    $custom = str_repeat('Consigne de test. ', 10);
    $screen->set('prompt', $custom)->call('savePrompt')->assertHasNoErrors();
    expect(Settings::string('assistant_prompt'))->toBe(trim($custom));

    $screen->call('resetPrompt');
    expect(Settings::string('assistant_prompt'))->toContain('Tu es l’assistant de Xomiton')->and(AuditLog::query()->where('action', 'assistant.prompt_saved')->exists())->toBeTrue();
});

it('enregistre les garde-fous et l’escalade, et exige des mots-clés quand l’escalade est active', function (): void {
    $screen = assistantScreen();

    $screen->set('guardrails.no_pii', false)->set('escalationKeywords', '')->call('saveGuardrails')->assertHasErrors(['escalationKeywords']);
    $screen->set('escalationKeywords', 'danger, urgence')->set('escalationTarget', 'psychologist')->call('saveGuardrails')->assertHasNoErrors();

    expect(Settings::array('assistant_guardrails')['no_pii'])->toBeFalse()->and(Settings::array('assistant_guardrails')['no_diagnosis'])->toBeTrue()
        ->and(Settings::array('assistant_escalation'))->toMatchArray(['on' => true, 'keywords' => 'danger, urgence', 'target' => 'psychologist']);

    $screen->set('escalationTarget', 'nimporte')->call('saveGuardrails')->assertHasErrors(['escalationTarget']);
});

it('gère la base de connaissances : validation, mots-clés normalisés, activation, suppression', function (): void {
    $screen = assistantScreen();

    $screen->call('newKb')->set('kbForm', ['question' => 'Court', 'answer' => 'Trop court', 'keywords' => '', 'is_active' => true])->call('saveKb')
        ->assertHasErrors(['kbForm.answer', 'kbForm.keywords']);

    $screen->set('kbForm', ['question' => 'Comment porter plainte ?', 'answer' => 'Vous pouvez vous rendre au commissariat ou à la gendarmerie.', 'keywords' => 'Plainte, POLICE ; plainte, justice', 'is_active' => true])
        ->call('saveKb')->assertHasNoErrors();

    $entry = AssistantKb::query()->firstOrFail();
    expect($entry->keywords)->toBe(['plainte', 'police', 'justice'])->and($entry->matches('Je veux porter PLAINTE'))->toBeTrue();

    $screen->call('toggleKb', $entry->id);
    expect($entry->fresh()->is_active)->toBeFalse();
    $screen->call('editKb', $entry->id)->assertSet('kbForm.keywords', 'plainte, police, justice');
    $screen->call('deleteKb', $entry->id);
    expect(AssistantKb::query()->count())->toBe(0);
});

it('lit une conversation signalée avec une ligne au journal, la marque traitée, sans jamais lister de texte', function (): void {
    $conversation = AssistantConversation::factory()->flagged('Détresse évoquée')->create();
    AssistantMessage::factory()->create(['conversation_id' => $conversation->id, 'role' => 'user', 'body' => 'Message secret de la personne']);
    $screen = assistantScreen()->assertSee('Détresse évoquée')->assertDontSee('Message secret de la personne');

    $screen->call('openConversation', $conversation->id)->assertSee('Message secret de la personne');
    expect(AuditLog::query()->where('action', 'assistant.conversation_read')->count())->toBe(1);

    $screen->call('resolveConversation', $conversation->id);
    expect($conversation->fresh()->is_flagged)->toBeFalse()->and(AuditLog::query()->where('action', 'assistant.conversation_resolved')->exists())->toBeTrue();
    $screen->assertDontSee('Détresse évoquée');
});

it('calcule les statistiques sans texte : conversations, signalées, escaladées, entrées les plus consultées', function (): void {
    AssistantConversation::factory()->count(2)->create();
    AssistantConversation::factory()->escalated()->create();
    $top = AssistantKb::factory()->create(['question' => 'Question la plus posée', 'hits_count' => 42]);
    AssistantKb::factory()->create(['hits_count' => 3]);
    AssistantMessage::factory()->fromAssistant($top)->create();

    $screen = assistantScreen();
    $summary = $screen->instance()->summary();

    expect($summary)->toMatchArray(['conversations' => 4, 'flagged' => 1, 'escalated' => 1, 'kb_hits' => 45])
        ->and($summary['answered_share'])->toBe(100);
    $screen->assertSee('Question la plus posée');
});
