<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use App\Application\Sync\Push\PushItem;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST sync/push` (P9-06, docs/API.md §21.2) : `{ items: [{uuid, type, op?, payload, created_at?}], pending? }`, 50
 * éléments au plus (`xomiton.sync.push_batch_size`), uuid distincts ; `Idempotency-Key` = uuid du lot (middleware
 * `idempotent:required`). Seule la forme du lot est validée ici : un type inconnu ou une charge utile refusée ne
 * refusent que l'élément (PushBatch), jamais le lot. `created_at` (heure de l'appareil) est accepté mais pas encore
 * exploité : l'alerte et l'escalade datent de la réception (Q86, reste à faire).
 */
final class PushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::maxItems()],
            'items.*' => ['required', 'array'],
            'items.*.uuid' => ['required', 'uuid', 'distinct:ignore_case'],
            'items.*.type' => ['required', 'string', 'max:20'],
            'items.*.op' => ['sometimes', 'string', 'in:'.PushItem::OP_CREATE.','.PushItem::OP_UPDATE],
            'items.*.payload' => ['present', 'array'],
            'items.*.created_at' => ['sometimes', 'nullable', 'date'],
            'pending' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'items' => (string) __('sync.fields.items'),
            'items.*.uuid' => (string) __('sync.fields.uuid'),
            'items.*.type' => (string) __('sync.fields.type'),
            'items.*.op' => (string) __('sync.fields.op'),
            'items.*.payload' => (string) __('sync.fields.payload'),
        ];
    }

    public static function maxItems(): int
    {
        return max(1, (int) config('xomiton.sync.push_batch_size', 50));
    }

    /**
     * @return list<PushItem>
     */
    public function items(): array
    {
        $items = [];

        foreach (array_values((array) $this->validated('items')) as $index => $item) {
            $items[] = new PushItem(
                index: $index,
                uuid: strtolower((string) $item['uuid']),
                type: (string) $item['type'],
                op: (string) ($item['op'] ?? PushItem::OP_CREATE),
                payload: (array) $item['payload'],
            );
        }

        return $items;
    }

    public function pending(): ?int
    {
        return $this->has('pending') ? (int) $this->validated('pending') : null;
    }
}
