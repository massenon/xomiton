<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Domain\Feed\ContentFilter;
use App\Domain\Feed\ContentPatterns;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Domain\Feed\ValueObjects\ContentVerdict;
use App\Enums\BadWordSeverity;
use App\Models\BadWord;
use App\Models\ContentFilterAllowedPhrase;
use App\Support\Audit;
use Illuminate\Support\Facades\Cache;

/**
 * Filtre minimal de contenus appliqué côté serveur avant toute publication, tout commentaire, message de groupe, texte de
 * forum, description de page ou de groupe et tout pseudonyme (P7-01, FR-SOC-06, D-IOS-9, règle Apple 1.2). Trois sources :
 *  - les MOTS actifs de `bad_words` (gardés en cache, `xomiton.feed.bad_words_cache_seconds`, oubliés à chaque écriture de
 *    App\Models\BadWord), de gravité « bloquer », « masquer » ou « signaler » et de catégorie (insulte, haine, violence) ;
 *  - les MOTIFS de structure (App\Domain\Feed\ContentPatterns : coordonnées personnelles dans l'espace public, liens
 *    suspects), réglés par `xomiton.moderation.content_filter.patterns` ;
 *  - la LISTE BLANCHE `content_filter_allowlist` (faux positifs).
 * « Bloquer » refuse le texte (422 `content_rejected`, message neutre et bienveillant, jamais le terme) ; « masquer » le
 * publie puis le masque et ouvre un signalement automatique ; « signaler » le laisse passer et ouvre un signalement
 * automatique (App\Application\Moderation\ReportContent::automatic).
 * JAMAIS appelé pour un message privé 1-à-1 (confidentialité : aucune lecture) ni pour un signalement, un SOS ou un besoin.
 * Journal d'audit `content_filter.rejected` / `content_filter.reviewed` : surface, champ et catégories seulement, sans contenu.
 */
final class BadWordFilter
{
    /**
     * Contrôle chaque champ séparément pour désigner le bon champ dans l'erreur 422.
     *
     * @param  array<string, string|null>  $fields  champ => texte
     * @param  string  $surface  `post`, `comment`, `forum_topic`, `forum_reply`, `group_message`, `page`, `group`, `event`, `pseudo`
     *
     * @throws ContentBlocked au premier champ qui contient un terme ou un motif bloquant
     */
    public function assertAllowed(array $fields, string $surface = 'post'): ContentVerdict
    {
        if (! (bool) config('xomiton.moderation.content_filter.enabled', true)) {
            return ContentVerdict::clean();
        }

        $filter = new ContentFilter($this->terms(), $this->allowedPhrases(), (int) config('xomiton.moderation.content_filter.min_spaced_letters', 3));
        $verdict = ContentVerdict::clean();

        foreach ($fields as $field => $text) {
            if ($text === null || trim($text) === '') {
                continue;
            }

            $fieldVerdict = $this->withPatterns($filter->check($text), $text, $surface);

            if ($fieldVerdict->isBlocked()) {
                $this->trace('content_filter.rejected', $surface, (string) $field, $fieldVerdict);

                throw new ContentBlocked($field);
            }

            $verdict = $verdict->merge($fieldVerdict);
        }

        if (! $verdict->isClean()) {
            $this->trace('content_filter.reviewed', $surface, implode(',', array_keys($fields)), $verdict);
        }

        return $verdict;
    }

    /**
     * Simulation pour l'écran d'administration : mêmes règles, aucune écriture, aucune exception.
     *
     * @return array{outcome: string, categories: list<string>}
     */
    public function test(string $text, string $surface = 'post'): array
    {
        $filter = new ContentFilter($this->terms(), $this->allowedPhrases(), (int) config('xomiton.moderation.content_filter.min_spaced_letters', 3));
        $verdict = $this->withPatterns($filter->check($text), $text, $surface);

        return [
            'outcome' => match (true) {
                $verdict->isBlocked() => 'blocked',
                $verdict->isHidden() => 'hidden',
                $verdict->isFlagged() => 'flagged',
                default => 'clean',
            },
            'categories' => $verdict->categories,
        ];
    }

    /**
     * @return list<array{word: string, severity: BadWordSeverity, category: string}>
     */
    public function terms(): array
    {
        /** @var list<array{word: string, severity: string, category: string}> $rows */
        $rows = Cache::remember(
            BadWord::CACHE_KEY,
            max(1, (int) config('xomiton.feed.bad_words_cache_seconds', 600)),
            static fn (): array => BadWord::query()->active()->orderBy('id')->get(['word', 'severity', 'category'])
                ->map(static fn (BadWord $word): array => ['word' => (string) $word->word, 'severity' => $word->severity->value, 'category' => (string) $word->category])
                ->values()->all(),
        );

        $terms = [];

        foreach ($rows as $row) {
            $severity = BadWordSeverity::tryFrom((string) $row['severity']);

            if ($severity !== null) {
                $terms[] = ['word' => (string) $row['word'], 'severity' => $severity, 'category' => (string) ($row['category'] ?? 'autre')];
            }
        }

        return $terms;
    }

    /**
     * @return list<string>
     */
    public function allowedPhrases(): array
    {
        /** @var list<string> $phrases */
        $phrases = Cache::remember(
            ContentFilterAllowedPhrase::CACHE_KEY,
            max(1, (int) config('xomiton.feed.bad_words_cache_seconds', 600)),
            static fn (): array => ContentFilterAllowedPhrase::query()->orderBy('id')->pluck('phrase')->map(static fn ($p): string => (string) $p)->all(),
        );

        return $phrases;
    }

    /** Ajoute au verdict des mots celui des motifs de structure, selon l'action configurée de chaque catégorie. */
    private function withPatterns(ContentVerdict $verdict, string $text, string $surface): ContentVerdict
    {
        $config = (array) config('xomiton.moderation.content_filter');
        $actions = (array) ($config['patterns'] ?? []);
        $categories = [];

        foreach ([ContentPatterns::CONTACT, ContentPatterns::LINK] as $category) {
            $action = (string) ($actions[$category] ?? 'off');

            if ($action === 'off') {
                continue;
            }

            if ($category === ContentPatterns::CONTACT && ! in_array($surface, (array) ($config['personal_data_surfaces'] ?? []), true)) {
                continue;
            }

            $categories[] = $category;
        }

        if ($categories === []) {
            return $verdict;
        }

        $patterns = new ContentPatterns(
            (array) ($config['link_allowed_hosts'] ?? []),
            (array) ($config['link_suspicious_hosts'] ?? []),
            (array) ($config['link_suspicious_tlds'] ?? []),
        );

        $blocking = $flagging = $hiding = $hit = [];

        foreach ($patterns->detect($text, $categories) as $category) {
            $hit[] = $category;

            match ((string) ($actions[$category] ?? 'off')) {
                'block' => $blocking[] = '#'.$category,
                'hide' => $hiding[] = '#'.$category,
                default => $flagging[] = '#'.$category,
            };
        }

        return $verdict->merge(new ContentVerdict($blocking, $flagging, $hiding, $hit));
    }

    private function trace(string $action, string $surface, string $field, ContentVerdict $verdict): void
    {
        Audit::log($action, null, sprintf('surface=%s ; champ=%s ; categories=%s', $surface, $field, implode(',', $verdict->categories) ?: 'autre'));
    }
}
