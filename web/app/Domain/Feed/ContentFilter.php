<?php

declare(strict_types=1);

namespace App\Domain\Feed;

use App\Domain\Feed\ValueObjects\ContentVerdict;
use App\Enums\BadWordSeverity;
use Illuminate\Support\Str;

/**
 * Filtre de mots (P7-01, FR-SOC-06, table `bad_words`) : comparaison insensible à la casse et aux accents, sur mots
 * entiers (« tarée » ne déclenche pas sur « notarée »), espaces multiples ramenés à un seul pour les expressions.
 * Pur : la liste des termes actifs est fournie par App\Application\Feed\BadWordFilter (cache), ce qui rend la règle
 * testable sans base.
 */
final class ContentFilter
{
    /**
     * @param  iterable<array{word: string, severity: BadWordSeverity}>  $terms
     */
    public function __construct(private readonly iterable $terms) {}

    public function check(string ...$texts): ContentVerdict
    {
        $haystack = self::normalize(implode("\n", $texts));

        if ($haystack === '') {
            return ContentVerdict::clean();
        }

        $blocking = [];
        $flagging = [];

        foreach ($this->terms as $term) {
            if (! self::matches($haystack, self::normalize($term['word']))) {
                continue;
            }

            if ($term['severity']->blocks()) {
                $blocking[] = $term['word'];
            } else {
                $flagging[] = $term['word'];
            }
        }

        return new ContentVerdict(array_values(array_unique($blocking)), array_values(array_unique($flagging)));
    }

    /**
     * Le terme apparaît-il dans le texte, en mot entier ? (aussi utilisé par App\Models\BadWord::appearsIn).
     */
    public static function contains(string $text, string $word): bool
    {
        return self::matches(self::normalize($text), self::normalize($word));
    }

    private static function matches(string $normalizedText, string $normalizedWord): bool
    {
        if ($normalizedWord === '' || $normalizedText === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normalizedWord, '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $normalizedText) === 1;
    }

    private static function normalize(string $text): string
    {
        $ascii = Str::lower(Str::ascii($text));

        return trim((string) preg_replace('/\s+/u', ' ', $ascii));
    }
}
