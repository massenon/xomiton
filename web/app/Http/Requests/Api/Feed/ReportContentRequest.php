<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Enums\ContentReportReason;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * POST /api/v1/posts/{ulid}/report · comments/{id}/report (P7-02, FR-SOC-06) : `{reason, details?}` — motif parmi
 * App\Enums\ContentReportReason::selectable(), précisions ≤ 500 car. Contenu visible (404 sinon) et d'un·e autre membre
 * (403 : on ne signale pas son propre contenu).
 */
final class ReportContentRequest extends FormRequest
{
    use FeedRequest;

    public function authorize(): bool
    {
        $comment = $this->route('comment');

        if ($comment instanceof Comment) {
            if (! $this->viewer()->can('view', $comment)) {
                throw new NotFoundHttpException;
            }

            return $this->viewer()->can('report', $comment);
        }

        return $this->viewer()->can('report', $this->visiblePost());
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
