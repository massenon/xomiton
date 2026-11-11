<?php

declare(strict_types=1);

namespace App\Application\Admin\Assistant;

use App\Models\AssistantKb;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Str;

/**
 * Base de connaissances de l'assistant (P12-07, permission `assistant_kb.manage`) : question, réponse, mots-clés (séparés
 * par des virgules, comparés sans accent ni casse par AssistantKb::matches), actif / inactif. Le compteur de réponses
 * (`hits_count`) n'est jamais modifié ici. Journalisé sans texte.
 */
final class ManageAssistantKb
{
    public const KEYWORDS_MAX_COUNT = 20;

    /**
     * Mots-clés d'une saisie « agression, viol ; attouchement » : minuscules, sans doublon, sans vide, 40 caractères au plus.
     *
     * @return list<string>
     */
    public static function parseKeywords(string $input): array
    {
        $parts = preg_split('/[,;\n]+/u', $input) ?: [];
        $keywords = [];

        foreach ($parts as $part) {
            $keyword = Str::limit(mb_strtolower(trim($part)), 40, '');

            if ($keyword !== '' && ! in_array($keyword, $keywords, true)) {
                $keywords[] = $keyword;
            }
        }

        return $keywords;
    }

    /**
     * @param  array{question: string, answer: string, keywords: list<string>, is_active: bool}  $data
     */
    public function save(?AssistantKb $entry, array $data, User $actor): AssistantKb
    {
        $entry ??= new AssistantKb;
        $creating = ! $entry->exists;

        $entry->fill([
            'question' => trim($data['question']),
            'answer' => trim($data['answer']),
            'keywords' => $data['keywords'],
            'is_active' => $data['is_active'],
        ])->save();

        Audit::log($creating ? 'assistant.kb_created' : 'assistant.kb_updated', $entry, null, $actor);

        return $entry;
    }

    public function toggle(AssistantKb $entry, User $actor): AssistantKb
    {
        $entry->forceFill(['is_active' => ! $entry->is_active])->save();
        Audit::log($entry->is_active ? 'assistant.kb_activated' : 'assistant.kb_deactivated', $entry, null, $actor);

        return $entry;
    }

    public function delete(AssistantKb $entry, User $actor): void
    {
        $entry->delete();
        Audit::log('assistant.kb_deleted', $entry, null, $actor);
    }
}
