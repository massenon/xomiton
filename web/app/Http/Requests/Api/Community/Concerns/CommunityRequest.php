<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Community\Concerns;

use App\Models\User;

/**
 * Commun aux Form Requests des groupes et des pages (P8-02, P8-03) : noms de champs (lang/fr/community.php
 * `api.attributes`, tu) et personne connectée.
 */
trait CommunityRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = ['name', 'description', 'privacy', 'zone_id', 'category', 'website', 'role', 'tab', 'q', 'status', 'per_page'];

        return array_combine($fields, array_map(static fn (string $key): string => (string) __('community.api.attributes.'.$key), $fields));
    }

    protected function viewer(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }

    protected function search(): ?string
    {
        $q = $this->validated('q');

        return is_string($q) && trim($q) !== '' ? trim($q) : null;
    }
}
