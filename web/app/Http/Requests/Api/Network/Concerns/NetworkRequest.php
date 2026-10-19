<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Network\Concerns;

use App\Models\User;

/**
 * Commun aux Form Requests de « Qui suivre et invitations » (P8-06) : noms de champs (lang/fr/community.php
 * `api.network_attributes`, tu, hors catalogue Vue) et personne connectée.
 */
trait NetworkRequest
{
    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $fields = ['user_id', 'q', 'zone', 'role', 'box', 'cursor', 'per_page'];

        return array_combine($fields, array_map(static fn (string $key): string => (string) __('community.api.network_attributes.'.$key), $fields));
    }

    /** Curseur opaque de la page suivante (`meta.next_cursor`, NetworkPage) ; null pour la première page. */
    public function cursor(): ?string
    {
        $cursor = $this->validated('cursor');

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    public function viewer(): User
    {
        $user = $this->user();

        if (! $user instanceof User) {
            abort(401);
        }

        return $user;
    }
}
