<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PATCH conversations/{ulid} `{title}` (P8-01) : renommer un groupe (créateur·rice ou admin, 403 sinon).
 */
final class UpdateConversationRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        $conversation = $this->conversation();

        // Conversation privée : aucune gestion (ConversationPolicy) ; une participante reçoit le 422 explicite
        // `conversation_group_only` de l'action plutôt qu'un 403.
        return $this->viewer()->can($conversation->isGroup() ? 'update' : 'view', $conversation);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:'.(int) config('xomiton.messaging.group_title_min', 3), 'max:'.Conversation::PREVIEW_MAX_LENGTH],
        ];
    }

    public function title(): string
    {
        return trim((string) $this->validated('title'));
    }
}
