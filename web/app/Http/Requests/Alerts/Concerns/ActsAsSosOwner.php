<?php

declare(strict_types=1);

namespace App\Http\Requests\Alerts\Concerns;

use App\Application\Alerts\Support\SosOwnerToken;
use App\Models\Alert;
use App\Models\User;

/**
 * Action de la personne à l'origine d'un SOS (`POST sos/{alert:ulid}/cancel|safe`) : le compte propriétaire si
 * l'alerte en a un, sinon l'en-tête `X-Sos-Token` rendu à la création (SOS anonyme, Q52 b).
 */
trait ActsAsSosOwner
{
    public function authorize(): bool
    {
        $alert = $this->route('alert');

        if (! $alert instanceof Alert) {
            return false;
        }

        if ($alert->user_id === null) {
            return SosOwnerToken::matches($alert, $this->sosToken());
        }

        $user = $this->user() ?? $this->user('sanctum');

        return $user instanceof User && (int) $user->getKey() === (int) $alert->user_id;
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

    public function sosToken(): ?string
    {
        $token = $this->header(SosOwnerToken::HEADER);

        return is_string($token) && $token !== '' ? $token : null;
    }

    public function actor(): ?User
    {
        $user = $this->user() ?? $this->user('sanctum');

        return $user instanceof User ? $user : null;
    }
}
