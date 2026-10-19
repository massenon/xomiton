<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Events\Concerns;

use App\Models\User;

/**
 * Commun aux Form Requests des événements (P8-04) : noms de champs (lang/fr/events.php `api.attributes`, tu) et personne
 * connectée.
 */
trait EventRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = ['title', 'starts_at', 'ends_at', 'place', 'address', 'is_online', 'online_url', 'category_id', 'description', 'group', 'is_public', 'going', 'reminder', 'filter', 'category', 'q', 'per_page'];

        return array_combine($fields, array_map(static fn (string $key): string => (string) __('community.events.api.attributes.'.$key), $fields));
    }

    protected function viewer(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
