<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use App\Models\Conversation;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET conversations/recipients?q= (P8-01, « Nouvelle conversation ») : début de pseudonyme (`recipient_search_min` à
 * 40 car.) ; sans saisie, les connexions de la personne.
 */
final class SearchRecipientsRequest extends FormRequest
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
        return ['q' => ['sometimes', 'nullable', 'string', 'min:'.(int) config('xomiton.messaging.recipient_search_min', 2), 'max:40']];
    }

    public function term(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
