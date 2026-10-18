<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Réponse de l'assistant public (P4-01) : `Answer::toArray()` sans `matched_kb_id` ni `conversation_id` (inutiles au
 * public, rien n'est enregistré), plus les liens d'action affichés sous la bulle — pour une réponse de crise : SOS, un
 * lien `tel:` par numéro d'urgence configuré, « Exprimer un besoin » (/signaler?tab=besoin, prototype signalement.php
 * l.11), signalement et contacts d'urgence ; aucun sinon (la base n'a pas de colonne de lien, plan §7).
 *
 * @property Answer $resource
 */
final class AssistantAnswerResource extends JsonResource
{
    /**
     * @return array{text: string, kind: string, emergency: bool, suggestions: list<string>, links: list<array{label: string, url: string, variant: string}>}
     */
    public function toArray(Request $request): array
    {
        $answer = $this->resource;

        return [
            'text' => $answer->text,
            'kind' => $answer->kind,
            'emergency' => $answer->emergency,
            'suggestions' => $answer->suggestions,
            'links' => $answer->isCrisis() ? self::crisisLinks() : [],
        ];
    }

    /**
     * @return list<array{label: string, url: string, variant: string}>
     */
    private static function crisisLinks(): array
    {
        $calls = array_map(static fn (array $entry): array => [
            'label' => (string) __('assistant.links.call', ['number' => $entry['number'], 'label' => $entry['label']]),
            'url' => KnowledgeBaseAnswerer::telHref($entry['number']),
            'variant' => 'primary-soft',
        ], KnowledgeBaseAnswerer::emergencyNumbers());

        return [
            ['label' => (string) __('buttons.help.sos_gps'), 'url' => route('sos'), 'variant' => 'danger'],
            ...$calls,
            ['label' => (string) __('assistant.links.need'), 'url' => route('signaler', ['tab' => 'besoin']), 'variant' => 'primary-soft'],
            ['label' => (string) __('buttons.help.report'), 'url' => route('signaler'), 'variant' => 'primary-soft'],
            ['label' => (string) __('buttons.help.emergency_contacts'), 'url' => route('urgences'), 'variant' => 'primary-soft'],
        ];
    }
}
