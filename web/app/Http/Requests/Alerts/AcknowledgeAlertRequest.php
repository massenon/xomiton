<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts;

use App\Application\Alerts\Support\AcknowledgeLink;
use App\Models\Alert;
use App\Models\DirectoryContact;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Accusé de réception par lien signé (`GET|POST /alertes/{alert:ulid}/accuser?contact=&expires=&signature=`) : signature
 * valide et non expirée, fiche active désignée comme contact routé ou cible d'escalade de l'alerte ; sinon 403 avec le
 * message `alerts.acknowledge.invalid` (vous).
 */
final class AcknowledgeAlertRequest extends FormRequest
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        return $alert instanceof Alert
            && $this->hasValidSignature()
            && AcknowledgeLink::matches($alert, $this->contact());
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [];
    }

    public function alert(): Alert
    {
        /** @var Alert $alert */
        $alert = $this->route('alert');

        return $alert;
    }

    public function contact(): ?DirectoryContact
    {
        $id = $this->query('contact');

        return is_numeric($id) ? DirectoryContact::query()->find((int) $id) : null;
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException((string) __('alerts.acknowledge.invalid'));
    }
}
