<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Enums\ContentReportReason;
use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST conversations/{ulid}/report `{reason, details?, message_id?}` (P8-01, prototype messaging.php l.85-100) : motif
 * parmi App\Enums\ContentReportReason::selectable() (mêmes motifs que le fil), précisions ≤ 500 car.
 */
final class ReportConversationRequest extends FormRequest
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
            'reason' => ['required', 'string', Rule::in(ContentReportReason::selectable())],
            'details' => ['sometimes', 'nullable', 'string', 'max:500'],
            'message_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function reason(): ContentReportReason
    {
        return ContentReportReason::from((string) $this->validated('reason'));
    }

    public function details(): ?string
    {
        $details = $this->validated('details');

        return is_string($details) ? $details : null;
    }

    public function messageId(): ?int
    {
        $id = $this->validated('message_id');

        return $id === null ? null : (int) $id;
    }
}
