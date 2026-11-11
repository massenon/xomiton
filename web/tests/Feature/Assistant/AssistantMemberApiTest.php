<?php

declare(strict_types=1);

use App\Application\Assistant\AskAssistant;
use App\Application\Assistant\AssistantProvider;
use App\Application\Assistant\AssistantQuery;
use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\AlertFixtures;

/*
 * Assistant membre (P8-11, Q7, Q41, Q55) : `POST assistant/question` étendu — garde-fous de crise **sans appel au
 * fournisseur**, visiteur·se jamais servi·e par le fournisseur, membre servi·e par le pilote configuré (repli sur la base
 * s'il échoue), échange enregistré seulement sur demande (`persist`), poursuite d'une conversation à soi (404 sinon),
 * `DELETE assistant/conversations/{id}`, membre par jeton Bearer sur la route publique, texte de crise d'un compte
 * connecté sans enregistrement, pas de doublon quand le fournisseur échoue après avoir enregistré.
 */

/** Fournisseur de test : compte ses appels, répond un texte fixe, enregistre l'échange comme le ferait un pilote LLM. */
final class RecordingAssistantProvider implements AssistantProvider
{
    /** @var list<AssistantQuery> */
    public array $queries = [];

    public bool $fail = false;

    /** Enregistre l'échange puis échoue (pilote fautif qui n'enregistre pas en dernier). */
    public bool $failAfterRecord = false;

    public function name(): string
    {
        return 'test';
    }

    public function answer(AssistantQuery $query): Answer
    {
        $this->queries[] = $query;

        if ($this->fail) {
            throw new RuntimeException('Fournisseur indisponible');
        }

        $answer = new Answer(text: 'Réponse du fournisseur', kind: Answer::KIND_KB);

        $answer = $query->persist
            ? app(KnowledgeBaseAnswerer::class)->record($query->user, $query->conversation, $query->question, $answer)
            : $answer;

        if ($this->failAfterRecord) {
            throw new RuntimeException('Échec après enregistrement');
        }

        return $answer;
    }
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    AssistantKb::factory()->create([
        'question' => "Comment porter plainte\u{00A0}?",
        'answer' => 'Rends-toi au commissariat le plus proche.',
        'keywords' => ['plainte', 'police'],
        'is_active' => true,
    ]);

    $this->provider = new RecordingAssistantProvider;
    $this->app->instance(AssistantProvider::class, $this->provider);
    $this->member = AlertFixtures::member();
});

it('répond à un message de crise par les numéros d’urgence sans jamais appeler le fournisseur', function (): void {
    $response = $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'Il me frappe tous les soirs'])
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private');

    expect($this->provider->queries)->toBe([])
        ->and($response->json('data.kind'))->toBe('crisis')
        ->and($response->json('data.emergency'))->toBeTrue()
        ->and($response->json('data.text'))->toContain("Police secours\u{00A0}: 117")->toContain('+229 01 97 38 88 47')
        ->and($response->json('data.links.0.url'))->toBe(route('sos'))
        ->and(collect($response->json('data.links'))->pluck('url'))->toContain('tel:117')
        ->and($response->json('data.escalation'))->toBeTrue()
        ->and($response->json('data.conversation_id'))->toBeNull()
        ->and(AssistantConversation::count())->toBe(0);
});

it('ne sert jamais un·e visiteur·se par le fournisseur (Q41) et garde le contrat public', function (): void {
    $response = $this->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?', 'persist' => true])
        ->assertOk();

    expect($this->provider->queries)->toBe([])
        ->and($response->json('data.kind'))->toBe('kb')
        ->and(array_keys($response->json('data')))->toBe(['text', 'kind', 'emergency', 'suggestions', 'links'])
        ->and(AssistantConversation::count())->toBe(0);
});

it('passe une question ordinaire d’un·e membre au fournisseur configuré, sans rien enregistrer par défaut', function (): void {
    $response = $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?'])
        ->assertOk()
        ->assertJsonPath('data.text', 'Réponse du fournisseur')
        ->assertJsonPath('data.escalation', false)
        ->assertJsonPath('data.conversation_id', null);

    expect($this->provider->queries)->toHaveCount(1)
        ->and($this->provider->queries[0]->user->is($this->member))->toBeTrue()
        ->and($this->provider->queries[0]->persist)->toBeFalse()
        ->and(array_keys($response->json('data')))->toBe(['text', 'kind', 'emergency', 'suggestions', 'links', 'conversation_id', 'escalation'])
        ->and(AssistantConversation::count())->toBe(0)
        ->and(AssistantMessage::count())->toBe(0);
});

it('répond par la base quand le fournisseur échoue, avec un avertissement au journal', function (): void {
    $this->provider->fail = true;
    Log::spy();

    $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'kb')
        ->assertJsonPath('data.text', 'Rends-toi au commissariat le plus proche.');

    Log::shouldHaveReceived('warning')->once()->withArgs(fn (string $message): bool => str_contains($message, '« test »'));
});

