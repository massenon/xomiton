<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\AssistantKb;

/**
 * assistant_kb.json → assistant_kb (q → question, a → answer, keywords « a, b » → json, active → is_active).
 * Déjà semée par ContentSeeder : réalignée par question, le compteur de consultations est conservé.
 */
final class ImportAssistantKb extends AbstractImporter
{
    public static function collection(): string
    {
        return 'assistant_kb';
    }

    protected function importRow(array $row, int $index): void
    {
        $question = $this->required($row['q'] ?? $row['question'] ?? null, 'q', 255);
        $answer = $this->required($row['a'] ?? $row['answer'] ?? null, 'a');
        $keywords = $row['keywords'] ?? [];

        if (is_string($keywords)) {
            $keywords = explode(',', $keywords);
        }

        $keywords = array_values(array_unique(array_filter(array_map(
            static fn (mixed $keyword): string => mb_strtolower(trim((string) $keyword)),
            is_array($keywords) ? $keywords : [],
        ))));

        $entry = AssistantKb::query()->updateOrCreate(
            ['question' => $question],
            ['answer' => $answer, 'keywords' => $keywords, 'is_active' => (bool) ($row['active'] ?? true)],
        );

        $this->track($entry);
    }
}
