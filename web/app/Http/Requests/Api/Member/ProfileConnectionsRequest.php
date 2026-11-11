<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Member;

use Illuminate\Foundation\Http\FormRequest;

/**
 * GET members/{pseudo}/connections?q=&page= (P8-07, my-profile-connections.php : recherche ≤ 60 car. sur le pseudo).
 */
final class ProfileConnectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.profile.search_max', 60)],
            'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
        ];
    }

    public function term(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
