<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

use App\Application\Alerts\Data\ReportData;
use App\Enums\ContactChannel;
use App\Models\Alert;

/**
 * Rejeu d'un formulaire /signaler (P5-04, ADR-32) : la clé d'idempotence d'un envoi déjà reçu ne vaut « déjà reçu » que
 * si le contenu n'a pas changé (réponse perdue puis formulaire corrigé). Pendant web de l'empreinte du middleware
 * `idempotent` (422 `idempotency_key_reused` côté API), comparée à l'alerte d'origine : type, catégorie, récit, date,
 * besoins, lieu, position, anonymat, contact et canal. Les preuves ne comptent que si le nouvel envoi en porte (un
 * navigateur vide souvent le champ fichier au retour arrière) ; elles sont ré-encodées, seul leur nombre est comparé.
 */
final class ReportReplay
{
    private function __construct() {}

    public static function matches(Alert $alert, ReportData $data, int $files = 0): bool
    {
        $contact = $data->contactable ? AlertRecorder::normalizeContact($data->contact) : null;
        $channel = $contact === null ? null : ($data->preferredChannel ?? ContactChannel::guessFor($contact));

        $sameContent = $alert->type === $data->type
            && $alert->category === $data->category
            && (string) $alert->description === trim($data->description)
            && $alert->occurred_on?->toDateString() === $data->occurredOn?->toDateString()
            && array_values((array) $alert->needs) === AlertRecorder::needs($data->needs)
            && self::id($alert->zone_id) === $data->zoneId
            && self::id($alert->commune_id) === $data->communeId
            && $alert->lat === AlertRecorder::coordinate($data->lat)
            && $alert->lng === AlertRecorder::coordinate($data->lng)
            && $alert->is_anonymous === $data->isAnonymous()
            && $alert->contact === $contact
            && $alert->preferred_channel === $channel;

        return $sameContent && ($files === 0 || $files === $alert->attachments()->count());
    }

    /** Clé étrangère lue telle que le pilote la rend (entier ou chaîne). */
    private static function id(mixed $value): ?int
    {
        return $value === null ? null : (int) $value;
    }
}
