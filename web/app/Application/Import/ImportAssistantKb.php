<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Models\AssistantKb;
use App\Support\FrenchTypography;

/**
 * assistant_kb.json → assistant_kb (q → question, a → answer, keywords « a, b » → json, active → is_active).
 * Déjà semée par ContentSeeder : retrouvée par question (toutes graphies, FrenchTypography), seuls les mots-clés et
 * l'activation sont réalignés ; question et réponse d'une entrée existante restent celles du seeder ou de l'administration
 * (réponses au tu, P4-01 / Q56 ; le JSON du prototype vouvoie) et le compteur de consultations est conservé.
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

        $entry = AssistantKb::query()->whereIn('question', FrenchTypography::variants($question))->first()
            ?? new AssistantKb(['question' => $question, 'answer' => $answer]);

        $entry->fill(['keywords' => $keywords, 'is_active' => (bool) ($row['active'] ?? true)])->save();

        $this->track($entry);
    }
}
