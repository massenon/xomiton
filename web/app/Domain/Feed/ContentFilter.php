<?php

declare(strict_types=1);

namespace App\Domain\Feed;

use App\Domain\Feed\ValueObjects\ContentVerdict;
use App\Enums\BadWordSeverity;
use Illuminate\Support\Str;

/**
 * Filtre de mots (P7-01, FR-SOC-06, D-IOS-9 ; table `bad_words`) : comparaison insensible à la casse, aux accents et à la
 * ponctuation de contournement, sur mots entiers (« tarée » ne déclenche pas sur « notarée »), espaces multiples ramenés à
 * un seul pour les expressions.
 *
 * Contournements simples repérés : lettres séparées (« i.n.s.u.l.t.e », « i n s u l t e », « i-n-s-u-l-t-e »), lettres
 * répétées (« insuuulte »), chiffres et symboles de substitution (« 1nsulte », « @ »). Liste blanche : les expressions
 * légitimes (faux positifs) sont retirées du texte avant la recherche.
 *
 * Pur : la liste des termes actifs et la liste blanche sont fournies par App\Application\Feed\BadWordFilter (cache), ce qui
 * rend la règle testable sans base.
 */
final class ContentFilter
{
    private const LEET = ['0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '!' => 'i'];

    /**
     * @param  iterable<array{word: string, severity: BadWordSeverity, category?: string}>  $terms
     * @param  list<string>  $allowed  expressions légitimes retirées du texte
     */
    public function __construct(
        private readonly iterable $terms,
        private readonly array $allowed = [],
        private readonly int $minSpacedLetters = 3,
    ) {}

    public function check(string ...$texts): ContentVerdict
    {
        $variants = $this->variants(implode("\n", $texts));

        if ($variants === []) {
            return ContentVerdict::clean();
        }

        $blocking = $flagging = $hiding = $categories = [];

        foreach ($this->terms as $term) {
            $word = self::normalize($term['word']);

            if (! self::matchesAny($variants, $word)) {
                continue;
            }

            $categories[] = (string) ($term['category'] ?? 'autre');

            match ($term['severity']) {
                BadWordSeverity::Block => $blocking[] = $term['word'],
                BadWordSeverity::Hide => $hiding[] = $term['word'],
                BadWordSeverity::Flag => $flagging[] = $term['word'],
            };
        }

        return new ContentVerdict(
            array_values(array_unique($blocking)),
            array_values(array_unique($flagging)),
            array_values(array_unique($hiding)),
            array_values(array_unique($categories)),
        );
    }

    /**
     * Le terme apparaît-il dans le texte, en mot entier ? (aussi utilisé par App\Models\BadWord::appearsIn).
     */
    public static function contains(string $text, string $word): bool
    {
        $normalizedText = self::normalize($text);

        return self::matches($normalizedText, self::normalize($word))
            || self::matches(self::squeeze($normalizedText), self::squeeze(self::normalize($word)));
    }

    /**
     * Textes comparables : tel quel, lettres séparées recollées, substitutions de chiffres défaites ; la liste blanche
     * est appliquée d'abord.
     *
     * @return list<string>
     */
    private function variants(string $text): array
    {
        $base = self::normalize($text);

        if ($base === '') {
            return [];
        }

        foreach ($this->allowed as $phrase) {
            $phrase = self::normalize($phrase);

            if ($phrase !== '') {
                $base = (string) preg_replace('/(?<![\p{L}\p{N}])'.preg_quote($phrase, '/').'(?![\p{L}\p{N}])/u', ' ', $base);
            }
        }

        $joined = $this->joinSpacedLetters($base);
        $variants = [$base, $joined, strtr($joined, self::LEET)];

        return array_values(array_unique($variants));
    }

    /** « i.n.s.u.l.t.e » et « i n s u l t e » deviennent « insulte » (au moins `minSpacedLetters` lettres isolées). */
    private function joinSpacedLetters(string $text): string
    {
        $count = max(2, $this->minSpacedLetters) - 1;

        return (string) preg_replace_callback(
            '/(?<![\p{L}\p{N}])\p{L}(?:[\s.\-_*]\p{L}){'.$count.',}(?![\p{L}\p{N}])/u',
            static fn (array $m): string => (string) preg_replace('/[\s.\-_*]/u', '', $m[0]),
            $text,
        );
    }

    /**
     * @param  list<string>  $variants
     */
    private static function matchesAny(array $variants, string $word): bool
    {
        if ($word === '') {
            return false;
        }

        $squeezedWord = self::squeeze($word);

        foreach ($variants as $variant) {
            if (self::matches($variant, $word) || self::matches(self::squeeze($variant), $squeezedWord)) {
                return true;
            }
        }

        return false;
    }

    private static function matches(string $normalizedText, string $normalizedWord): bool
    {
        if ($normalizedWord === '' || $normalizedText === '') {
            return false;
        }

        $pattern = '/(?<![\p{L}\p{N}])'.preg_quote($normalizedWord, '/').'(?![\p{L}\p{N}])/u';

        return preg_match($pattern, $normalizedText) === 1;
    }

    /** Lettres répétées ramenées à une (« insuuulte » → « insulte »). */
    private static function squeeze(string $text): string
    {
        return (string) preg_replace('/(\p{L})\1+/u', '$1', $text);
    }

    private static function normalize(string $text): string
    {
        $ascii = Str::lower(Str::ascii(str_replace(['’', '‘', 'ʼ'], "'", $text)));

        return trim((string) preg_replace('/\s+/u', ' ', $ascii));
    }
}
