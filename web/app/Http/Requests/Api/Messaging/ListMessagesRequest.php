<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET conversations/{ulid}/messages?cursor=&per_page= (historique, du plus récent au plus ancien) ou `?after=<id>`
 * (sondage court : les messages arrivés depuis, du plus ancien au plus récent) (P8-01).
 */
final class ListMessagesRequest extends FormRequest
{
    use MessagingRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('view', $this->conversation());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'after' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cursor' => ['sometimes', 'nullable', 'string', 'max:255'],
            'since' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /**
     * Sondage `?after=` : suppressions pour tout le monde à rapporter depuis `since` (`meta.checked_at` du passage
     * précédent), à défaut depuis `messaging.deleted_window_minutes`.
     */
    public function deletedSince(): CarbonImmutable
    {
        $window = now()->toImmutable()->subMinutes(max(1, (int) config('xomiton.messaging.deleted_window_minutes', 15)));
        $since = $this->validated('since');

        if (! is_string($since) || $since === '') {
            return $window;
        }

        // Jamais plus loin que la fenêtre de sondage la plus longue (onglet resté caché : le fil se recharge à l'ouverture).
        return max(CarbonImmutable::parse($since)->subSeconds(1), now()->toImmutable()->subDay());
    }

    public function afterId(): ?int
    {
        $after = $this->validated('after');

        return $after === null ? null : (int) $after;
    }
}
