<?php

declare(strict_types=1);

namespace App\Http\Requests\Search;

use App\Domain\Search\SearchTerm;
use App\Domain\Search\SearchType;
use App\Models\User;
use App\Support\ListingQuery;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Recherche membre (P8-13) : page `/recherche` et `GET /api/v1/search[/suggestions]`. Lecture tolérante comme les
 * autres listes (BrowseDirectoryRequest) — jamais de 422, dont le message pourrait recopier la saisie : terme assaini
 * (caractères de contrôle retirés, espaces normalisés) et tronqué à `xomiton.search.max_length` ; `type` d'une
 * famille connue (sinon vue d'ensemble) ; `page` entier ≥ 1. Le terme n'existe ensuite que sous la forme d'un
 * SearchTerm (jamais journalisé ni sérialisé).
 */
final class SearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->viewerOrNull() instanceof User;
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
        $user = $this->viewerOrNull();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    public function term(): SearchTerm
    {
        return SearchTerm::of(ListingQuery::search($this->query('q'), self::maxLength()));
    }

    public function type(): ?SearchType
    {
        $type = $this->query('type');

        return is_string($type) ? SearchType::tryFrom($type) : null;
    }

    public function page(): int
    {
        $value = $this->query('page');
        $page = is_string($value) ? filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;

        return $page === false ? 1 : (int) $page;
    }

    public static function maxLength(): int
    {
        return max(1, (int) config('xomiton.search.max_length', 80));
    }

    private function viewerOrNull(): ?User
    {
        $user = $this->user() ?? $this->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
