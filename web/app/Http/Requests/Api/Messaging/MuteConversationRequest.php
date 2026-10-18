<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Messaging;

use App\Http\Requests\Api\Messaging\Concerns\MessagingRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST conversations/{ulid}/mute `{duration: "8h" | "1w" | "always"}` (P8-01, `xomiton.messaging.mute_durations`).
 */
final class MuteConversationRequest extends FormRequest
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
        return ['duration' => ['sometimes', 'string', Rule::in(array_keys(self::durations()))]];
    }

    /** Minutes de sourdine ; null = jusqu'à réactivation. */
    public function minutes(): ?int
    {
        $minutes = self::durations()[(string) $this->validated('duration', 'always')] ?? null;

        return $minutes === null ? null : (int) $minutes;
    }

    /**
     * @return array<string, int|null>
     */
    private static function durations(): array
    {
        return (array) config('xomiton.messaging.mute_durations', ['8h' => 480, '1w' => 10080, 'always' => null]);
    }
}
