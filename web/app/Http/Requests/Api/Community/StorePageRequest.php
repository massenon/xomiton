<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Application\Community\Data\PageDraft;
use App\Application\Community\Pages\PageDirectory;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use App\Models\Page;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST pages (P8-03 ; create-page.php) : `{name (3–80), category (xomiton.community.page_categories), description?
 * (≤ 400), website? (adresse http(s), ≤ 190)}` ; `social.access` (403 sinon), e-mail confirmé (`verified`).
 */
final class StorePageRequest extends FormRequest
{
    use CommunityRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Page::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.(int) config('xomiton.community.page_name_min', 3), 'max:'.(int) config('xomiton.community.page_name_max', 80)],
            'category' => ['required', 'string', Rule::in(PageDirectory::choices())],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.community.page_description_max', 400)],
            'website' => ['sometimes', 'nullable', 'string', 'max:190', 'url:http,https'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = ['min' => (int) config('xomiton.community.page_name_min', 3)];

        return [
            'name.required' => (string) __('community.pages.create.name_min', $min),
            'name.min' => (string) __('community.pages.create.name_min', $min),
            'category.in' => (string) __('community.pages.create.category_invalid'),
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'website'] as $field) {
            if (is_string($this->input($field))) {
                $this->merge([$field => trim($this->input($field)) === '' ? null : trim($this->input($field))]);
            }
        }
    }

    public function toDraft(): PageDraft
    {
        $description = $this->validated('description');
        $website = $this->validated('website');

        return new PageDraft(
            name: (string) $this->validated('name'),
            category: (string) $this->validated('category'),
            description: is_string($description) ? $description : null,
            website: is_string($website) ? $website : null,
        );
    }
}
