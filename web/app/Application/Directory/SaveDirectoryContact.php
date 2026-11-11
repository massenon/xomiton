<?php

declare(strict_types=1);

namespace App\Application\Directory;

use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Rules\PhoneBj;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Création et modification d'une fiche de l'annuaire (P11-04, `directory.manage`) à partir de données déjà validées par
 * DirectoryContactRules : téléphone et WhatsApp à la forme canonique béninoise (le texte saisi est gardé si la règle
 * `phone_bj` est désactivée), e-mail en minuscules. Journal d'audit « directory.created » / « directory.updated » (nom,
 * rôle et champs modifiés ; jamais les coordonnées). Le routage RG-04 lit l'annuaire à chaque alerte : une écriture
 * prend effet tout de suite (DirectoryContactObserver vide aussi le cache des contacts d'urgence).
 */
final class SaveDirectoryContact
{
    /** Colonnes écrites par l'administration (les notes internes et l'horodatage ne passent pas par ici). */
    public const FIELDS = ['name', 'role', 'org', 'availability', 'phone', 'email', 'whatsapp', 'zone_id', 'commune_id', 'is_active', 'is_public', 'is_emergency', 'is_national_focal', 'user_id'];

    /**
     * @param  array<string, mixed>  $validated
     */
    public function __invoke(User $by, array $validated, ?DirectoryContact $contact = null): DirectoryContact
    {
        $contact === null
            ? Gate::forUser($by)->authorize('create', DirectoryContact::class)
            : Gate::forUser($by)->authorize('update', $contact);

        $created = $contact === null;
        $contact = $this->persist($validated, $contact);
        $changed = $created ? [] : array_values(array_intersect(self::FIELDS, array_keys($contact->getChanges())));

        Audit::log(
            $created ? 'directory.created' : 'directory.updated',
            $contact,
            $created
                ? sprintf('nom=%s ; rôle=%s', $contact->name, $contact->role->value)
                : sprintf('nom=%s ; champs=%s', $contact->name, $changed === [] ? '-' : implode(',', $changed)),
            $by,
        );

        return $contact;
    }

    /**
     * Écrit la fiche sans autorisation ni audit (l'import CSV contrôle les droits une fois et n'écrit qu'une ligne
     * d'audit pour tout le fichier).
     *
     * @param  array<string, mixed>  $validated
     */
    public function persist(array $validated, ?DirectoryContact $contact = null): DirectoryContact
    {
        $contact ??= new DirectoryContact;
        $role = DirectoryRole::from((string) $validated['role']);
        $zoneId = self::id($validated['zone_id'] ?? null);

        $contact->fill([
            'name' => trim((string) $validated['name']),
            'role' => $role,
            'org' => self::text($validated['org'] ?? null),
            'availability' => self::text($validated['availability'] ?? null),
            'phone' => self::phone($validated['phone'] ?? null),
            'email' => ($email = self::text($validated['email'] ?? null)) === null ? null : mb_strtolower($email),
            'whatsapp' => self::phone($validated['whatsapp'] ?? null),
            'zone_id' => $zoneId,
            'commune_id' => $zoneId === null ? null : self::id($validated['commune_id'] ?? null),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'is_public' => (bool) ($validated['is_public'] ?? true),
            'is_emergency' => (bool) ($validated['is_emergency'] ?? false),
            'is_national_focal' => $role === DirectoryRole::PointFocal && (bool) ($validated['is_national_focal'] ?? false),
            'user_id' => self::id($validated['user_id'] ?? null),
        ]);
        $contact->deleted_at = null;
        $contact->save();

        return $contact;
    }

    private static function phone(mixed $value): ?string
    {
        $text = self::text($value);

        return $text === null ? null : PhoneBj::format($text);
    }

    private static function text(mixed $value): ?string
    {
        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private static function id(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }
}
