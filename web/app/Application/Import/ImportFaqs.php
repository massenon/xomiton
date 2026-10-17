<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\Faq;

/**
 * faqs.json → faqs (q → question, a → answer, published → is_published, position par ordre du fichier).
 * Les FAQ sont déjà semées (ContentSeeder) : l'import les réaligne par question, sans toucher à la
 * catégorie ni à l'affichage en page d'accueil réglés par l'administration.
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

        $faq = Faq::query()->firstOrNew(['question' => $question]);

        $faq->fill([
            'answer' => $answer,
            'position' => $index + 1,
            'is_published' => (bool) ($row['published'] ?? true),
        ]);

        if (! $faq->exists) {
            // Le prototype affiche les huit premières questions en page d'accueil (index.php).
            $faq->show_on_home = $index < 8;
        }

        $faq->save();
        $this->track($faq);
    }
}
