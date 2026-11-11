<?php

declare(strict_types=1);

namespace App\Application\Alerts\Results;

use App\Domain\Alerts\ValueObjects\RoutingDecision;
use App\Models\Alert;
use App\Models\DirectoryContact;

/**
 * Résultat d'une création d'alerte : l'alerte (d'origine en cas de rejeu, ADR-32), `duplicate`, le contact routé et,
 * pour un SOS anonyme, le jeton `X-Sos-Token` à rendre dans la réponse 201 (`sos_token`, Q52 b).
 */
final readonly class CreatedAlert
{
    public function __construct(
        public Alert $alert,
        public bool $duplicate,
        public ?DirectoryContact $routedContact,
        public ?RoutingDecision $routing = null,
        public ?string $sosToken = null,
    ) {}

    /**
     * Contact routé tel que la réponse de création le montre (parité api.php l.65-66) : nom, téléphone et WhatsApp
     * seulement si la fiche est publique, sinon le rôle seul.
     *
     * @return array{role: string, name?: string, phone?: string|null, whatsapp?: string|null}|null
     */
    public function routedPayload(): ?array
    {
        $contact = $this->routedContact;

        if ($contact === null) {
            return null;
        }

        $payload = ['role' => $contact->role->label()];

        if ($contact->is_public) {
            $payload['name'] = (string) $contact->name;
            $payload['phone'] = $contact->phone;
            $payload['whatsapp'] = $contact->whatsapp;
        }

        return $payload;
    }
}
