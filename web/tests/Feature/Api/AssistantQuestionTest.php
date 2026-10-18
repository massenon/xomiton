<?php

declare(strict_types=1);

use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;

/*
 * POST /api/v1/assistant/question (P4-01, Q41 a) : réponse de la base, garde-fous de crise avec liens SOS / tel: /
 * besoin / signalement / urgences et sans promesse de rappel, repli avec suggestions, validation, limiteur `assistant`
 * par IP (cache `array` neuf à chaque test), chemin Sanctum stateful (419 sans X-XSRF-TOKEN), aucune écriture.
 */

beforeEach(function (): void {
    foreach (require database_path('seeders/data/content/assistant_kb.php') as [$question, $answer, $keywords, $active]) {
        AssistantKb::factory()->create(['question' => $question, 'answer' => $answer, 'keywords' => $keywords, 'is_active' => $active, 'hits_count' => 0]);
    }
});

it('répond depuis la base, sans lien ni enregistrement de l’échange', function (): void {
    $entry = AssistantKb::query()->where('question', "Comment porter plainte\u{00A0}?")->firstOrFail();

    $response = $this->postJson(route('api.assistant.question'), ['question' => 'Comment porter plainte ?'])
        ->assertOk()
        ->assertJsonPath('data.text', $entry->answer)
        ->assertJsonPath('data.kind', 'kb')
        ->assertJsonPath('data.emergency', false)
        ->assertJsonPath('data.links', []);

    expect(array_keys($response->json('data')))->toBe(['text', 'kind', 'emergency', 'suggestions', 'links'])
        ->and($response->json('data.suggestions'))->each->toBeString()
        ->and($entry->fresh()->hits_count)->toBe(1)
        ->and(AssistantConversation::count())->toBe(0)
        ->and(AssistantMessage::count())->toBe(0);
});

it('donne la réponse de crise avec SOS, appels, besoin, signalement et urgences, sans promesse de rappel', function (): void {
    $response = $this->postJson('/api/v1/assistant/question', ['question' => 'il va me tuer'])->assertOk();
    $text = (string) $response->json('data.text');

    expect($response->json('data.kind'))->toBe('crisis')
        ->and($response->json('data.emergency'))->toBeTrue()
        ->and($text)->toContain('117')
        // Rien n'est enregistré : aucun humain ne peut recontacter, le texte renvoie vers « Exprimer un besoin ».
        ->toContain(__('assistant.crisis.need'))
        ->not->toContain('recontacte')
        ->not->toContain(__('assistant.crisis.human'))
        // Les adresses sont des boutons, pas du texte.
        ->not->toContain(route('sos'))
        ->not->toContain(route('signaler'))
        ->and($response->json('data'))->not->toHaveKeys(['matched_kb_id', 'conversation_id'])
        ->and($response->json('data.links'))->toBe([
            ['label' => __('buttons.help.sos_gps'), 'url' => route('sos'), 'variant' => 'danger'],
            ['label' => __('assistant.links.call', ['number' => '117', 'label' => 'Police secours']), 'url' => 'tel:117', 'variant' => 'primary-soft'],
            ['label' => __('assistant.links.call', ['number' => '118', 'label' => 'Pompiers / secours']), 'url' => 'tel:118', 'variant' => 'primary-soft'],
            ['label' => __('assistant.links.call', ['number' => '+229 01 97 38 88 47', 'label' => 'Ligne d’écoute Xomiton']), 'url' => 'tel:+2290197388847', 'variant' => 'primary-soft'],
            ['label' => __('assistant.links.need'), 'url' => route('signaler', ['tab' => 'besoin']), 'variant' => 'primary-soft'],
            ['label' => __('buttons.help.report'), 'url' => route('signaler'), 'variant' => 'primary-soft'],
            ['label' => __('buttons.help.emergency_contacts'), 'url' => route('urgences'), 'variant' => 'primary-soft'],
        ]);
});

