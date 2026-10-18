<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET conversations?q=&filter=all|unread&page=&per_page= (P8-01) : recherche sur le titre et les pseudonymes (≤ 60
 * car.), filtre des non lus.
 */
final class ListConversationsRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Conversation::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:60'],
            'filter' => ['sometimes', 'nullable', 'string', 'in:all,unread'],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'since' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];
    }

    public function page(): int
    {
        return max(1, (int) ($this->validated('page') ?? 1));
    }

    /** Empreinte de la liste déjà affichée (`meta.fingerprint` du passage précédent). */
    public function since(): ?string
    {
        $since = $this->validated('since');

        return is_string($since) && $since !== '' ? $since : null;
    }

    public function search(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }

    public function unreadOnly(): bool
    {
        return $this->validated('filter') === 'unread';
    }
}
