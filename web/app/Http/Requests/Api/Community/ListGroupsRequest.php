<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Application\Community\Groups\GroupDirectory;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET groups?tab=discover|mine&q=&page=&per_page= (P8-02, groups.php : onglets et recherche ≤ 60 car.).
 */
final class ListGroupsRequest extends FormRequest
{
    use CommunityRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Group::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['sometimes', 'nullable', 'string', Rule::in(GroupDirectory::TABS)],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.community.search_max', 60)],
        ];
    }

    public function tab(): string
    {
        $tab = $this->validated('tab');

        return is_string($tab) && $tab !== '' ? $tab : GroupDirectory::TAB_DISCOVER;
    }

    public function term(): ?string
    {
        return $this->search();
    }
}
