<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Events;

use App\Application\Community\Data\EventDraft;
use App\Http\Requests\Api\Events\Concerns\EventRequest;
use App\Models\Category;
use App\Models\Event;
use App\Models\Group;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Throwable;

/**
 * POST events (P8-04 ; modale « Créer un événement » de partials/modals.php l.166-190) : `{title (3–200), starts_at
 * (date et heure à venir, fuseau de l'application si absent), ends_at? (après le début, 31 jours au plus), place?
 * (ville ou zone, ≤ 120), address? (adresse exacte, ≤ 255, réservée aux participant·es), is_online?, online_url? (https),
 * category_id? (catégorie d'événement active), description? (≤ 2000), group? (ULID d'un groupe où l'on peut publier),
 * is_public? (modération seulement)}` ; `social.access` (403 sinon), e-mail confirmé (`verified`).
 */
final class StoreEventRequest extends FormRequest
{
    use EventRequest;

    private ?Group $resolvedGroup = null;

    public function authorize(): bool
    {
        return $this->viewer()->can('create', Event::class);
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach (['title', 'place', 'address', 'online_url', 'description'] as $field) {
            if (is_string($this->input($field))) {
                $trimmed[$field] = trim($this->input($field));
            }
        }

        $this->merge($trimmed);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:'.(int) config('xomiton.events.title_min', 3), 'max:'.(int) config('xomiton.events.title_max', 200)],
            'starts_at' => ['required', 'date', 'after:now'],
            'ends_at' => ['sometimes', 'nullable', 'date', 'after:starts_at'],
            'place' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.events.place_max', 120)],
            'address' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.events.address_max', 255)],
            'is_online' => ['sometimes', 'boolean'],
            'online_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url:https'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('categories', 'id')->where('kind', Category::KIND_EVENT)->where('is_active', true)],
            'description' => ['sometimes', 'nullable', 'string', 'max:'.(int) config('xomiton.events.description_max', 2000)],
            'group' => ['sometimes', 'nullable', 'string', 'size:26'],
            'is_public' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = ['min' => (int) config('xomiton.events.title_min', 3)];

        return [
            'title.required' => (string) __('community.events.create.title_min', $min),
            'title.min' => (string) __('community.events.create.title_min', $min),
            'starts_at.after' => (string) __('community.events.create.starts_future'),
            'ends_at.after' => (string) __('community.events.create.ends_after'),
            'online_url.url' => (string) __('community.events.create.online_url_https'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (is_string($this->input('group')) && $this->input('group') !== '') {
                $group = $this->group();

                if ($group === null || ! $this->viewer()->can('post', $group)) {
                    $validator->errors()->add('group', (string) __('community.groups.errors.not_member'));
                }
            }

            if ($this->boolean('is_public') && ! $this->viewer()->can('publishPublicly', Event::class)) {
                $validator->errors()->add('is_public', (string) __('community.events.api.public_reserved'));
            }

            if ($this->boolean('is_public') && $this->group() !== null && $this->group()->isPrivate()) {
                $validator->errors()->add('is_public', (string) __('community.events.api.public_private_group'));
            }

            if ($this->boolean('is_online') && ! $this->filled('online_url') && ! $this->filled('place')) {
                $validator->errors()->add('online_url', (string) __('community.events.create.online_url_required'));
            }

            $starts = $this->moment('starts_at');
            $ends = $this->moment('ends_at');
            $days = (int) config('xomiton.events.max_duration_days', 31);

            if ($starts !== null && $ends !== null && $ends->greaterThan($starts->addDays($days))) {
                $validator->errors()->add('ends_at', (string) __('community.events.create.too_long', ['days' => $days]));
            }
        }];
    }

    public function group(): ?Group
    {
        $ulid = $this->input('group');

        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        return $this->resolvedGroup ??= Group::query()->active()->whereUlid($ulid)->first();
    }

    public function toDraft(): EventDraft
    {
        $string = fn (string $key): ?string => is_string($this->validated($key)) && $this->validated($key) !== '' ? (string) $this->validated($key) : null;
        $category = $this->validated('category_id');

        return new EventDraft(
            title: (string) $this->validated('title'),
            startsAt: $this->moment('starts_at') ?? CarbonImmutable::now(),
            endsAt: $this->moment('ends_at'),
            place: $string('place'),
            address: $string('address'),
            isOnline: $this->boolean('is_online'),
            onlineUrl: $string('online_url'),
            categoryId: is_numeric($category) ? (int) $category : null,
            description: $string('description'),
            groupId: $this->group()?->getKey() !== null ? (int) $this->group()->getKey() : null,
            isPublic: $this->boolean('is_public'),
        );
    }

    /** Date et heure reçues, dans le fuseau de l'application quand le client n'en donne pas. */
    private function moment(string $key): ?CarbonImmutable
    {
        $value = $this->input($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($value, (string) config('app.timezone', 'UTC'))->setTimezone((string) config('app.timezone', 'UTC'));
        } catch (Throwable) {
            return null;
        }
    }
}
