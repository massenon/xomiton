<?php

declare(strict_types=1);

use App\Application\Assistant\AskAssistant;
use App\Application\Assistant\AssistantQuery;
use App\Application\Shared\Answer;
use App\Infrastructure\Assistant\AssistantProviders;
use App\Infrastructure\Assistant\GeminiAssistantProvider;
use App\Infrastructure\Assistant\PiiMasker;
use App\Models\AssistantKb;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/*
 * Gemini (Q7) : branché mais désactivé par défaut. Désactivé = jamais d'appel ; crise = jamais d'appel ; la base
 * répond d'abord ; PII masquée ; repli sur « pas trouvé » en cas d'erreur / blocage ; débit limité ; journal sans contenu.
 */

function geminiOk(string $text = 'Je comprends, tu peux en parler ici.'): array
{
    return ['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text]]]]]])];
}

function askGemini(User $user, string $question, bool $persist = false): Answer
{
    return app(GeminiAssistantProvider::class)->answer(new AssistantQuery($question, $user, $persist));
}

beforeEach(function (): void {
    Http::preventStrayRequests();
    config(['xomiton.assistant.llm_enabled' => true, 'xomiton.assistant.provider' => 'gemini', 'xomiton.assistant.gemini.api_key' => 'k-test']);
    $this->user = User::factory()->create(['name' => 'Aminata Kossou', 'pseudo' => 'lune_bleue']);
});

it('est désactivé par défaut et ne résout pas gemini sans le drapeau', function (): void {
    config(['xomiton.assistant.llm_enabled' => false]);

    expect(AssistantProviders::effective('gemini'))->toBe('kb')
        ->and(file_get_contents(base_path('.env.example')))->toContain('ASSISTANT_LLM_ENABLED=false');
    Http::assertNothingSent();
});

it('n’appelle jamais Gemini quand le drapeau est faux', function (): void {
    config(['xomiton.assistant.llm_enabled' => false]);
    Http::fake();

    $answer = askGemini($this->user, 'Comment organiser une randonnée en forêt ?');

    expect($answer->isFallback())->toBeTrue();
    Http::assertNothingSent();
});

it('n’appelle jamais Gemini sans clé', function (): void {
    config(['xomiton.assistant.gemini.api_key' => '']);
    Http::fake();

    askGemini($this->user, 'Comment organiser une randonnée ?');

    Http::assertNothingSent();
});

it('laisse la base de connaissances répondre d’abord', function (): void {
    AssistantKb::factory()->create(['question' => 'Comment signaler un contenu ?', 'answer' => 'Utilise le bouton Signaler.', 'keywords' => ['signaler', 'contenu'], 'is_active' => true]);
    Http::fake();

    $answer = askGemini($this->user, 'Comment signaler un contenu ?');

    expect($answer->text)->toContain('bouton Signaler');
    Http::assertNothingSent();
});

it('n’envoie jamais une question de crise ou de violence', function (string $question): void {
    Http::fake();

    $answer = app(AskAssistant::class)($question, $this->user);

    expect($answer->isCrisis())->toBeTrue();
    Http::assertNothingSent();

    // Même appelé directement (défense en profondeur).
    askGemini($this->user, $question);
    Http::assertNothingSent();
})->with(['mourir' => ['J’ai envie de mourir'], 'viol' => ['Je veux parler d’un viol'], 'danger' => ['Je suis en danger chez moi'], 'frappe' => ['Mon conjoint me frappe']]);

it('appelle Gemini en repli, avec la consigne système et sans donnée personnelle', function (): void {
    Http::fake(geminiOk());

    $answer = askGemini($this->user, 'Salut Aminata, écris à aminata@example.org ou appelle le +229 01 97 38 88 47, je suis à 6.37031, 2.39124. Quel temps fait-il à Ouidah ?');

    expect($answer->text)->toBe('Je comprends, tu peux en parler ici.')->and($answer->kind)->toBe(Answer::KIND_KB);
    Http::assertSent(function ($request): bool {
        $body = json_encode($request->data(), JSON_UNESCAPED_UNICODE);
        $system = $request['systemInstruction']['parts'][0]['text'];

        return $request->hasHeader('x-goog-api-key', 'k-test')
            && ! str_contains($request->url(), 'k-test')
            && ! str_contains($body, 'aminata@example.org') && ! str_contains($body, '97 38 88 47') && ! str_contains($body, '6.37031') && ! str_contains(mb_strtolower($body), 'aminata')
            && ! str_contains($body, (string) $this->user->email) && ! str_contains($body, 'lune_bleue')
            && str_contains($body, 'Ouidah')
            && str_contains($system, 'tutoyant') && str_contains($system, 'médical ou juridique') && str_contains($system, 'Signaler ou SOS');
    });
});

it('masque les données personnelles', function (): void {
    $masked = PiiMasker::mask('Je suis Awa Dossou (@awa_d), mail awa@x.bj, tel 97-38-88-47, voir https://x.bj/p?id=1', ['Awa Dossou']);

    expect($masked)->not->toContain('Awa')->not->toContain('@x.bj')->not->toContain('97-38')->not->toContain('https')->not->toContain('@awa_d')
        ->and($masked)->toContain('[nom]')->toContain('[e-mail]')->toContain('[numéro]')->toContain('[lien]');
});

it('revient à la réponse « pas trouvé » sur erreur, blocage, réponse vide ou délai dépassé', function (string $case): void {
    $stop = fn (string $text) => ['candidates' => [['finishReason' => 'STOP', 'content' => ['parts' => [['text' => $text]]]]]];

    Http::fake(['generativelanguage.googleapis.com/*' => match ($case) {
        'http500' => Http::response([], 500),
        'blocage' => Http::response(['promptFeedback' => ['blockReason' => 'SAFETY']]),
        'finish_safety' => Http::response(['candidates' => [['finishReason' => 'SAFETY']]]),
        'vide' => Http::response($stop(' ')),
        'delai' => fn () => throw new Illuminate\Http\Client\ConnectionException('timeout'),
    }]);

    expect(askGemini($this->user, 'Quelle est la couleur du ciel demain ?')->isFallback())->toBeTrue();
})->with(['http500', 'blocage', 'finish_safety', 'vide', 'delai']);

it('limite le débit par membre', function (): void {
    config(['xomiton.assistant.gemini.per_hour' => 2]);
    RateLimiter::clear('assistant-llm:'.$this->user->id);
    Http::fake(geminiOk());

    foreach (range(1, 4) as $i) {
        askGemini($this->user, 'Question libre numéro '.$i.' sur la météo ?');
    }

    Http::assertSentCount(2);
});

it('journalise sans contenu de question ni de réponse', function (): void {
    Log::spy();
    Http::fake(geminiOk('Réponse secrète de test'));

    askGemini($this->user, 'Une question très personnelle sur ma voisine');

    Log::shouldHaveReceived('info')->withArgs(function (string $m, array $c = []): bool {
        $dump = json_encode($c);

        return $m === 'assistant.llm' && ! str_contains($dump, 'voisine') && ! str_contains($dump, 'secrète') && ! str_contains($dump, (string) $this->user->email);
    })->once();
});

it('enregistre l’échange quand persist est demandé', function (): void {
    Http::fake(geminiOk());

    $answer = askGemini($this->user, 'Une question libre sur le jardinage ?', persist: true);

    expect($answer->conversationId)->not->toBeNull();
});
