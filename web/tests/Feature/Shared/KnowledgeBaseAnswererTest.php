<?php

declare(strict_types=1);

use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\DB;

/*
 * P3-09 : réponse de la base par mots-clés et similarité, garde-fous de crise prioritaires, repli avec suggestions,
 * aucune écriture pour un·e visiteur·se, enregistrement chiffré facultatif pour un·e membre.
 */

beforeEach(function (): void {
    foreach (require database_path('seeders/data/content/assistant_kb.php') as [$question, $answer, $keywords, $active]) {
        AssistantKb::factory()->create(['question' => $question, 'answer' => $answer, 'keywords' => $keywords, 'is_active' => $active]);
    }

    $this->answerer = app(KnowledgeBaseAnswerer::class);
});

function kbEntry(string $questionStart): AssistantKb
{
    return AssistantKb::query()->where('question', 'like', $questionStart.'%')->firstOrFail();
}

it('répond depuis la base sur un mot-clé et incrémente hits_count, sans rien enregistrer pour un·e visiteur·se', function (): void {
    $entry = kbEntry('Comment porter plainte');

    $answer = $this->answerer->answer('Comment porter plainte ?');

    expect($answer)->toBeInstanceOf(Answer::class)
        ->and($answer->kind)->toBe(Answer::KIND_KB)
        ->and($answer->isFromKnowledgeBase())->toBeTrue()
        ->and($answer->emergency)->toBeFalse()
        ->and($answer->matchedKbId)->toBe($entry->id)
        ->and($answer->text)->toBe($entry->answer)
        ->and($answer->suggestions)->not->toContain($entry->question)
        ->and($answer->conversationId)->toBeNull()
        ->and($entry->refresh()->hits_count)->toBe(1)
        ->and(AssistantConversation::query()->count())->toBe(0)
        ->and(AssistantMessage::query()->count())->toBe(0);
});

it('ignore la casse, les accents et la ponctuation, et apparie par similarité de question sans mot-clé exact', function (): void {
    expect($this->answerer->answer('DÉPISTAGE du vih ???')->matchedKbId)->toBe(kbEntry('Dépistage VIH')->id)
        ->and($this->answerer->answer("c'est quoi le consentement")->matchedKbId)->toBe(kbEntry('Qu’est-ce que le consentement')->id)
        ->and($this->answerer->answer('je veux savoir comment je peux porter une plainte contre lui')->matchedKbId)->toBe(kbEntry('Comment porter plainte')->id)
        ->and($this->answerer->answer('on me fait du chantage avec mes photos')->matchedKbId)->toBe(kbEntry('Chantage à l’outing')->id);
});

it('ne répond pas depuis une entrée désactivée', function (): void {
    kbEntry('Contraception')->update(['is_active' => false]);

    $answer = $this->answerer->answer('contraception');

    expect($answer->kind)->toBe(Answer::KIND_FALLBACK)->and($answer->matchedKbId)->toBeNull();
});

it('fait passer les garde-fous de crise avant la base : numéros d’urgence, SOS, signalement, escalade humaine', function (): void {
    $agression = kbEntry('Que faire après une agression');

    $answer = $this->answerer->answer('Mon mari me frappe et dit qu’il va me tuer, que faire ?');

    expect($answer->kind)->toBe(Answer::KIND_CRISIS)
        ->and($answer->isCrisis())->toBeTrue()
        ->and($answer->emergency)->toBeTrue()
        ->and($answer->matchedKbId)->toBeNull()
        ->and($answer->text)->toContain('Police secours : 117')
        ->toContain('Pompiers / secours : 118')
        ->toContain('Ligne d’écoute Xomiton : +229 01 97 38 88 47')
        ->toContain(url('/sos'))
        ->toContain(url('/signaler'))
        ->toContain('Une personne formée peut te répondre')
        ->and($answer->suggestions)->toHaveCount(3);

    // Le mot-clé « viol » d'une entrée de la base ne l'emporte pas sur la réponse de crise, et rien n'est compté.
    $afterRape = $this->answerer->answer('il m’a violée hier soir');

    expect($afterRape->kind)->toBe(Answer::KIND_CRISIS)
        ->and($afterRape->suggestions[0])->toBe($agression->question)
        ->and($agression->refresh()->hits_count)->toBe(0);
});

it('ne confond pas un mot de crise avec un mot plus long et détecte le mot-clé fautif', function (): void {
    expect(KnowledgeBaseAnswerer::detectCrisis('Qu’est-ce que les violences basées sur le genre ?'))->toBeNull()
        ->and(KnowledgeBaseAnswerer::detectCrisis('Je suis enfermée depuis hier'))->toBe('enfermée')
        ->and(KnowledgeBaseAnswerer::detectCrisis('J’ai envie de MOURIR.'))->toBe('mourir')
        ->and(KnowledgeBaseAnswerer::detectCrisis('comment porter plainte'))->toBeNull()
        ->and($this->answerer->answer('Qu’est-ce que les violences basées sur le genre ?')->kind)->not->toBe(Answer::KIND_CRISIS);
});