it('prend les liens d’appel dans les numéros d’urgence paramétrés', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte', 'number' => '138']]);

    $links = $this->postJson('/api/v1/assistant/question', ['question' => 'il va me tuer'])->assertOk()->json('data.links');

    expect(array_values(array_filter($links, static fn (array $link): bool => str_starts_with($link['url'], 'tel:'))))
        ->toBe([['label' => __('assistant.links.call', ['number' => '138', 'label' => 'Ligne verte']), 'url' => 'tel:138', 'variant' => 'primary-soft']]);
});

it('replie avec trois suggestions quand rien ne correspond', function (): void {
    $response = $this->postJson('/api/v1/assistant/question', ['question' => 'bonjour, tu vas bien ?'])->assertOk();

    expect($response->json('data.kind'))->toBe('fallback')
        ->and($response->json('data.suggestions'))->toHaveCount(3)->each->toBeString()
        ->and($response->json('data.links'))->toBe([]);
});

it('valide la question : obligatoire, 2 à 500 caractères', function (array $payload): void {
    $this->postJson('/api/v1/assistant/question', $payload)
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors('question');
})->with([
    'absente' => [[]],
    'vide' => [['question' => '']],
    'un caractère' => [['question' => 'a']],
    '501 caractères' => [['question' => str_repeat('a', 501)]],
    'non textuelle' => [['question' => ['a', 'b']]],
]);

it('nomme le champ « question » dans le message de validation', function (): void {
    $this->postJson('/api/v1/assistant/question', [])
        ->assertJsonPath('errors.question.0', __('validation.required', ['attribute' => __('validation.attributes.question')]));
});

it('limite les questions par IP (429 avec message traduit) : une autre IP passe, un compte reste compté par IP', function (): void {
    config(['xomiton.rate_limits.assistant_per_minute' => 2]);
    $ip = ['REMOTE_ADDR' => '10.0.0.1'];

    $this->withServerVariables($ip)->postJson('/api/v1/assistant/question', ['question' => 'consentement'])->assertOk();
    $this->withServerVariables($ip)->postJson('/api/v1/assistant/question', ['question' => 'consentement'])->assertOk();

    $response = $this->withServerVariables($ip)->postJson('/api/v1/assistant/question', ['question' => 'consentement'])
        ->assertStatus(429)
        ->assertHeader('Retry-After')
        ->assertJsonPath('code', 'too_many_requests');

    expect($response->json('message'))->toBe(__('auth.throttle', ['seconds' => (int) $response->headers->get('Retry-After')]));

    // Se connecter depuis la même adresse ne remet pas le compteur à zéro.
    $this->actingAs(User::factory()->create())
        ->withServerVariables($ip)
        ->postJson('/api/v1/assistant/question', ['question' => 'consentement'])
        ->assertStatus(429);

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/v1/assistant/question', ['question' => 'consentement'])
        ->assertOk();
});

it('sur le chemin stateful (Referer du domaine), exige le jeton XSRF : 419 sans, 200 avec', function (): void {
    // ValidateCsrfToken laisse tout passer pendant les tests unitaires : cette variante vérifie vraiment le jeton.
    $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
    {
        protected function runningUnitTests(): bool
        {
            return false;
        }
    });

    $stateful = ['Referer' => 'http://localhost/', 'Accept' => 'application/json'];

    $this->withHeaders($stateful)
        ->postJson('/api/v1/assistant/question', ['question' => 'consentement'])
        ->assertStatus(419)
        ->assertJsonPath('code', 'session_expired');

    $xsrf = encrypt(CookieValuePrefix::create('XSRF-TOKEN', app('encrypter')->getKey()).'jeton-de-test', false);

    $this->withSession(['_token' => 'jeton-de-test'])
        ->withHeaders($stateful + ['X-XSRF-TOKEN' => $xsrf])
        ->postJson('/api/v1/assistant/question', ['question' => 'consentement'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'kb');
});

it('répond de même à un·e membre connecté·e, sans rien enregistrer', function (): void {
    $this->actingAs(User::factory()->create());

    $this->postJson('/api/v1/assistant/question', ['question' => 'contraception d’urgence'])
        ->assertOk()
        ->assertJsonPath('data.kind', 'kb');

    expect(AssistantConversation::count())->toBe(0)->and(AssistantMessage::count())->toBe(0);
});
