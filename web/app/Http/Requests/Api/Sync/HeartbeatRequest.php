<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Sync;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST heartbeat` (P9-06, docs/API.md §21.5) : signal de vie de l'application (`X-Device-Id`), avec le nombre
 * d'éléments encore dans sa boîte d'envoi (`pending`, facultatif) et sa version (`app_version`, facultatif).
 */
final class HeartbeatRequest extends FormRequest
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
            'pending' => ['sometimes', 'integer', 'min:0'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'pending' => (string) __('sync.fields.pending'),
            'app_version' => (string) __('account.devices.fields.app_version'),
        ];
    }

    public function pending(): ?int
    {
        return $this->has('pending') ? (int) $this->validated('pending') : null;
    }

    public function appVersion(): ?string
    {
        $version = $this->validated('app_version');

        return is_string($version) && trim($version) !== '' ? trim($version) : null;
    }
}
