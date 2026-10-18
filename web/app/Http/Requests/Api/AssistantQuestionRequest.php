<?php

declare(strict_types=1);

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/assistant/question (P4-01, Q41 a) : question libre posée à l'assistant public, 2 à 500 caractères
 * (`maxlength` du champ de l'offcanvas ; KnowledgeBaseAnswerer::MAX_QUESTION_LENGTH reste la borne interne).
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
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'question' => (string) __('validation.attributes.question'),
        ];
    }

    public function question(): string
    {
        return (string) $this->validated('question');
    }
}
