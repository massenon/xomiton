<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Models\Alert;
use Illuminate\Foundation\Http\FormRequest;

/** Note interne de l'équipe (P10, `alerts.manage`) : 1000 caractères au plus, jamais journalisée. */
final class AddAlertNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        return $alert instanceof Alert && ($this->user()?->can('note', $alert) ?? false);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.(int) config('xomiton.alerts.note_max', 1000)],
        ];
    }
}
