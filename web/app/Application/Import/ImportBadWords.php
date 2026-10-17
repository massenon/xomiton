<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\BadWordSeverity;
use App\Models\BadWord;

/**
 * bad_words.json → bad_words (word, severity « bloquer » / « signaler »). Déjà semés : réalignés par mot.
 */
final class ImportBadWords extends AbstractImporter
{
    public static function collection(): string
    {
        return 'bad_words';
    }

    protected function importRow(array $row, int $index): void
    {
        $word = mb_strtolower($this->required($row['word'] ?? null, 'word', 80));
        $severity = BadWordSeverity::tryFrom(mb_strtolower((string) ($row['severity'] ?? '')))
            ?? $this->reject(sprintf('gravité inconnue « %s »', (string) ($row['severity'] ?? '')));

        $badWord = BadWord::query()->firstOrNew(['word' => $word]);
        $badWord->severity = $severity;

        if (! $badWord->exists) {
            $badWord->is_active = true;
        }

        $badWord->save();
        $this->track($badWord);
    }
}
