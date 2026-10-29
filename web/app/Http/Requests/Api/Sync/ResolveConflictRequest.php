<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use App\Models\SyncConflict;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST sync/conflicts/{conflict}/resolve` (docs/API.md §21.5) : `resolution` = `serveur` (garder la valeur du serveur) ou
 * `appareil` (appliquer celle de l'appareil) ; `fusion` + `merged` (texte saisi) réservés à l'administration
 * (SyncConflictPolicy `merge`). L'autorisation est celle de la policy, jouée par ResolveSyncConflict.
 */
final class ResolveConflictRequest extends FormRequest
{
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
            'resolution' => ['required', 'string', 'in:'.implode(',', SyncConflict::RESOLUTIONS)],
            'merged' => ['required_if:resolution,'.SyncConflict::RESOLUTION_MERGE, 'nullable', 'string', 'max:5000'],
        ];
    }

    public function resolution(): string
    {
        return (string) $this->validated('resolution');
    }

    public function merged(): ?string
    {
        $merged = $this->validated('merged');

        return is_string($merged) ? $merged : null;
    }
}
