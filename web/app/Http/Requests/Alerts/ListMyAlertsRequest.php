<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Enums\AlertType;
use App\Http\Resources\Alerts\AlertResource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET signalements/mine` (P5-06, `auth:sanctum`) et page membre « Mes signalements » (P8-12) : filtre facultatif par
 * type en valeurs API (Q29 : report | need | sos), pagination par page (20 par défaut, 50 au plus).
 */
final class ListMyAlertsRequest extends FormRequest
{
    public const PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'type' => ['nullable', 'string', Rule::in(array_values(AlertResource::API_TYPES))],
            'per_page' => ['nullable', 'integer', 'between:1,'.self::MAX_PER_PAGE],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function alertType(): ?AlertType
    {
        $type = array_search($this->query('type'), AlertResource::API_TYPES, true);

        return is_string($type) ? AlertType::from($type) : null;
    }

    public function perPage(): int
    {
        return $this->filled('per_page') ? (int) $this->query('per_page') : self::PER_PAGE;
    }
}
