<?php

declare(strict_types=1);

namespace App\Infrastructure\Assistant;

use App\Application\Assistant\AssistantProvider;
use App\Application\Assistant\AssistantQuery;
use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

/**
 * Pilote `gemini` (Q7) : API Google Gemini, branché mais DÉSACTIVÉ par défaut (`xomiton.assistant.llm_enabled`,
 * ASSISTANT_LLM_ENABLED=false ; activation en production subordonnée à l'avis juridique, DECISIONS_A_VALIDER.md).
 *
 * Garde-fous, dans cet ordre :
 *  1. retrieval-first : la base de connaissances répond d'abord ; Gemini n'est appelé qu'en repli (réponse « pas trouvé ») ;
 *  2. crise / violence / SOS (mots-clés) : réponse locale d'urgence de la base, jamais d'appel (défense en profondeur,
 *     AskAssistant l'écarte déjà en amont) ;
 *  3. drapeau désactivé, clé absente ou limite de débit par membre atteinte : réponse de la base, aucun appel ;
 *  4. minimisation : PiiMasker retire contacts, positions et noms ; aucun identifiant du compte n'est envoyé ;
 *  5. consigne système en tu, bienveillante, sans conseil médical / juridique définitif, rappel du bouton Signaler / SOS ;
 *  6. délai court (`gemini.timeout_seconds`) ; toute erreur, blocage de sécurité ou réponse vide → réponse « pas trouvé » ;
 *  7. journal sans contenu : issue, durée, longueurs — jamais la question, la réponse ni l'identité de la personne.
 * L'échange est enregistré (si `persist`) en dernier, comme l'exige le contrat AssistantProvider.
 */
final class GeminiAssistantProvider implements AssistantProvider
{
    public const NAME = 'gemini';

    public const SYSTEM_PROMPT = <<<'TXT'
Tu es l'assistante de Xomiton, une plateforme d'entraide pour les filles, les femmes et les personnes LBTQ+ au Bénin.
Réponds en français, en tutoyant, avec bienveillance et sans jugement, en 4 phrases au plus et sans mise en forme.
Tu ne donnes jamais de conseil médical ou juridique définitif : tu orientes vers une personne ou un service qualifié.
Si la personne parle de danger, de violence ou de détresse, rappelle-lui le bouton Signaler ou SOS de l'application, et l'aide de Xomiton.
Ne demande aucune information personnelle (nom, numéro, adresse, lieu) et ignore celles que tu verrais ; ne cherche pas à identifier la personne.
Si tu ne sais pas, dis-le simplement et propose de reformuler. Ne prétends pas être un humain.
TXT;

    public function __construct(private readonly KnowledgeBaseAnswerer $answerer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function answer(AssistantQuery $query): Answer
    {
        $local = $this->answerer->answer($query->question, $query->user, false);

        // 1. La base répond (ou c'est une réponse de crise) : jamais d'appel.
        if (! $local->isFallback() || KnowledgeBaseAnswerer::detectCrisis($query->question) !== null) {
            return $this->finish($query, $local);
        }

        if (($skipped = $this->skipReason($query)) !== null) {
            return $this->finish($query, $local, $skipped);
        }

        $started = microtime(true);
        $text = null;
        $outcome = 'error';

        try {
            $names = array_values(array_filter([(string) ($query->user->name ?? ''), (string) ($query->user->pseudo ?? '')]));
            $text = $this->generate($query->question, $names);
            $outcome = $text === null ? 'empty' : 'ok';
        } catch (Throwable $e) {
            $outcome = 'error:'.class_basename($e);
        }

        Log::info('assistant.llm', [
            'provider' => self::NAME,
            'outcome' => $outcome,
            'ms' => (int) round((microtime(true) - $started) * 1000),
            'question_length' => mb_strlen($query->question),
            'answer_length' => $text === null ? 0 : mb_strlen($text),
        ]);

        if ($text === null) {
            return $this->finish($query, $local);
        }

        return $this->finish($query, new Answer(text: $text, kind: Answer::KIND_KB, suggestions: $local->suggestions));
    }

    /** Raison pour laquelle Gemini n'est pas appelé, ou null s'il peut l'être. */
    private function skipReason(AssistantQuery $query): ?string
    {
        if (! (bool) config('xomiton.assistant.llm_enabled', false)) {
            return 'disabled';
        }

        $key = config('xomiton.assistant.gemini.api_key');

        if (! is_string($key) || $key === '') {
            return 'no_key';
        }

        $limit = (int) config('xomiton.assistant.gemini.per_hour', 10);
        $bucket = 'assistant-llm:'.$query->user->getKey();

        if ($limit < 1 || ! RateLimiter::attempt($bucket, $limit, static fn (): bool => true, 3600)) {
            return 'rate_limited';
        }

        return null;
    }

    /**
     * Appel de l'API (sans contrôle du drapeau : réservé à answer() et à la commande assistant:test-llm, qui le vérifient).
     *
     * @param  list<string>  $knownNames  noms de la personne, retirés avec le reste des données personnelles
     */
    public function generate(string $question, array $knownNames = []): ?string
    {
        $config = (array) config('xomiton.assistant.gemini');
        $question = PiiMasker::mask($question, $knownNames);

        $response = Http::withHeaders(['x-goog-api-key' => (string) $config['api_key']])
            ->timeout((float) $config['timeout_seconds'])
            ->connectTimeout(min(2.0, (float) $config['timeout_seconds']))
            ->acceptJson()
            ->asJson()
            ->post(sprintf('%s/models/%s:generateContent', rtrim((string) $config['endpoint'], '/'), rawurlencode((string) $config['model'])), [
                'systemInstruction' => ['parts' => [['text' => self::SYSTEM_PROMPT]]],
                'contents' => [['role' => 'user', 'parts' => [['text' => $question]]]],
                'generationConfig' => ['maxOutputTokens' => (int) $config['max_output_tokens'], 'temperature' => 0.4],
            ]);

        if (! $response->successful() || $response->json('promptFeedback.blockReason') !== null) {
            return null;
        }

        $candidate = $response->json('candidates.0');

        if (! is_array($candidate) || ! in_array($candidate['finishReason'] ?? 'STOP', ['STOP', 'MAX_TOKENS'], true)) {
            return null;
        }

        $text = '';

        foreach ((array) ($candidate['content']['parts'] ?? []) as $part) {
            $text .= (string) ($part['text'] ?? '');
        }

        $text = trim(strip_tags($text));

        return $text === '' ? null : mb_substr($text, 0, (int) $config['max_answer_chars']);
    }

    /** Enregistrement de l'échange (si demandé) puis retour : dernière étape, rien ne doit échouer après. */
    private function finish(AssistantQuery $query, Answer $answer, ?string $skipped = null): Answer
    {
        if ($skipped !== null) {
            Log::info('assistant.llm', ['provider' => self::NAME, 'outcome' => 'skipped:'.$skipped]);
        }

        return $query->persist ? $this->answerer->record($query->user, $query->conversation, $query->question, $answer) : $answer;
    }
}
