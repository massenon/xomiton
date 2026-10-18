<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Domain\Feed\ContentFilter;
use App\Domain\Feed\Exceptions\ContentBlocked;
use App\Domain\Feed\ValueObjects\ContentVerdict;
use App\Enums\BadWordSeverity;
use App\Models\BadWord;
use Illuminate\Support\Facades\Cache;

/**
 * Filtre de mots appliqué côté serveur avant toute publication ou tout commentaire (P7-01, FR-SOC-06) : la liste active
 * de `bad_words` est gardée en cache (cache file sur cPanel, `xomiton.feed.bad_words_cache_seconds`, oubliée à chaque
 * écriture par App\Models\BadWord) ; « bloquer » refuse le texte (422 `content_blocked`, message neutre), « signaler » le
 * laisse passer et ouvre un signalement automatique (App\Application\Moderation\ReportContent::automatic).
 */
final class BadWordFilter
{
    /**
     * Contrôle chaque champ séparément pour désigner le bon champ dans l'erreur 422.
     *
     * @param  array<string, string|null>  $fields  champ => texte
     *
     * @throws ContentBlocked au premier champ qui contient un terme bloquant
     */
    public function assertAllowed(array $fields): ContentVerdict
    {
        $filter = new ContentFilter($this->terms());
        $verdict = ContentVerdict::clean();

        foreach ($fields as $field => $text) {
            if ($text === null || trim($text) === '') {
                continue;
            }

            $fieldVerdict = $filter->check($text);

            if ($fieldVerdict->isBlocked()) {
                throw new ContentBlocked($field);
            }

            $verdict = $verdict->merge($fieldVerdict);
        }

        return $verdict;
    }

    /**
     * @return list<array{word: string, severity: BadWordSeverity}>
     */
    public function terms(): array
    {
        /** @var list<array{word: string, severity: string}> $rows */
        $rows = Cache::remember(
            BadWord::CACHE_KEY,
            max(1, (int) config('xomiton.feed.bad_words_cache_seconds', 600)),
            static fn (): array => BadWord::query()->active()->orderBy('id')->get(['word', 'severity'])
                ->map(static fn (BadWord $word): array => ['word' => (string) $word->word, 'severity' => $word->severity->value])
                ->values()->all(),
        );

        $terms = [];

        foreach ($rows as $row) {
            $severity = BadWordSeverity::tryFrom((string) $row['severity']);

            if ($severity !== null) {
                $terms[] = ['word' => (string) $row['word'], 'severity' => $severity];
            }
        }

        return $terms;
    }
}
