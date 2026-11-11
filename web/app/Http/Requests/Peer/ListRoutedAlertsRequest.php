<?php

declare(strict_types=1);

namespace App\Http\Requests\Peer;

use App\Application\Cases\Queries\ListRoutedAlerts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `GET pair/alerts?state=open|all&page=&per_page=` (P9-02) : alertes routées vers les fiches d'annuaire du compte.
 */
final class ListRoutedAlertsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('alerts.view_routed') ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'state' => ['nullable', Rule::in([ListRoutedAlerts::STATE_OPEN, ListRoutedAlerts::STATE_ALL])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:'.max(1, (int) config('xomiton.api.max_per_page', 50))],
        ];
    }

    public function state(): string
    {
        return (string) ($this->validated('state') ?? ListRoutedAlerts::STATE_OPEN);
    }

    public function page(): int
    {
        return (int) ($this->validated('page') ?? 1);
    }
}
