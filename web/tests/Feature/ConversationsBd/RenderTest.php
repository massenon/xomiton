<?php

declare(strict_types=1);

use App\Application\Content\ConversationsBd\BubbleGeometry;
use App\Application\Content\ConversationsBd\BubbleSvg;
use App\Application\Content\ConversationsBd\BubbleTranscript;
use Illuminate\Support\Facades\Blade;

if (! function_exists('bdcNb')) {
    /** Typographie des fichiers de lang et des données : espace insécable avant « : ! ? ». */
    function bdcNb(string $text): string
    {
        return str_replace([' :', ' !', ' ?'], ["\u{00A0}:", "\u{00A0}!", "\u{00A0}?"], $text);
    }
}

/*
 * P20-04 : rendu SVG des bulles (BubbleGeometry, BubbleSvg, BubbleTranscript, composant `x-bd.panel`) — formes des 9 types,
 * bulle dimensionnée d'après son texte, queue seulement sur la dernière bulle d'une chaîne, pensées en italique avec trois
 * ronds, cri gras et plus grand, texte réel échappé, transcription annoncée avec le personnage et la nature de la bulle.
 * La parité exacte avec le composant Vue est vérifiée par ComponentParityTest (cas `bd-panel*`) et parity.test.ts.
 */

function bdcB(string $type, string $text = 'Bonjour à toi.', array $over = []): array
{
    return array_replace(['type' => $type, 'personnage' => 'afi', 'texte' => $text, 'emphase' => [], 'position' => ['x' => 10, 'y' => 10], 'queue' => ['vers' => 'personnage', 'x' => 30, 'y' => 80], 'associee_a' => null, 'superpose' => false, 'masque' => false], $over);
}

it('dimensionne la bulle d’après son texte et garde une forme horizontale', function (): void {
    $short = BubbleGeometry::layout(bdcB('parole', 'Oui.'));
    $long = BubbleGeometry::layout(bdcB('parole', 'Je n’en ai pas envie… mais je ne veux pas le vexer, alors je dis peut-être.'));

    expect($long['w'])->toBeGreaterThan($short['w'])
        ->and($long['h'])->toBeGreaterThan($short['h'])
        ->and($long['w'])->toBeGreaterThan($long['h'])
        ->and(count($long['lines']))->toBeGreaterThan(1)
        ->and(count($short['lines']))->toBe(1);
    // Une espace insécable ne coupe jamais la ligne.
    expect(BubbleGeometry::words("Pourquoi\u{00A0}? Non."))->toBe(["Pourquoi\u{00A0}?", 'Non.']);
});

it('applique la taille des textes : cri ×1,3, tremblée et chuchotement ×0,85', function (): void {
    expect(BubbleGeometry::layout(bdcB('cri'))['font'])->toBe(22 * 1.3)
        ->and(BubbleGeometry::layout(bdcB('tremblee'))['font'])->toBe(22 * 0.85)
        ->and(BubbleGeometry::layout(bdcB('chuchote'))['font'])->toBe(22 * 0.85)
        ->and(BubbleGeometry::layout(bdcB('parole'))['font'])->toBe(22.0);
});

it('dessine la forme de chaque type', function (string $type, string $expected): void {
    expect(BubbleSvg::render([bdcB($type)]))->toMatch($expected);
})->with([
    'parole : ovale lisse' => ['parole', '/<path d="M[\d.]+ [\d.]+ A[\d.]+ [\d.]+ 0 1 0 /'],
    'pensée : nuage, trois ronds, italique' => ['pensee', '/A[\d.]+ [\d.]+ 0 0 1 .*(<circle [^>]+>.*){6}.*font-style="italic"/s'],
    'cri : pointes, contour épais, gras' => ['cri', '/(L[\d.]+ [\d.]+ ){23}Z" stroke-width="9".*font-weight="700"/s'],
    'tremblée : contour irrégulier' => ['tremblee', '/(Q[\d.]+ [\d.]+ [\d.]+ [\d.]+ ){24}Z/'],
    'chuchoté : pointillés' => ['chuchote', '/stroke-dasharray="12 8"/'],
    'joie : nuage et queue pointue' => ['joie', '/A[\d.]+ [\d.]+ 0 0 1 .*<path d="M[\d.]+ [\d.]+ L[\d.]+ [\d.]+ L/s'],
    'message : rectangle arrondi' => ['message', '/A16 16 0 0 1/'],
    'voix d’un appareil : ondes, italique' => ['voix_appareil', '/class="xm-bdc-waves".*font-style="italic"/s'],
])->group('bd');

