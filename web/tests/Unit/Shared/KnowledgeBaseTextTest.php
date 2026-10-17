<?php

declare(strict_types=1);

use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\AssistantKb;

/*
 * P3-09 : normalisation, jetons et score d'appariement, sans base de données.
 */

it('normalise un texte : minuscules, sans accent, apostrophes et ponctuation effacées', function (): void {
    expect(KnowledgeBaseAnswerer::normalize('  Qu’est-ce que le CONSENTEMENT ?!  '))->toBe('qu est ce que le consentement')
        ->and(KnowledgeBaseAnswerer::normalize('Dépistage VIH / IST'))->toBe('depistage vih ist')
        ->and(KnowledgeBaseAnswerer::normalize('   '))->toBe('');
});

it('extrait des jetons significatifs sans mots vides ni doublons', function (): void {
    expect(KnowledgeBaseAnswerer::tokens(KnowledgeBaseAnswerer::normalize('Comment porter plainte ? Je veux porter plainte.')))
        ->toBe(['porter', 'plainte']);
});

it('calcule un score borné à 1 qui croît avec les mots-clés et la similarité de question', function (): void {
    $kb = new AssistantKb(['question' => 'Comment porter plainte ?', 'keywords' => ['plainte', 'police', 'justice', 'loi']]);

    expect(KnowledgeBaseAnswerer::score($kb, 'bonjour'))->toBe(0.0)
        ->and(KnowledgeBaseAnswerer::score($kb, 'la police'))->toBe(0.6)
        ->and(KnowledgeBaseAnswerer::score($kb, 'la police et la justice'))->toBe(0.8)
        ->and(KnowledgeBaseAnswerer::score($kb, 'policier'))->toBe(0.0)
        ->and(KnowledgeBaseAnswerer::score($kb, 'comment porter plainte'))->toBe(1.0)
        ->and(KnowledgeBaseAnswerer::score($kb, 'porter des plaintes contre son patron'))->toBeGreaterThanOrEqual(0.6)
        ->and(KnowledgeBaseAnswerer::score($kb, 'la police, la justice, la loi et une plainte : tout'))->toBe(1.0);
});

it('tolère un pluriel et un préfixe de quatre lettres au moins, pas un mot inclus', function (): void {
    $kb = new AssistantKb(['question' => 'Que faire après une agression sexuelle ?', 'keywords' => ['agression', 'viol']]);

    expect(KnowledgeBaseAnswerer::score($kb, 'des agressions'))->toBe(0.6)
        ->and(KnowledgeBaseAnswerer::score($kb, 'les violences conjugales'))->toBe(0.0)
        ->and(KnowledgeBaseAnswerer::score($kb, 'agressée sexuellement'))->toBeGreaterThan(0.0);
});
