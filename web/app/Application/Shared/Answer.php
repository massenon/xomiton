<?php

declare(strict_types=1);

namespace App\Application\Shared;

/**
 * Réponse de l'assistant (KnowledgeBaseAnswerer, P3-09) : texte prêt à afficher (retours à la ligne conservés, comme la
 * bulle `white-space: pre-line` du prototype), nature de la réponse, entrée de la base appariée, questions proches à
 * proposer et drapeau d'urgence (réponse de crise : numéros, SOS, signalement, escalade vers un humain).
 */
final readonly class Answer
{
    public const KIND_KB = 'kb';

    public const KIND_CRISIS = 'crisis';

    public const KIND_FALLBACK = 'fallback';

    /**
     * @param  list<string>  $suggestions  questions de la base les plus proches (3 au plus)
     * @param  int|null  $conversationId  conversation `assistant_conversations` alimentée, si l'échange a été enregistré
     */
    public function __construct(
        public string $text,
        public string $kind,
        public ?int $matchedKbId = null,
        public array $suggestions = [],
        public bool $emergency = false,
        public ?int $conversationId = null,
    ) {}

    public function isCrisis(): bool
    {
        return $this->kind === self::KIND_CRISIS;
    }

    public function isFromKnowledgeBase(): bool
    {
        return $this->kind === self::KIND_KB;
    }

    public function isFallback(): bool
    {
        return $this->kind === self::KIND_FALLBACK;
    }

    public function withConversationId(int $conversationId): self
    {
        return new self($this->text, $this->kind, $this->matchedKbId, $this->suggestions, $this->emergency, $conversationId);
    }

    /**
     * @return array{text: string, kind: string, matched_kb_id: int|null, suggestions: list<string>, emergency: bool, conversation_id: int|null}
     */
    public function toArray(): array
    {
        return [
            'text' => $this->text,
            'kind' => $this->kind,
            'matched_kb_id' => $this->matchedKbId,
            'suggestions' => $this->suggestions,
            'emergency' => $this->emergency,
            'conversation_id' => $this->conversationId,
        ];
    }
}
