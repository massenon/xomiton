<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Me;

use App\Application\Account\DeviceData;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/me/devices (P6-09) : `device_uuid` (UUID généré à l'installation), `label` (≤ 80), `platform` (≤ 40),
 * `app_version` (≤ 20).
 */
final class StoreDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid'],
            'label' => ['required', 'string', 'max:80'],
            'platform' => ['sometimes', 'nullable', 'string', 'max:40'],
            'app_version' => ['sometimes', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'device_uuid' => __('account.devices.fields.device_uuid'),
            'label' => __('account.devices.fields.label'),
            'platform' => __('account.devices.fields.platform'),
            'app_version' => __('account.devices.fields.app_version'),
        ];
    }

    public function toData(): DeviceData
    {
        $platform = $this->validated('platform');
        $version = $this->validated('app_version');

        return new DeviceData(
            uuid: (string) $this->validated('device_uuid'),
            label: (string) $this->validated('label'),
            platform: is_string($platform) && trim($platform) !== '' ? $platform : null,
            appVersion: is_string($version) && trim($version) !== '' ? $version : null,
        );
    }
}