it('enregistre l’échange chiffré sur demande, le poursuit et marque la crise pour relecture, toujours sans fournisseur en crise', function (): void {
    $first = $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?', 'persist' => true])
        ->assertOk();

    $conversationId = $first->json('data.conversation_id');
    $conversation = AssistantConversation::query()->findOrFail($conversationId);

    expect($conversation->user_id)->toBe($this->member->id)
        ->and($conversation->messages()->pluck('body')->all())->toBe(['Comment porter plainte ?', 'Réponse du fournisseur'])
        ->and(DB::table('assistant_messages')->pluck('body')->implode(' '))->not->toContain('porter plainte');

    $crisis = $this->postJson('/api/v1/assistant/question', ['question' => 'il va me tuer', 'persist' => true, 'conversation_id' => $conversationId])
        ->assertOk()
        ->assertJsonPath('data.kind', 'crisis')
        ->assertJsonPath('data.conversation_id', $conversationId);

    expect($this->provider->queries)->toHaveCount(1)
        ->and($conversation->refresh()->is_flagged)->toBeTrue()
        ->and($conversation->messages()->count())->toBe(4)
        ->and($crisis->json('data.text'))->toContain(__('assistant.crisis.human'));
});

it('refuse de poursuivre la conversation d’un autre compte (404 sans oracle) et valide les champs', function (): void {
    $other = AssistantConversation::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'bonjour à toi', 'persist' => true, 'conversation_id' => $other->id])
        ->assertNotFound()
        ->assertJsonPath('code', 'not_found');

    $this->postJson('/api/v1/assistant/question', ['question' => 'bonjour', 'persist' => 'peut-être', 'conversation_id' => 'abc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['persist', 'conversation_id']);

    expect($other->messages()->count())->toBe(0);
});

it('efface une conversation enregistrée et ses messages, 404 pour celle d’un autre compte, 401 sans compte', function (): void {
    $mine = app(AskAssistant::class)('Comment porter plainte ?', $this->member, true);
    $other = AssistantConversation::factory()->create(['user_id' => User::factory()->create()->id]);

    $this->deleteJson('/api/v1/assistant/conversations/'.$mine->conversationId)->assertUnauthorized();

    $this->actingAs($this->member)->deleteJson('/api/v1/assistant/conversations/'.$other->id)->assertNotFound();
    $this->deleteJson('/api/v1/assistant/conversations/'.$mine->conversationId)->assertNoContent();

    expect(AssistantConversation::query()->whereKey($mine->conversationId)->exists())->toBeFalse()
        ->and(AssistantMessage::query()->where('conversation_id', $mine->conversationId)->count())->toBe(0)
        ->and($other->fresh())->not->toBeNull();
});

it('sert un·e membre authentifié·e par jeton Bearer sur la route publique (Flutter) : fournisseur, enregistrement, crise', function (): void {
    $token = $this->member->createToken('Pixel 7')->plainTextToken;
    $bearer = ['Authorization' => 'Bearer '.$token];

    $first = $this->withHeaders($bearer)
        ->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?', 'persist' => true])
        ->assertOk()
        ->assertJsonPath('data.text', 'Réponse du fournisseur');

    $conversation = AssistantConversation::query()->findOrFail($first->json('data.conversation_id'));

    $crisis = $this->withHeaders($bearer)
        ->postJson('/api/v1/assistant/question', ['question' => 'il me frappe'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'crisis')
        ->assertJsonPath('data.escalation', true)
        ->assertJsonPath('data.conversation_id', null);

    expect($this->provider->queries)->toHaveCount(1)
        ->and($this->provider->queries[0]->user->is($this->member))->toBeTrue()
        ->and($conversation->user_id)->toBe($this->member->id)
        ->and($conversation->messages()->count())->toBe(2)
        // Compte connecté sans enregistrement : même consigne que la page (« Parler à un humain »), sans adresse dans le texte.
        ->and($crisis->json('data.text'))->toContain(__('assistant.crisis.human'))
        ->not->toContain(__('assistant.crisis.need'))
        ->not->toContain(route('sos'));
});

it('propose « Parler à un humain » dans le texte de crise d’un·e membre qui n’enregistre pas (session)', function (): void {
    $response = $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'je suis séquestrée'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'crisis')
        ->assertJsonPath('data.escalation', true);

    expect($response->json('data.text'))->toContain(__('assistant.crisis.human'))
        ->not->toContain(__('assistant.crisis.need'))
        ->and(AssistantConversation::count())->toBe(0);
});

it('n’enregistre l’échange qu’une fois quand le fournisseur échoue après l’avoir enregistré', function (): void {
    $mine = app(AskAssistant::class)('Comment porter plainte ?', $this->member, true);
    $this->provider->queries = [];
    $this->provider->failAfterRecord = true;
    Log::spy();

    $this->actingAs($this->member)
        ->postJson('/api/v1/assistant/question', ['question' => 'Où trouver une écoute ?', 'persist' => true, 'conversation_id' => $mine->conversationId])
        ->assertOk()
        ->assertJsonPath('data.conversation_id', $mine->conversationId);

    $this->postJson('/api/v1/assistant/question', ['question' => 'Comment porter plainte ?', 'persist' => true])
        ->assertOk();

    $conversation = AssistantConversation::query()->findOrFail($mine->conversationId);

    expect($this->provider->queries)->toHaveCount(2)
        // 2 messages du premier échange + 2 du second, pas les 2 que le fournisseur avait écrits avant d'échouer.
        ->and($conversation->messages()->count())->toBe(4)
        ->and(array_count_values($conversation->messages()->pluck('body')->all()))->toMatchArray(['Où trouver une écoute ?' => 1, 'Réponse du fournisseur' => 1])
        // Nouvelle conversation : seulement celle de la base, pas celle du fournisseur annulée.
        ->and(AssistantConversation::query()->where('user_id', $this->member->id)->count())->toBe(2)
        ->and(AssistantMessage::count())->toBe(6);

    Log::shouldHaveReceived('warning')->twice();
});
