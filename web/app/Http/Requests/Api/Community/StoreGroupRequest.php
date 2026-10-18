<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community;

use App\Application\Community\Data\GroupDraft;
use App\Enums\GroupPrivacy;
use App\Http\Requests\Api\Community\Concerns\CommunityRequest;
use App\Models\Group;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST groups (P8-02 ; modale de groups.php l.111-116) : `{name (3–80), description? (≤ 300), privacy (public |
 * prive), zone_id? (département actif, null = national)}` ; `social.access` (403 sinon), e-mail confirmé (`verified`).
 */
final class StoreGroupRequest extends FormRequest
{
    use CommunityRequest;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Group::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:'.(int) config('xomiton.community.group_name_min', 3), 'max:'.(int) config('xomiton.community.group_name_max', 80)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.community.group_description_max', 300)],
            'privacy' => ['required', 'string', Rule::enum(GroupPrivacy::class)],
            'zone_id' => ['sometimes', 'nullable', 'integer', Rule::exists('zones', 'id')->where('is_active', true)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = ['min' => (int) config('xomiton.community.group_name_min', 3)];

        return [
            'name.required' => (string) __('community.groups.create.name_min', $min),
            'name.min' => (string) __('community.groups.create.name_min', $min),
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function toDraft(): GroupDraft
    {
        $description = $this->validated('description');
        $zone = $this->validated('zone_id');

        return new GroupDraft(
            name: (string) $this->validated('name'),
            description: is_string($description) ? $description : null,
            privacy: GroupPrivacy::from((string) $this->validated('privacy')),
            zoneId: is_numeric($zone) ? (int) $zone : null,
        );
    }
}
