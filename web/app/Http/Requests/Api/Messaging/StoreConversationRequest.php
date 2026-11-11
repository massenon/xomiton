<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST conversations (P8-01) : `{kind: "private", user_id}` ou `{kind: "group", title, user_ids[]}` — titre de
 * `group_title_min` à 120 car., 1 à `group_max_participants - 1` personnes distinctes.
 */
final class StoreConversationRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Conversation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $max = max(2, (int) config('xomiton.messaging.group_max_participants', 50)) - 1;

        return [
            'kind' => ['sometimes', 'string', 'in:private,group'],
            'user_id' => ['exclude_if:kind,group', 'required', 'integer', 'min:1'],
            'title' => ['exclude_unless:kind,group', 'required', 'string', 'min:'.(int) config('xomiton.messaging.group_title_min', 3), 'max:'.Conversation::PREVIEW_MAX_LENGTH],
            'user_ids' => ['exclude_unless:kind,group', 'required', 'array', 'min:1', 'max:'.$max],
            'user_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    public function isGroup(): bool
    {
        return $this->input('kind') === 'group';
    }

    public function recipientId(): int
    {
        return (int) $this->validated('user_id');
    }

    public function title(): string
    {
        return trim((string) $this->validated('title'));
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('user_ids', [])));
    }
}
