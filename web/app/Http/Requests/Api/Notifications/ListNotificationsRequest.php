<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notifications;

use App\Application\Notifications\NotificationInbox;
use App\Models\DatabaseNotification;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/notifications (P6-07) : `?filter=all|unread|read` (défaut `all`), `?category=` (catégorie de
 * préférences), `?page=`, `?per_page=` (≤ xomiton.api.max_per_page). POST notifications/read-all accepte `category`.
 */
final class ListNotificationsRequest extends FormRequest
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
            'filter' => ['sometimes', 'string', Rule::in(NotificationInbox::FILTERS)],
            'category' => ['sometimes', 'nullable', 'string', Rule::in(DatabaseNotification::CATEGORIES)],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.(int) config('xomiton.api.max_per_page', 50)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'filter' => __('account.notifications.filter'),
            'category' => __('account.notifications.category'),
        ];
    }

    public function filter(): string
    {
        $filter = $this->validated('filter');

        return is_string($filter) ? $filter : NotificationInbox::FILTER_ALL;
    }

    public function category(): ?string
    {
        $category = $this->validated('category');

        return is_string($category) && $category !== '' ? $category : null;
    }
}
