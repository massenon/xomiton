<?php

declare(strict_types=1);

namespace App\Application\Admin\Assistant;

use App\Models\AssistantConversation;
use App\Models\AssistantKb;
use App\Models\AssistantMessage;
use Illuminate\Support\Collection;

/**
 * Statistiques de l'assistant (P12-07) : uniquement des comptages sur les échanges enregistrés (jamais le texte). Un
 * échange non enregistré (visiteur·ses, membres qui n'ont pas demandé l'historique) n'est pas compté ici ; les réponses
 * de la base sont comptées par entrée (`hits_count`), enregistrées ou non.
 */
final class AssistantStats
{
    /**
     * @return array{conversations: int, flagged: int, escalated: int, answered_share: int|null, kb_active: int, kb_hits: int}
     */
    public function summary(): array
    {
        $assistantMessages = AssistantMessage::query()->where('role', AssistantMessage::ROLE_ASSISTANT)->count();
        $answered = AssistantMessage::query()->where('role', AssistantMessage::ROLE_ASSISTANT)->whereNotNull('matched_kb_id')->count();

        return [
            'conversations' => AssistantConversation::query()->count(),
            'flagged' => AssistantConversation::query()->flagged()->count(),
            'escalated' => AssistantConversation::query()->escalated()->count(),
            'answered_share' => $assistantMessages === 0 ? null : (int) round($answered / $assistantMessages * 100),
            'kb_active' => AssistantKb::query()->active()->count(),
            'kb_hits' => (int) AssistantKb::query()->sum('hits_count'),
        ];
    }

    /**
     * Les entrées les plus consultées.
     *
     * @return Collection<int, AssistantKb>
     */
    public function topEntries(int $limit = 5): Collection
    {
        return AssistantKb::query()->where('hits_count', '>', 0)->orderByDesc('hits_count')->orderBy('id')->limit($limit)->get(['id', 'question', 'hits_count']);
    }
}
