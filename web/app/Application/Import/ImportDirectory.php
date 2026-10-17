<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;

/**
 * annuaire.json → directory_contacts (role via DirectoryRole::tryFromLabel, active → is_active,
 * zone / commune → FK). E-mails et téléphones sont des données personnelles : la collection n'est
 * importée qu'hors production ou avec --with-demo. Clé naturelle : e-mail, sinon nom + rôle.
 */
final class ImportDirectory extends AbstractImporter
{
    public static function collection(): string
    {
        return 'annuaire';
    }

    public static function holdsPersonalData(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $name = $this->required($row['name'] ?? null, 'name', 120);
        $roleLabel = $this->required($row['role'] ?? null, 'role');
        $role = DirectoryRole::tryFromLabel($roleLabel) ?? $this->reject(sprintf('rôle d’annuaire inconnu « %s »', $roleLabel));
        $email = $this->text($row['email'] ?? null, 190);
        $email = $email === null ? null : mb_strtolower($email);

        $zoneId = $this->ctx->zoneId($row['zone'] ?? null);
        $communeId = $this->ctx->communeId($row['commune'] ?? null, $zoneId);

        $contact = $this->ctx->findContact((string) $email, $name, $role) ?? new DirectoryContact;

        $contact->fill([
            'name' => $name,
            'role' => $role,
            'org' => $this->text($row['org'] ?? null, 120),
            'phone' => $this->text($row['phone'] ?? null, 30),
            'email' => $email,
            'whatsapp' => $this->text($row['whatsapp'] ?? null, 30),
            'zone_id' => $zoneId,
            'commune_id' => $communeId,
            'availability' => $this->text($row['availability'] ?? null, 120),
            'is_active' => (bool) ($row['active'] ?? true),
            'is_public' => true,
        ]);

        // Compte lié : la personne de l'annuaire qui possède aussi un compte (même e-mail ou même nom).
        $contact->user_id ??= ($email !== null ? $this->ctx->userIdByEmail($email) : null) ?? $this->ctx->userIdByName($name);
        $contact->deleted_at = null;
        $contact->save();

        $this->ctx->remember('contacts', (int) ($row['id'] ?? 0), (int) $contact->getKey());
        $this->track($contact);
    }
}
