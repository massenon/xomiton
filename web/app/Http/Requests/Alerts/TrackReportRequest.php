<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Suivi par code sur `/suivi` (P5-04, FR-ALT-01) : le code seul, saisie tolérante (normalisée par
 * FindAlertByTrackingCode). Envoyé en POST pour qu'il n'apparaisse ni dans l'historique du navigateur ni dans les
 * journaux du serveur ; limiteur `tracking` (20/h par IP) comme `GET /api/v1/signalements/{code}`.
 */
final class TrackReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'tracking_code' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tracking_code.required' => (string) __('alerts.report.tracking.required'),
            'tracking_code.max' => (string) __('alerts.report.tracking.invalid'),
            'tracking_code.string' => (string) __('alerts.report.tracking.invalid'),
        ];
    }

    public function code(): string
    {
        return trim((string) $this->input('tracking_code'));
    }
}
