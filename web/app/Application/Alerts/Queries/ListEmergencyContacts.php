<?php

declare(strict_types=1);

namespace App\Application\Alerts\Queries;

use App\Application\Alerts\Results\EmergencyContacts;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Application\Shared\Referentials;
use App\Enums\DirectoryRole;
use App\Models\DirectoryContact;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Source unique des contacts d'urgence : `GET /api/v1/urgences` (P5-06), page `/urgences` (P4-06) et cache hors ligne
 * du mobile (P15-04). Écart consigné (plan P5-06) : ni la table `emergency_services` ni la colonne
 * `directory_contacts.is_emergency` annoncées par P1-05 n'existent ; les numéros nationaux sont donc ceux de
 * l'assistant (paramètre `emergency_numbers`, sinon `xomiton.assistant.emergency_numbers`).
 *
 * Contacts servis hors connexion (Q19 : « l'annuaire complet reste membre » ; prototype urgences.php l.8) : fiches
 * actives `is_public` **et** d'un rôle d'urgence (point focal, juriste, psychologue) ou point focal national ; jamais
 * pair·e animateur·rice, responsable de réseau, personnel identifié, etc. Avec `?zone=` : fiches du département puis
 * fiches nationales (sans département ou point focal national). Lecture seule (ADR-29).
 *
 * Cache serveur comme les référentiels (clé versionnée, pilote `file` sur cPanel) : invalidé par toute écriture sur
 * une fiche (App\Observers\DirectoryContactObserver) et par la version des référentiels (noms de zone et de commune).
 */
final class ListEmergencyContacts
{
    /** Rôles d'urgence servis hors connexion, en attendant `directory_contacts.is_emergency` (P1-05). */
    public const EMERGENCY_ROLES = [DirectoryRole::PointFocal, DirectoryRole::Juriste, DirectoryRole::Psychologue];

    /** Plafond de fiches renvoyées ; au-delà, `truncated` le signale (meta.truncated de `GET urgences`). */
    public const MAX_CONTACTS = 100;

    public const VERSION_KEY = 'emergency_contacts:version';

    public function __construct(private readonly Referentials $referentials) {}

    public function __invoke(?Zone $zone = null): EmergencyContacts
    {
        /** @var Collection<int, DirectoryContact> $contacts */
        $contacts = Cache::remember($this->key($zone), Referentials::ttlSeconds(), fn (): Collection => $this->query($zone));

        return new EmergencyContacts(
            KnowledgeBaseAnswerer::emergencyNumbers(),
            $contacts->take(self::MAX_CONTACTS)->values(),
            $zone,
            truncated: $contacts->count() > self::MAX_CONTACTS,
        );
    }

    /** Rend obsolètes toutes les entrées en cache (une écriture sur l'annuaire). */
    public static function invalidate(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);
    }

    private static function version(): int
    {
        $version = Cache::get(self::VERSION_KEY);

        return is_int($version) && $version > 0 ? $version : 1;
    }

    private function key(?Zone $zone): string
    {
        return sprintf('emergency_contacts:v%d:r%d:%s', self::version(), $this->referentials->version(), $zone?->getKey() ?? 'all');
    }

    /**
     * Une fiche de plus que le plafond, pour signaler la troncature.
     *
     * @return Collection<int, DirectoryContact>
     */
    private function query(?Zone $zone): Collection
    {
        return DirectoryContact::query()
            ->active()
            ->public()
            ->where(fn (Builder $query) => $query
                ->whereIn('role', array_map(static fn (DirectoryRole $role): string => $role->value, self::EMERGENCY_ROLES))
                ->orWhere('is_national_focal', true))
            ->with(['zone', 'commune'])
            ->when($zone !== null, fn (Builder $query) => $query->where(fn (Builder $scope) => $scope
                ->where('zone_id', $zone?->getKey())
                ->orWhereNull('zone_id')
                ->orWhere('is_national_focal', true)))
            ->when($zone !== null, fn (Builder $query) => $query->orderByRaw('case when zone_id = ? then 0 else 1 end', [$zone?->getKey()]))
            ->orderByDesc('is_national_focal')
            ->orderBy('name')
            ->orderBy('id')
            ->limit(self::MAX_CONTACTS + 1)
            ->get();
    }
}
