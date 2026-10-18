<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET conversations/{ulid} · POST …/leave · …/block · DELETE …/mute · …/block (P8-01) : conversation où la personne
 * est présente (404 sinon), sans corps.
 */
final class ViewConversationRequest extends FormRequest
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
        return [];
    }
}
