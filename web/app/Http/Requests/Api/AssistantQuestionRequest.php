<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/assistant/question (P4-01, Q41 a ; P8-11) : question libre posée à l'assistant, 2 à 500 caractères
 * (`maxlength` du champ de l'offcanvas et de /assistant ; KnowledgeBaseAnswerer::MAX_QUESTION_LENGTH reste la borne
 * interne). Compte connecté seulement (sinon ignorés) : `persist` (garder l'échange, chiffré ; faux par défaut, Q55) et
 * `conversation_id` (poursuivre une conversation enregistrée de la personne).
 */
final class AssistantQuestionRequest extends FormRequest
{
    public const MAX_LENGTH = 500;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'min:2', 'max:'.self::MAX_LENGTH],
            'persist' => ['sometimes', 'boolean'],
            'conversation_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'question' => (string) __('validation.attributes.question'),
            'persist' => (string) __('assistant.page.persist'),
            'conversation_id' => (string) __('assistant.page.conversation'),
        ];
    }

    public function question(): string
    {
        return (string) $this->validated('question');
    }

    public function persist(): bool
    {
        return $this->boolean('persist');
    }

    public function conversationId(): ?int
    {
        $id = $this->validated('conversation_id');

        return $id === null ? null : (int) $id;
    }
}
