<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Forums;

use App\Enums\ContentReportReason;
use App\Http\Requests\Api\Forums\Concerns\ForumRequest;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST forums/topics/{ulid}/report · forums/replies/{id}/report (P8-05, comme le fil : FR-SOC-06, RG-16) : `{reason,
 * details?}` — motif parmi ContentReportReason::selectable(), précisions ≤ 500 car. Contenu visible (404 sinon) et
 * d'un·e autre membre (403 : on ne signale pas son propre contenu).
 */
final class ReportForumContentRequest extends FormRequest
{
    use ForumRequest;

    public function authorize(): bool
    {
        return $this->route('reply') instanceof ForumReply
            ? $this->viewer()->can('report', $this->visibleReply())
            : $this->viewer()->can('report', $this->visibleTopic());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::in(ContentReportReason::selectable())],
            'details' => ['sometimes', 'nullable', 'string', 'max:500'],
        ];
    }

    public function subject(): ForumTopic|ForumReply
    {
        return $this->route('reply') instanceof ForumReply ? $this->visibleReply() : $this->visibleTopic();
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
}
