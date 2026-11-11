<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Notifications;

use App\Application\Notifications\NotificationInbox;
use App\Application\Notifications\NotificationTab;
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
            // P8-10 : filtres de la page /notifications (Toutes · Invitations · Événements · Échanges · Système).
            'tab' => ['sometimes', 'nullable', 'string', Rule::in(NotificationTab::values())],
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
            'tab' => __('account.notifications.filter'),
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

    /** Filtre de la page /notifications ; `all` ou absent → aucun. */
    public function tab(): ?NotificationTab
    {
        $tab = NotificationTab::tryFrom((string) $this->validated('tab'));

        return $tab === NotificationTab::All ? null : $tab;
    }
}
