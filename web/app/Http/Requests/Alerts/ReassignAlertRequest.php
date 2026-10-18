<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Models\Alert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Réattribution manuelle d'une alerte (P10, `alerts.manage`) vers un contact existant de l'annuaire. */
final class ReassignAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        return $alert instanceof Alert && ($this->user()?->can('reassign', $alert) ?? false);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer', Rule::exists('directory_contacts', 'id')->whereNull('deleted_at')],
        ];
    }
}
