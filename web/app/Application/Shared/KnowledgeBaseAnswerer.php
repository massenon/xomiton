<?php

declare(strict_types=1);

namespace App\Application\Shared;

use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Assistant sans LLM (P3-09, Q41 a ; port de XM.assistant du prototype) : répond depuis `assistant_kb` par mots-clés
 * et similarité de questions, après des garde-fous de crise évalués avant toute recherche.
 *
 *   $answer = $answerer->answer('Comment porter plainte ?');                       // visiteur·se : rien n'est écrit
 *   $answer = $answerer->answer($question, $user, persist: true, conversation: $c); // membre : échange chiffré enregistré
 *
 * 1. crise : si la question contient un mot-clé de `xomiton.assistant.crisis_keywords` (mot entier, accents et casse
 *    ignorés) → réponse de crise (lang/fr/assistant.php) avec numéros d'urgence (`settings.emergency_numbers` sinon config),
 *    SOS, signalement et proposition d'escalade vers un humain ; `kind = crisis`, `emergency = true` ;
 * 2. base : chaque entrée active reçoit un score dans [0, 1] = max(score mots-clés, similarité de question) ; la meilleure
 *    répond si elle atteint `match_threshold`, et son `hits_count` est incrémenté ;
 * 3. repli bienveillant avec les 3 questions les plus proches (ou les plus consultées si rien n'approche).
 * Aucun échange n'est conservé pour un·e visiteur·se ; pour un·e membre, seulement si `$persist` est vrai
 * (`assistant_conversations` / `assistant_messages`, corps chiffré par le cast du modèle).
 */
final class KnowledgeBaseAnswerer
{
    /** Longueur maximale d'une question analysée et enregistrée (caractères). */
    public const MAX_QUESTION_LENGTH = 1000;

    /** Score d'une entrée dont un mot-clé apparaît ; +0,2 par mot-clé supplémentaire, plafonné à 1. */
    private const KEYWORD_BASE_SCORE = 0.6;

    private const KEYWORD_BONUS = 0.2;

    /** Jetons ignorés dans la similarité de questions (forme normalisée, sans accent). */
    private const STOPWORDS = [
        'le', 'la', 'les', 'un', 'une', 'des', 'du', 'de', 'et', 'ou', 'mais', 'donc', 'car', 'ni', 'au', 'aux', 'en', 'dans', 'sur',
        'sous', 'par', 'pour', 'avec', 'sans', 'chez', 'vers', 'que', 'qui', 'quoi', 'dont', 'comment', 'pourquoi', 'quand', 'est',
        'ce', 'cet', 'cette', 'ces', 'ca', 'cela', 'ceci', 'il', 'elle', 'ils', 'elles', 'on', 'nous', 'vous', 'je', 'tu', 'me', 'te',
        'se', 'moi', 'toi', 'lui', 'leur', 'mon', 'ma', 'mes', 'ton', 'ta', 'tes', 'son', 'sa', 'ses', 'notre', 'nos', 'votre', 'vos',
        'leurs', 'ne', 'pas', 'plus', 'jamais', 'rien', 'tout', 'tous', 'toute', 'toutes', 'etre', 'avoir', 'suis', 'es', 'sommes',
        'etes', 'sont', 'ai', 'as', 'avons', 'avez', 'ont', 'ete', 'fait', 'faire', 'faut', 'peut', 'peux', 'veux', 'voudrais', 'dois',
        'doit', 'si', 'oui', 'non', 'bonjour', 'bonsoir', 'salut', 'merci', 'svp', 'stp', 'quel', 'quelle', 'quels', 'quelles', 'cas',
        'est-ce', 'y', 'a', 'd', 'l', 'c', 's', 'j', 'm', 'n', 'qu', 't',
    ];

    public function answer(string $question, ?User $user = null, bool $persist = false, ?AssistantConversation $conversation = null): Answer
    {
        $question = Str::limit(trim($question), self::MAX_QUESTION_LENGTH, '');

        if ($conversation !== null && ($user === null || $conversation->user_id !== $user->getKey())) {
            throw new InvalidArgumentException('The conversation does not belong to the given user.');
        }

        $crisisKeyword = self::detectCrisis($question);
        $ranked = $this->rank($question);

        if ($crisisKeyword !== null) {
            $answer = new Answer(
                text: $this->crisisText(),
                kind: Answer::KIND_CRISIS,
                suggestions: $this->suggestions($ranked, null),
                emergency: true,
            );
        } elseif (($ranked->first()['score'] ?? 0.0) >= self::threshold()) {
            /** @var AssistantKb $kb */
            $kb = $ranked->first()['kb'];
            $kb->recordHit();

            $answer = new Answer(
                text: trim((string) $kb->answer),
                kind: Answer::KIND_KB,
                matchedKbId: (int) $kb->getKey(),
                suggestions: $this->suggestions($ranked, (int) $kb->getKey()),
            );
        } else {
            $suggestions = $this->suggestions($ranked, null);

            $answer = new Answer(
                text: $this->fallbackText($suggestions),
                kind: Answer::KIND_FALLBACK,
                suggestions: $suggestions,
            );
        }

        if ($persist && $user !== null) {
            $conversation = $this->persist($user, $conversation, $question, $answer, $crisisKeyword);

            return $answer->withConversationId((int) $conversation->getKey());
        }

        return $answer;
    }

    /**
     * Mot-clé de crise présent dans la question (mot entier, pluriel toléré), ou null. Évalué avant toute recherche.
     */
    public static function detectCrisis(string $question): ?string
    {
        $normalized = self::normalize($question);

        if ($normalized === '') {
            return null;
        }

        foreach ((array) config('xomiton.assistant.crisis_keywords', []) as $keyword) {
            if (! is_string($keyword)) {
                continue;
            }

            $needle = self::normalize($keyword);

            if ($needle !== '' && self::containsPhrase($normalized, $needle)) {
                return $keyword;
            }
        }

        return null;
    }

    /**
     * Forme comparable d'un texte : minuscules, sans accent, apostrophes et ponctuation remplacées par des espaces.
     */
    public static function normalize(string $text): string
    {
        $text = Str::lower(Str::ascii($text));
        $text = (string) preg_replace('/[^a-z0-9]+/', ' ', $text);

        return trim((string) preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Jetons significatifs d'un texte normalisé : au moins trois caractères, hors mots vides.
     *
     * @return list<string>
     */
    public static function tokens(string $normalized): array
    {
        $tokens = array_filter(
            explode(' ', $normalized),
            static fn (string $token): bool => strlen($token) >= 3 && ! in_array($token, self::STOPWORDS, true),
        );

        return array_values(array_unique($tokens));
    }

    /**
     * Score d'une entrée pour une question : max(score mots-clés, similarité des questions), dans [0, 1].
     */
    public static function score(AssistantKb $kb, string $question): float
    {
        $normalized = self::normalize($question);
        $hits = 0;

        foreach ((array) ($kb->keywords ?? []) as $keyword) {
            $needle = is_scalar($keyword) ? self::normalize((string) $keyword) : '';

            if ($needle !== '' && self::containsPhrase($normalized, $needle)) {
                $hits++;
            }
        }

        $keywordScore = $hits === 0 ? 0.0 : min(1.0, self::KEYWORD_BASE_SCORE + self::KEYWORD_BONUS * ($hits - 1));
        $similarity = self::similarity(self::tokens($normalized), self::tokens(self::normalize((string) $kb->question)));

        return round(max($keywordScore, $similarity), 3);
    }

    /**
     * Entrées actives classées par score décroissant (puis popularité), seules celles qui obtiennent un score > 0 comptent
     * comme « proches ».
     *
     * @return Collection<int, array{kb: AssistantKb, score: float}>
     */
    private function rank(string $question): Collection
    {
        return AssistantKb::query()
            ->active()
            ->get()
            ->map(static fn (AssistantKb $kb): array => ['kb' => $kb, 'score' => self::score($kb, $question)])
            ->sort(static function (array $a, array $b): int {
                return [$b['score'], (int) $b['kb']->hits_count, (int) $a['kb']->getKey()]
                    <=> [$a['score'], (int) $a['kb']->hits_count, (int) $b['kb']->getKey()];
            })
            ->values();
    }

    /**
     * Questions proches : celles qui ont un score > 0, complétées par les plus consultées, sans l'entrée qui vient de répondre.
     *
     * @param  Collection<int, array{kb: AssistantKb, score: float}>  $ranked
     * @return list<string>
     */
    private function suggestions(Collection $ranked, ?int $excludeId): array
    {
        $count = max(0, (int) config('xomiton.assistant.suggestions_count', 3));

        $candidates = $ranked->reject(static fn (array $row): bool => $excludeId !== null && (int) $row['kb']->getKey() === $excludeId);

        $close = $candidates->filter(static fn (array $row): bool => $row['score'] > 0.0);
        $popular = $candidates
            ->filter(static fn (array $row): bool => $row['score'] <= 0.0)
            ->sortByDesc(static fn (array $row): int => (int) $row['kb']->hits_count);

        return $close->concat($popular)
            ->take($count)
            ->map(static fn (array $row): string => trim((string) $row['kb']->question))
            ->values()
            ->all();
    }

    private function crisisText(): string
    {
        $lines = [(string) __('assistant.crisis.intro')];

        foreach ($this->emergencyNumbers() as $entry) {
            $lines[] = (string) __('assistant.crisis.number', ['label' => $entry['label'], 'number' => $entry['number']]);
        }

        $lines[] = '';
        $lines[] = (string) __('assistant.crisis.sos', ['url' => self::url('sos', '/sos')]);
        $lines[] = (string) __('assistant.crisis.report', ['url' => self::url('signaler', '/signaler')]);
        $lines[] = '';
        $lines[] = (string) __('assistant.crisis.human');

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $suggestions
     */
    private function fallbackText(array $suggestions): string
    {
        $lines = [(string) __('assistant.fallback.intro')];

        if ($suggestions !== []) {
            $lines[] = (string) __('assistant.fallback.suggestions');

            foreach ($suggestions as $suggestion) {
                $lines[] = (string) __('assistant.fallback.suggestion', ['question' => $suggestion]);
            }
        }

        $lines[] = (string) __('assistant.fallback.rephrase');
        $lines[] = (string) __('assistant.fallback.disclaimer');

        return implode("\n", $lines);
    }

    /**
     * Numéros d'urgence : paramètre `emergency_numbers` (settings) s'il est renseigné et bien formé, sinon config.
     *
     * @return list<array{label: string, number: string}>
     */
    public function emergencyNumbers(): array
    {
        $fromSettings = self::wellFormedNumbers(Settings::array('emergency_numbers', []));

        if ($fromSettings !== []) {
            return $fromSettings;
        }

        return self::wellFormedNumbers((array) config('xomiton.assistant.emergency_numbers', []));
    }

    /**
     * Enregistre l'échange d'un·e membre : conversation existante ou nouvelle, message de la personne puis réponse
     * (corps chiffrés par le cast `encrypted`) ; une réponse de crise marque la conversation pour relecture humaine.
     */
    private function persist(User $user, ?AssistantConversation $conversation, string $question, Answer $answer, ?string $crisisKeyword): AssistantConversation
    {
        return DB::transaction(function () use ($user, $conversation, $question, $answer, $crisisKeyword): AssistantConversation {
            $conversation ??= AssistantConversation::query()->create(['user_id' => $user->getKey()]);

            $conversation->messages()->create([
                'role' => AssistantMessage::ROLE_USER,
                'body' => $question,
            ]);

            $conversation->messages()->create([
                'role' => AssistantMessage::ROLE_ASSISTANT,
                'body' => $answer->text,
                'matched_kb_id' => $answer->matchedKbId,
            ]);

            if ($answer->emergency) {
                $conversation->flag((string) __('assistant.crisis.flag_reason', ['keyword' => (string) $crisisKeyword]));
            }

            return $conversation;
        });
    }

    private static function threshold(): float
    {
        return max(0.0, min(1.0, (float) config('xomiton.assistant.match_threshold', 0.5)));
    }

    /**
     * Le groupe de mots apparaît-il en entier (pluriel en -s / -x toléré) dans le texte normalisé ?
     */
    private static function containsPhrase(string $haystack, string $needle): bool
    {
        return preg_match('/(?<![a-z0-9])'.preg_quote($needle, '/').'(?:s|x)?(?![a-z0-9])/', $haystack) === 1;
    }

    /**
     * Coefficient de Dice entre deux listes de jetons, un jeton en appariant un autre s'ils sont égaux ou si l'un
     * (≥ 4 caractères) est le début de l'autre (« agression » ~ « agressions », « depist » ~ « depistage »).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function similarity(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $matched = 0;

        foreach ($b as $tokenB) {
            foreach ($a as $tokenA) {
                if (self::tokensMatch($tokenA, $tokenB)) {
                    $matched++;

                    break;
                }
            }
        }

        return min(1.0, (2 * $matched) / (count($a) + count($b)));
    }

    private static function tokensMatch(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (min(strlen($a), strlen($b)) < 4) {
            return false;
        }

        return str_starts_with($a, $b) || str_starts_with($b, $a);
    }

    /**
     * @param  array<mixed>  $entries
     * @return list<array{label: string, number: string}>
     */
    private static function wellFormedNumbers(array $entries): array
    {
        $numbers = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $label = trim((string) ($entry['label'] ?? ''));
            $number = trim((string) ($entry['number'] ?? ''));

            if ($label !== '' && $number !== '') {
                $numbers[] = ['label' => $label, 'number' => $number];
            }
        }

        return $numbers;
    }

    private static function url(string $routeName, string $fallbackPath): string
    {
        return Route::has($routeName) ? route($routeName) : url($fallbackPath);
    }
}
