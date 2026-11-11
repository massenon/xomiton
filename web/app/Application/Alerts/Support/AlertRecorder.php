<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

use App\Domain\Alerts\Exceptions\IdempotencyKeyConflict;
use App\Enums\AlertStatus;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\AlertStatusChange;
use App\Models\User;
use App\Rules\PhoneBj;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;

/**
 * Écriture commune des créations d'alerte (CreateReport, TriggerSos, RequestCallback) avec la sémantique ADR-32 :
 * une clé d'idempotence déjà connue renvoie l'alerte d'origine (aucune ligne, aucun événement, même code de suivi),
 * y compris en cas de course (contrainte unique `alerts.idempotency_key`) ; une clé prise par une alerte d'un autre
 * type lève IdempotencyKeyConflict (409, comme P5-09). Toute création écrit l'historique
 * `null → nouveau` (DATABASE_DICTIONARY §5 `alert_status_changes`).
 */
final class AlertRecorder
{
    /** Espace de noms des clés dérivées d'un SOS dont la clé a déjà servi pour une autre alerte (UUID v5). */
    private const SOS_KEY_NAMESPACE = 'xomiton:alerts:sos-key/';

    /**
     * Alerte d'origine d'un rejeu (même clé, même type) ; une clé prise par une alerte d'un autre type est un conflit.
     *
     * @throws IdempotencyKeyConflict
     */
    public function existing(string $idempotencyKey, AlertType $type): ?Alert
    {
        $alert = Alert::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($alert === null) {
            return null;
        }

        if ($alert->type !== $type) {
            Log::warning('alerts.idempotency_conflict', ['expected' => $type->value, 'found' => $alert->type->value]);

            throw new IdempotencyKeyConflict;
        }

        Log::info('alerts.idempotent_replay', ['ulid' => $alert->ulid]);

        return $alert;
    }

    /**
     * Clé de repli d'un SOS dont la clé appartient déjà à une autre alerte : déterministe, donc les rejeux de ce SOS
     * restent dédoublonnés (ADR-32) sans jamais le perdre ni le confondre avec l'autre alerte.
     */
    public static function sosFallbackKey(string $idempotencyKey): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::SOS_KEY_NAMESPACE.$idempotencyKey)->toString();
    }

    /**
     * Crée l'alerte et sa première ligne d'historique dans une transaction ; `$then` écrit les lignes filles (pièces
     * jointes) dans la même transaction.
     *
     * @param  array<string, mixed>  $attributes
     * @param  (Closure(Alert): void)|null  $then
     * @return array{0: Alert, 1: bool} alerte, et vrai si c'est un rejeu (alerte d'origine)
     */
    public function create(array $attributes, ?Closure $then = null): array
    {
        $key = $attributes['idempotency_key'] ?? null;

        try {
            $alert = DB::transaction(function () use ($attributes, $then): Alert {
                $alert = Alert::query()->create([...$attributes, 'status' => AlertStatus::New]);

                AlertStatusChange::query()->create([
                    'alert_id' => $alert->getKey(),
                    'user_id' => null,
                    'from_status' => null,
                    'to_status' => AlertStatus::New,
                ]);

                if ($then !== null) {
                    $then($alert);
                }

                return $alert;
            });
        } catch (UniqueConstraintViolationException $exception) {
            $type = $attributes['type'] ?? null;
            $original = is_string($key) && $type instanceof AlertType ? $this->existing($key, $type) : null;

            if ($original === null) {
                throw $exception;
            }

            return [$original, true];
        }

        return [$alert, false];
    }

    /**
     * Identité de l'auteur·rice (RG-01, RG-02) : anonyme ⇒ ni compte, ni pseudo, ni appareil (`sync_devices.user_id`
     * relierait l'alerte au compte ; le reçu `sync_outbox_receipts` suffit au rejeu P9) ; sinon le compte, son pseudo
     * et l'appareil d'envoi.
     *
     * @return array{is_anonymous: bool, user_id: int|null, pseudo: string|null, device_id: int|null}
     */
    public static function author(?int $userId, bool $anonymous, ?int $deviceId = null): array
    {
        $user = $anonymous || $userId === null ? null : User::query()->find($userId);

        if ($user === null) {
            return ['is_anonymous' => true, 'user_id' => null, 'pseudo' => null, 'device_id' => null];
        }

        return ['is_anonymous' => false, 'user_id' => (int) $user->getKey(), 'pseudo' => $user->pseudo, 'device_id' => $deviceId];
    }

    /** Moyen de contact stocké (chiffré) : téléphone béninois au format canonique, e-mail en minuscules. */
    public static function normalizeContact(?string $contact): ?string
    {
        $contact = $contact === null ? '' : trim($contact);

        if ($contact === '') {
            return null;
        }

        if (filter_var($contact, FILTER_VALIDATE_EMAIL) !== false) {
            return mb_strtolower($contact);
        }

        return PhoneBj::normalize($contact) ?? $contact;
    }

    /** Coordonnée stockée en texte chiffré (6 décimales, comme l'import du prototype). */
    public static function coordinate(?float $value): ?string
    {
        return $value === null ? null : number_format($value, 6, '.', '');
    }

    /**
     * Slugs distincts, dans l'ordre fourni (l'ordre compte pour le routage, api.php l.16).
     *
     * @param  iterable<mixed>  $needs
     * @return list<string>
     */
    public static function needs(iterable $needs): array
    {
        $slugs = [];

        foreach ($needs as $need) {
            if (is_string($need) && ($need = trim($need)) !== '' && ! in_array($need, $slugs, true)) {
                $slugs[] = $need;
            }
        }

        return $slugs;
    }
}
