<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Enums\AlertPriority;
use App\Enums\AlertSource;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\AlertNote;
use App\Models\Need;
use App\Models\ViolenceType;

/**
 * alertes.json → alerts (anonymous → is_anonymous, created unix → created_at, status_at → acknowledged_at,
 * routed_to → directory_contacts via la correspondance de l'annuaire, needs json, lat / lng / description /
 * contact chiffrés, source, tracking_code généré) et notes[] → alert_notes (corps chiffré).
 * Idempotence : idempotency_key dérivée de l'identifiant du prototype.
 */
final class ImportAlerts extends AbstractImporter
{
    /** @var array<string, int>|null slugs des besoins connus */
    private ?array $needSlugs = null;

    /** @var array<string, int>|null slugs des types de violence connus */
    private ?array $violenceSlugs = null;

    public static function collection(): string
    {
        return 'alertes';
    }

    public static function isDemo(): bool
    {
        return true;
    }

    protected function importRow(array $row, int $index): void
    {
        $prototypeId = $this->prototypeId($row, $index);
        $type = AlertType::tryFrom((string) ($row['type'] ?? ''))
            ?? $this->reject(sprintf('type d’alerte inconnu « %s »', (string) ($row['type'] ?? '')));
        $status = AlertStatus::tryFrom((string) ($row['status'] ?? 'nouveau'))
            ?? $this->reject(sprintf('statut inconnu « %s »', (string) ($row['status'] ?? '')));
        $createdAt = $this->ctx->dateTime($row['created'] ?? null) ?? $this->reject('horodatage « created » absent ou invalide');
        $statusAt = $this->ctx->dateTime($row['status_at'] ?? null);

        $isAnonymous = (bool) ($row['anonymous'] ?? true);
        $pseudo = $isAnonymous ? null : $this->text($row['pseudo'] ?? null, 40);
        $userId = $isAnonymous ? null : ($this->ctx->userId($row['user'] ?? null) ?? $this->ctx->userIdByPseudo($pseudo));

        $routedTo = null;

        if (! empty($row['routed_to'])) {
            $routedTo = $this->ctx->contactId($row['routed_to']);

            if ($routedTo === null) {
                $this->ctx->note(sprintf('alerte #%s : contact d’annuaire #%s inconnu, alerte importée sans routage', (string) $prototypeId, (string) $row['routed_to']));
            }
        }

        $zoneId = $this->ctx->zoneId($row['zone'] ?? null);

        $alert = Alert::query()->firstOrNew(['idempotency_key' => ImportKeys::uuid('alertes', $prototypeId)]);

        $alert->fill([
            'type' => $type,
            'user_id' => $userId,
            'is_anonymous' => $isAnonymous,
            'pseudo' => $pseudo,
            'category' => $this->category($row['category'] ?? null, $prototypeId),
            'description' => $this->text($row['description'] ?? null, 4000),
            'needs' => $this->needs($row['needs'] ?? [], $prototypeId),
            'zone_id' => $zoneId,
            'commune_id' => $this->ctx->communeId($row['commune'] ?? null, $zoneId),
            'lat' => $this->coordinate($row['lat'] ?? null),
            'lng' => $this->coordinate($row['lng'] ?? null),
            'contact' => $this->text($row['contact'] ?? null, 190),
            'status' => $status,
            'priority' => AlertPriority::tryFrom((string) ($row['priority'] ?? '')) ?? ($type->isSos() ? AlertPriority::Critical : AlertPriority::Normal),
            'routed_to' => $routedTo,
            'source' => AlertSource::tryFrom((string) ($row['source'] ?? 'web')) ?? AlertSource::Web,
        ]);

        if ($status !== AlertStatus::New && $statusAt !== null) {
            $alert->acknowledged_at = $statusAt;
        }

        if ($status === AlertStatus::Closed) {
            $alert->closed_at ??= $statusAt ?? $createdAt;
        }

        $alert->created_at = $createdAt;
        $alert->save();

        $this->importNotes($alert, is_array($row['notes'] ?? null) ? $row['notes'] : []);

        $this->ctx->remember('alerts', $prototypeId, (int) $alert->getKey());
        $this->track($alert);
    }

    /**
     * Notes internes de l'administration du prototype (`[{at, by, text, internal?}]`) → alert_notes.
     *
     * @param  list<array<string, mixed>>  $notes
     */
    private function importNotes(Alert $alert, array $notes): void
    {
        foreach ($notes as $note) {
            if (! is_array($note)) {
                continue;
            }

            $body = $this->text($note['text'] ?? null, 1000);

            if ($body === null) {
                continue;
            }

            $createdAt = $this->ctx->dateTime($note['at'] ?? null) ?? $alert->created_at;
            $userId = $this->ctx->userIdByName($this->text($note['by'] ?? null));

            // Le corps est chiffré (non interrogeable) : la note est reconnue par alerte, auteur·rice et horodatage.
            $exists = AlertNote::query()
                ->where('alert_id', $alert->getKey())
                ->where('user_id', $userId)
                ->where('created_at', $createdAt)
                ->exists();

            if ($exists) {
                continue;
            }

            $model = new AlertNote(['alert_id' => $alert->getKey(), 'user_id' => $userId, 'body' => $body]);
            $model->created_at = $createdAt;
            $model->save();
        }
    }

    private function category(mixed $slug, string|int $prototypeId): ?string
    {
        $slug = $this->text($slug, 20);

        if ($slug === null) {
            return null;
        }

        $this->violenceSlugs ??= ViolenceType::query()->pluck('id', 'slug')->map(fn ($id): int => (int) $id)->all();

        if (! isset($this->violenceSlugs[$slug])) {
            $this->ctx->note(sprintf('alerte #%s : catégorie « %s » inconnue, conservée telle quelle', (string) $prototypeId, $slug));
        }

        return $slug;
    }

    /**
     * @return list<string>|null
     */
    private function needs(mixed $needs, string|int $prototypeId): ?array
    {
        if (is_string($needs)) {
            $needs = array_filter(array_map('trim', explode(',', $needs)));
        }

        if (! is_array($needs) || $needs === []) {
            return null;
        }

        $this->needSlugs ??= Need::query()->pluck('id', 'slug')->map(fn ($id): int => (int) $id)->all();
        $kept = [];

        foreach ($needs as $slug) {
            $slug = trim((string) $slug);

            if ($slug === '') {
                continue;
            }

            if (! isset($this->needSlugs[$slug])) {
                $this->ctx->note(sprintf('alerte #%s : besoin « %s » inconnu du référentiel, ignoré', (string) $prototypeId, $slug));

                continue;
            }

            $kept[] = $slug;
        }

        return $kept === [] ? null : array_values(array_unique($kept));
    }

    private function coordinate(mixed $value): ?string
    {
        return is_numeric($value) ? (string) $value : null;
    }
}
