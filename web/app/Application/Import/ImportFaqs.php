<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\Faq;
use App\Support\FrenchTypography;

/**
 * faqs.json → faqs (q → question, a → answer, published → is_published, position par ordre du fichier).
 * Les FAQ sont déjà semées (ContentSeeder) : l'import les retrouve par question (toutes graphies, FrenchTypography) et
 * ne réaligne que la position et la publication. Question, réponse, catégorie et affichage en page d'accueil d'une ligne
 * existante restent ceux du seeder ou de l'administration : le JSON du prototype garde « 24h/24… vers un humain » (Q36)
 * et l'apostrophe droite. Seule une question absente de la base est créée telle que le JSON la donne.
 */
final class ImportFaqs extends AbstractImporter
{
    public static function collection(): string
    {
        return 'faqs';
    }

    protected function importRow(array $row, int $index): void
    {
        $question = $this->required($row['q'] ?? $row['question'] ?? null, 'q', 255);
        $answer = $this->required($row['a'] ?? $row['answer'] ?? null, 'a');

        $faq = Faq::query()->whereIn('question', FrenchTypography::variants($question))->first();

        if ($faq === null) {
            // Accueil : 5–6 questions (Q16, P4-01) ; le prototype en affichait huit (index.php).
            $faq = new Faq(['question' => $question, 'answer' => $answer, 'show_on_home' => $index < 6]);
        }

        $faq->fill([
            'position' => $index + 1,
            'is_published' => (bool) ($row['published'] ?? true),
        ]);

        $faq->save();
        $this->track($faq);
    }
}
