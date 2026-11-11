<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Pages Vue des groupes et des pages (P8-02, P8-03 : `/groupes`, `/groupes/{ulid}`, `/pages`, `/pages/creer`,
 * `/pages/{ulid}`). Lecture seule et tolérante, comme le prototype : un onglet, une catégorie ou une recherche inconnus
 * ou trop longs reviennent à la valeur par défaut (l'API, elle, répond 422).
 */
final class CommunityPageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function viewer(): User
    {
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /**
     * @param  list<string>  $allowed
     */
    public function choice(string $key, array $allowed, string $default): string
    {
        $value = $this->query($key);

        return is_string($value) && in_array($value, $allowed, true) ? $value : $default;
    }

    public function term(string $key = 'q'): ?string
    {
        $value = $this->query($key);

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return mb_substr(trim($value), 0, (int) config('xomiton.community.search_max', 60));
    }
}
