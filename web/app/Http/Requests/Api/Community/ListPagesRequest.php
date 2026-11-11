<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Application\Community\Pages\PageDirectory;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET pages?tab=all|following|mine&category=&q=&page=&per_page= (P8-03, pages.php : catégorie et recherche).
 */
final class ListPagesRequest extends FormRequest
{
    use CommunityRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('viewAny', Page::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tab' => ['sometimes', 'nullable', 'string', Rule::in(PageDirectory::TABS)],
            'category' => ['sometimes', 'nullable', 'string', 'max:60'],
            'q' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.community.search_max', 60)],
        ];
    }

    public function tab(): string
    {
        $tab = $this->validated('tab');

        return is_string($tab) && $tab !== '' ? $tab : PageDirectory::TAB_ALL;
    }

    public function category(): ?string
    {
        $category = $this->validated('category');

        return is_string($category) && trim($category) !== '' ? trim($category) : null;
    }

    public function term(): ?string
    {
        return $this->search();
    }
}
