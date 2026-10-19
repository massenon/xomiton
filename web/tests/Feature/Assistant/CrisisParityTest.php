<?php

declare(strict_types=1);

use App\Application\Shared\KnowledgeBaseAnswerer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\AlertFixtures;

/*
 * Parité des garde-fous de crise (P8-11) : la page /assistant (resources/js/assistant/crisis.ts, avant tout appel réseau)
 * et le serveur (KnowledgeBaseAnswerer::detectCrisis) doivent trouver la même crise dans le même message. Ce test
 * calcule, pour chaque mot-clé de `xomiton.assistant.crisis_keywords`, des variantes (casse, accents retirés, pluriel,
 * ponctuation, apostrophe typographique, mot collé) et le mot-clé que le serveur y trouve, avec les mots-clés servis à
 * la page ; le résultat est versionné dans resources/js/assistant/crisis.parity.json, que crisis.test.ts (Vitest) rejoue
 * avec detectCrisis. Mots-clés modifiés sans régénérer le fichier : échec ici ; `XM_UPDATE_PARITY=1` le réécrit.
 */

const CRISIS_PARITY_FIXTURE = 'resources/js/assistant/crisis.parity.json';

/**
 * @return list<string>
 */
function crisisParityVariants(string $keyword): array
{
    $plain = Str::ascii($keyword);

    return [
        $keyword,
        Str::upper($keyword),
        $plain,
        "Aide-moi, {$keyword}s\u{00A0}!",
        "« {$keyword} »… vite",
        "J’ai peur\u{00A0}: {$keyword}.",
        "{$keyword}zz",
        "x{$keyword}",
    ];
}

/**
 * @param  list<string>  $keywords
 * @return array{keywords: list<string>, samples: list<array{text: string, match: string|null}>}
 */
function crisisParityFixture(array $keywords): array
{
    $texts = [
        'Comment porter plainte ?',
        'les violences conjugales',
        'Dépistage VIH / IST',
        'un virage dangereux',
        'Ma sœur est SÉQUESTRÉE chez lui',
        'il m’a frappée hier',
        'J’ai été violée',
        'je veux en finir',
        '',
    ];

    foreach ((array) config('xomiton.assistant.crisis_keywords', []) as $keyword) {
        if (is_string($keyword)) {
            array_push($texts, ...crisisParityVariants($keyword));
        }
    }

    $samples = [];

    foreach (array_values(array_unique($texts)) as $text) {
        $match = KnowledgeBaseAnswerer::detectCrisis($text);
        $samples[] = ['text' => $text, 'match' => $match === null ? null : KnowledgeBaseAnswerer::normalize($match)];
    }

    return ['keywords' => $keywords, 'samples' => $samples];
}

it('écrit la même détection de crise que le serveur pour la page (crisis.parity.json, rejoué par Vitest)', function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $keywords = [];

    $this->actingAs(AlertFixtures::member())->get('/assistant')
        ->assertOk()
        ->assertInertia(function (Assert $page) use (&$keywords): void {
            $keywords = array_values($page->toArray()['props']['crisis']['keywords']);
        });

    $expected = crisisParityFixture($keywords);
    $path = base_path(CRISIS_PARITY_FIXTURE);

    if (getenv('XM_UPDATE_PARITY') === '1') {
        file_put_contents($path, json_encode($expected, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)."\n");
    }

    $stored = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

    expect($stored)->toBe($expected, 'crisis.parity.json est en retard sur crisis_keywords : XM_UPDATE_PARITY=1 vendor/bin/pest --filter=CrisisParity')
        ->and(collect($expected['samples'])->whereNotNull('match'))->not->toBeEmpty()
        ->and(collect($expected['samples'])->whereNull('match'))->not->toBeEmpty();
});