it('dessine le cartouche sans queue, sur fond rose pâle, en Montserrat gras', function (): void {
    $svg = BubbleSvg::render([bdcB('cartouche', 'Le lendemain.', ['personnage' => null, 'queue' => ['vers' => 'personnage', 'x' => 30, 'y' => 80]])]);

    expect($svg)->toContain('fill="#FBF7FA"')
        ->toContain('font-family="Montserrat')
        ->toContain('font-weight="700"')
        ->not->toContain(' L');
});

it('ne dessine la queue que sur la dernière bulle d’une chaîne associée, reliée par un col', function (): void {
    $svg = BubbleSvg::render([
        bdcB('parole', 'Je voulais te dire…', ['queue' => null]),
        bdcB('parole', '… je ne suis pas d’accord.', ['position' => ['x' => 45, 'y' => 10], 'associee_a' => 0]),
    ]);

    expect(substr_count($svg, 'class="xm-bdc-chain'))->toBe(1)
        ->and(substr_count($svg, '<line '))->toBe(2)
        ->and(substr_count($svg, ' L'))->toBe(4); // une queue = deux segments, dessinée deux fois (contour + fond)
});

it('masque une insulte : bulle grisée sans texte', function (): void {
    $svg = BubbleSvg::render([bdcB('message', '', ['masque' => true, 'personnage' => null])]);

    expect($svg)->toContain('fill="#E4E1E8"')->toContain('fill="#B9B4C2"')->not->toContain('<text');
});

it('échappe le texte des bulles et met l’emphase en gras', function (): void {
    $svg = BubbleSvg::render([bdcB('parole', 'Non <b>merci</b>, vraiment non.', ['emphase' => ['vraiment']])]);

    expect($svg)->toContain('&lt;b&gt;merci&lt;/b&gt;')->not->toContain('<b>')
        ->toContain('<tspan font-weight="700">vraiment </tspan>');
});

it('transcrit les bulles avec le personnage et leur nature, la chaîne en une seule réplique', function (): void {
    $names = ['afi' => 'Afi', 'kossi' => 'Kossi'];
    $lines = BubbleTranscript::lines([
        bdcB('cartouche', 'Le soir.', ['personnage' => null, 'queue' => null]),
        bdcB('pensee', 'Je dois lui dire…', ['queue' => null]),
        bdcB('pensee', 'mais elle va se fâcher.', ['associee_a' => 1]),
        bdcB('parole', bdcNb('Qui est là ?'), ['personnage' => 'kossi', 'queue' => ['vers' => 'bord-droit']]),
        bdcB('message', bdcNb('Tu viens ?'), ['personnage' => 'inconnu', 'queue' => ['vers' => 'ecran', 'x' => 50, 'y' => 90]]),
        bdcB('message', 'Non.', ['queue' => ['vers' => 'personnage', 'x' => 20, 'y' => 90]]),
        bdcB('voix_appareil', bdcNb('Allô ?'), ['personnage' => 'kossi']),
        bdcB('chuchote', 'Chut.'),
        bdcB('message', '', ['masque' => true, 'personnage' => null]),
    ], $names);

    expect(array_column($lines, 'text'))->toBe([
        bdcNb('Narration : Le soir.'),
        bdcNb('Afi pense : Je dois lui dire… mais elle va se fâcher.'),
        bdcNb('Kossi dit, hors de l’image : Qui est là ?'),
        bdcNb('Message reçu de inconnu : Tu viens ?'),
        bdcNb('Message envoyé par Afi : Non.'),
        bdcNb('Voix au téléphone, Kossi : Allô ?'),
        bdcNb('Afi chuchote : Chut.'),
        __('conversations_bd.sr.masked'),
    ])->and(array_column($lines, 'thought'))->toBe([false, true, false, false, false, false, false, false]);
});

it('rend une case : repli rose pâle avec la description tant que l’image manque, transcription, SVG caché des lecteurs d’écran', function (): void {
    $html = Blade::render('<x-bd.panel :bubbles="$bubbles" :names="[\'afi\' => \'Afi\']" alt="Afi attend devant le lycée." :n="2" :count="5" title="Test" />', ['bubbles' => [bdcB('parole')]]);

    expect($html)->toContain('<figure class="xm-bdc-panel"')
        ->toContain('class="xm-bdc-fallback"')
        ->toContain('Afi attend devant le lycée.')
        ->toContain('Case 2 sur 5')
        ->toContain(bdcNb('>Afi dit : Bonjour à toi.</li>'))
        ->not->toContain('class=""')
        ->toContain('aria-hidden="true" focusable="false"')
        ->not->toContain('<img');
});