it('prend les numéros d’urgence dans les paramètres quand ils y sont renseignés', function (): void {
    Settings::set('emergency_numbers', [['label' => 'Ligne verte', 'number' => '138'], ['label' => '', 'number' => '555']]);

    $answer = $this->answerer->answer('je vais me tuer');

    // L'entrée sans libellé est ignorée ; les numéros de la config ne s'ajoutent pas à ceux des paramètres.
    expect($answer->text)->toContain('• Ligne verte : 138')->not->toContain('555')->not->toContain('117');
});

it('replie avec bienveillance et trois questions proches quand rien ne correspond', function (): void {
    kbEntry('Comment porter plainte')->update(['hits_count' => 9]);
    kbEntry('Dépistage VIH')->update(['hits_count' => 5]);

    $answer = $this->answerer->answer('bonjour, tu vas bien ?');

    expect($answer->kind)->toBe(Answer::KIND_FALLBACK)
        ->and($answer->isFallback())->toBeTrue()
        ->and($answer->emergency)->toBeFalse()
        ->and($answer->matchedKbId)->toBeNull()
        ->and($answer->suggestions)->toHaveCount(3)
        ->and($answer->suggestions[0])->toBe('Comment porter plainte ?')
        ->and($answer->suggestions[1])->toBe('Dépistage VIH / IST')
        ->and($answer->text)->toContain('Je n’ai pas trouvé de réponse précise')
        ->toContain('• Comment porter plainte ?')
        ->toContain('utilise le bouton SOS')
        ->and(AssistantKb::query()->sum('hits_count'))->toBe(14);

    // Une question approchante sans atteindre le seuil propose d'abord l'entrée la plus proche.
    config(['xomiton.assistant.match_threshold' => 0.99]);
    $close = $this->answerer->answer('ma pilule du lendemain');

    expect($close->kind)->toBe(Answer::KIND_FALLBACK)->and($close->suggestions[0])->toBe('Contraception d’urgence');
});

it('n’écrit rien pour un·e visiteur·se, même si la persistance est demandée', function (): void {
    $this->answerer->answer('comment porter plainte ?', null, persist: true);
    $this->answerer->answer('il va me tuer', null, persist: true);

    expect(AssistantConversation::query()->count())->toBe(0)
        ->and(AssistantMessage::query()->count())->toBe(0);
});

it('n’enregistre rien pour un·e membre sans demande explicite', function (): void {
    $user = User::factory()->create();

    $answer = $this->answerer->answer('comment porter plainte ?', $user);

    expect($answer->conversationId)->toBeNull()
        ->and(AssistantConversation::query()->count())->toBe(0);
});

it('enregistre l’échange chiffré d’un·e membre sur demande et poursuit la conversation', function (): void {
    $user = User::factory()->create();
    $entry = kbEntry('Comment porter plainte');

    $first = $this->answerer->answer('Comment porter plainte ?', $user, persist: true);

    expect($first->conversationId)->not->toBeNull();

    $conversation = AssistantConversation::query()->findOrFail($first->conversationId);
    $messages = $conversation->messages()->get();

    expect($conversation->user_id)->toBe($user->id)
        ->and($conversation->is_flagged)->toBeFalse()
        ->and($messages)->toHaveCount(2)
        ->and($messages[0]->role)->toBe(AssistantMessage::ROLE_USER)
        ->and($messages[0]->body)->toBe('Comment porter plainte ?')
        ->and($messages[0]->matched_kb_id)->toBeNull()
        ->and($messages[1]->role)->toBe(AssistantMessage::ROLE_ASSISTANT)
        ->and($messages[1]->body)->toBe($entry->answer)
        ->and($messages[1]->matched_kb_id)->toBe($entry->id);

    // Corps chiffrés au repos : la colonne brute ne contient ni la question ni la réponse.
    $raw = DB::table('assistant_messages')->orderBy('id')->pluck('body')->all();

    expect($raw[0])->not->toContain('porter plainte')
        ->and($raw[1])->not->toContain('loi n°2011-26')
        ->and(decrypt($raw[0], false))->toBe('Comment porter plainte ?');

    // Suite de la conversation : une réponse de crise marque la conversation pour relecture humaine.
    $second = $this->answerer->answer('il me frappe tous les soirs', $user, persist: true, conversation: $conversation);

    expect($second->conversationId)->toBe($conversation->id)
        ->and($conversation->messages()->count())->toBe(4)
        ->and(AssistantConversation::query()->count())->toBe(1)
        ->and($conversation->refresh()->is_flagged)->toBeTrue()
        ->and($conversation->flag_reason)->toBe('Mots de crise détectés : frappe')
        ->and($conversation->escalated_at)->toBeNull();
});

it('refuse d’écrire dans la conversation d’une autre personne', function (): void {
    $user = User::factory()->create();
    $other = AssistantConversation::factory()->create();

    expect(fn () => $this->answerer->answer('bonjour', $user, persist: true, conversation: $other))
        ->toThrow(InvalidArgumentException::class);
});

it('se sérialise pour les réponses JSON', function (): void {
    $answer = $this->answerer->answer('consentement');

    expect($answer->toArray())->toMatchArray([
        'kind' => 'kb',
        'matched_kb_id' => kbEntry('Qu’est-ce que le consentement')->id,
        'emergency' => false,
        'conversation_id' => null,
    ])->toHaveKeys(['text', 'suggestions']);
});
