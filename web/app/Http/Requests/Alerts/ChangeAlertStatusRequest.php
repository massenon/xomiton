<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Application\Alerts\ChangeAlertStatus;
use App\Enums\AlertStatus;
use App\Models\Alert;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Changement de statut par l'équipe (P10) : `take` pour prendre en charge, `close` pour traiter ou clore. */
final class ChangeAlertStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');
        $status = AlertStatus::tryFrom((string) $this->input('status'));

        if (! $alert instanceof Alert || $this->user() === null) {
            return false;
        }

        return $this->user()->can($status === null ? 'take' : ChangeAlertStatus::ability($status), $alert);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(AlertStatus::class)],
        ];
    }

    public function status(): AlertStatus
    {
        return AlertStatus::from((string) $this->validated('status'));
    }
}
