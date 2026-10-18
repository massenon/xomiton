<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST conversations/{ulid}/read `{message_id?}` (P8-01) : lu jusqu'au message donné, sinon jusqu'au dernier.
 */
final class MarkReadRequest extends FormRequest
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
        return ['message_id' => ['sometimes', 'nullable', 'integer', 'min:1']];
    }

    public function messageId(): ?int
    {
        $id = $this->validated('message_id');

        return $id === null ? null : (int) $id;
    }
}
