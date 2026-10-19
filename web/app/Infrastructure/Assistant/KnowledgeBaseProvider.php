<?php

declare(strict_types=1);

namespace App\Infrastructure\Assistant;

use App\Application\Assistant\AssistantProvider;
use App\Application\Assistant\AssistantQuery;
use App\Application\Shared\Answer;
use App\Application\Shared\KnowledgeBaseAnswerer;

/**
 * Pilote `kb` (défaut, Q7 non tranchée) : la base de connaissances `assistant_kb` répond, sans LLM, sans réseau et
 * sans clé d'API (KnowledgeBaseAnswerer, P3-09) ; enregistrement de l'échange selon `persist`.
 */
final class KnowledgeBaseProvider implements AssistantProvider
{
    public const NAME = 'kb';

    public function __construct(private readonly KnowledgeBaseAnswerer $answerer) {}

    public function name(): string
    {
        return self::NAME;
    }

    public function answer(AssistantQuery $query): Answer
    {
        return $this->answerer->answer($query->question, $query->user, $query->persist, $query->conversation);
    }
}
