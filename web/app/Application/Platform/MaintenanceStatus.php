<?php

declare(strict_types=1);

namespace App\Application\Platform;

use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Models\MaintenanceWindow;
use App\Models\User;
use App\Support\Settings;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * État du mode maintenance applicatif (RG-14, P2-11, pas `artisan down`) : bascule manuelle `maintenance` des
 * paramètres **ou** fenêtre `maintenance_windows` active et en cours. Point d'accès unique, lu par le middleware
 * EnsureNotInMaintenance, par AuthenticateUser (MaintenanceActive) et par la page 503 (message, dates).
 * P13-06..08 : annonce préalable d'une fenêtre à venir (bandeaux), état public de `GET /api/v1/status` (cache court,
 * vidé par chaque cas d'usage d'écriture : Enable / Disable / Schedule / CancelMaintenanceWindow, message).
 */
final class MaintenanceStatus
{
    /** Clés des paramètres (settings, groupe maintenance) : bascule manuelle, message, dates annoncées. */
    public const SETTING_FLAG = 'maintenance';

    public const SETTING_MESSAGE = 'maint_msg';

    public const SETTING_START = 'maint_start';

    public const SETTING_END = 'maint_end';

    /** Rôles qui se connectent et travaillent pendant la maintenance (RG-14). */
    public const ALLOWED_ROLES = ['admin', 'superadmin'];

    /** Longueur du message public (`maint_msg`, `maintenance_windows.message`, éditeur P13-06). */
    public const MESSAGE_MAX_LENGTH = 280;

    /** Délai d'annonce préalable maximal d'une fenêtre (heures). */
    public const MAX_ANNOUNCE_HOURS = 168;

    /** État public de `GET /api/v1/status` : cache court (sondé chaque minute par la page de maintenance et le mobile). */
    public const STATUS_CACHE_KEY = 'maintenance:public-status';

    public const STATUS_CACHE_SECONDS = 15;

    private function __construct() {}

    /**
     * La maintenance est-elle active (bascule manuelle ou fenêtre en cours) ?
     */
    public static function active(?Carbon $at = null): bool
    {
        if (Settings::bool(self::SETTING_FLAG, false)) {
            return true;
        }

        return MaintenanceWindow::isUnderMaintenance($at);
    }

    /**
     * La personne peut-elle utiliser la plateforme malgré la maintenance ?
     */
    public static function isExempt(?User $user): bool
    {
        return $user !== null && $user->hasAnyRole(self::ALLOWED_ROLES);
    }

    /**
     * Fenêtre active en cours, la plus récemment commencée en premier (null si seule la bascule est levée).
     */
    public static function currentWindow(?Carbon $at = null): ?MaintenanceWindow
    {
        return MaintenanceWindow::query()->ongoing($at)->orderByDesc('starts_at')->orderByDesc('id')->first();
    }

    /**
     * Photographie complète (une seule lecture de la fenêtre en cours) : état, message, dates.
     */
    public static function snapshot(?Carbon $at = null): MaintenanceSnapshot
    {
        $window = self::currentWindow($at);
        $active = $window !== null || Settings::bool(self::SETTING_FLAG, false);
        $announcedEnd = self::announcedEnd($window);

        return new MaintenanceSnapshot(
            active: $active,
            window: $window,
            message: self::message($window),
            startsAt: self::startsAt($window),
            endsAt: self::endsAt($window, $at),
            overdue: $active && $announcedEnd !== null && $announcedEnd->lte($at ?? now()),
        );
    }

    /**
     * Message affiché aux visiteur·ses : celui de la fenêtre en cours, sinon `maint_msg`, sinon le texte par défaut.
     */
    public static function message(?MaintenanceWindow $window): string
    {
        if ($window !== null && trim((string) $window->message) !== '') {
            return $window->displayMessage();
        }

        $custom = trim(Settings::string(self::SETTING_MESSAGE, ''));

        return $custom !== '' ? $custom : (string) __('maintenance.default_message');
    }

    /**
     * Début de la maintenance en cours (fenêtre, sinon paramètre `maint_start`), si connu.
     */
    public static function startsAt(?MaintenanceWindow $window): ?Carbon
    {
        return $window?->starts_at ?? self::parseDate(Settings::string(self::SETTING_START, ''));
    }

    /**
     * Reprise prévue (fenêtre, sinon paramètre `maint_end`), si connue et à venir.
     */
    public static function endsAt(?MaintenanceWindow $window, ?Carbon $at = null): ?Carbon
    {
        $end = self::announcedEnd($window);

        return $end !== null && $end->gt($at ?? now()) ? $end : null;
    }

    /** Reprise annoncée (fenêtre, sinon `maint_end`), même déjà passée : sert à repérer un dépassement. */
    private static function announcedEnd(?MaintenanceWindow $window): ?Carbon
    {
        return $window?->ends_at ?? self::parseDate(Settings::string(self::SETTING_END, ''));
    }

    /**
     * Fenêtre à venir entrée dans son délai d'annonce (bandeaux P13-08), la plus proche ; null sinon.
     */
    public static function announcement(?Carbon $at = null): ?MaintenanceWindow
    {
        $at ??= now();

        return MaintenanceWindow::query()
            ->upcoming($at)
            ->where('announce_hours', '>', 0)
            ->where('starts_at', '<=', $at->copy()->addHours(self::MAX_ANNOUNCE_HOURS))
            ->limit(10)
            ->get()
            ->first(static fn (MaintenanceWindow $window): bool => $window->isAnnounced($at));
    }

    /**
     * Annonce pour un gabarit (public, membre, administration) : jamais d'exception (base indisponible = aucun bandeau).
     *
     * @return array{id: int, starts_at: Carbon, ends_at: Carbon, message: ?string}|null
     */
    public static function notice(): ?array
    {
        try {
            $window = self::announcement();
        } catch (Throwable) {
            return null;
        }

        if ($window === null) {
            return null;
        }

        $message = trim((string) $window->message);

        return [
            'id' => (int) $window->getKey(),
            'starts_at' => $window->starts_at,
            'ends_at' => $window->ends_at,
            'message' => $message === '' ? null : $message,
        ];
    }

    /**
     * Numéros d'urgence nationaux : même source que `/urgences` et `GET /api/v1/urgences` (ListEmergencyContacts).
     *
     * @return list<array{label: string, number: string}>
     */
    public static function emergencyNumbers(): array
    {
        try {
            return array_values(KnowledgeBaseAnswerer::emergencyNumbers());
        } catch (Throwable) {
            // Base injoignable (503 générique) : numéros de la configuration, jamais une page sans numéro.
            return array_values(array_filter(
                (array) config('xomiton.assistant.emergency_numbers', []),
                static fn (mixed $entry): bool => is_array($entry) && is_string($entry['label'] ?? null) && is_string($entry['number'] ?? null),
            ));
        }
    }

    /**
     * Corps de `GET /api/v1/status` (P13-08, docs/API.md §5) : état, message public, dates, dépassement de la reprise
     * annoncée, annonce, numéros d'urgence et fuseau d'affichage des heures (`xomiton.display_timezone`). Mis en cache
     * 15 s : la page de maintenance et l'application mobile le sondent chaque minute.
     *
     * @return array<string, mixed>
     */
    public static function publicStatus(): array
    {
        $payload = Cache::remember(self::STATUS_CACHE_KEY, self::STATUS_CACHE_SECONDS, static function (): array {
            $snapshot = self::snapshot();
            $notice = $snapshot->active ? null : self::notice();

            return [
                'status' => $snapshot->active ? 'maintenance' : 'ok',
                'maintenance' => [
                    'active' => $snapshot->active,
                    'message' => $snapshot->active ? $snapshot->message : null,
                    'starts_at' => $snapshot->active ? $snapshot->startsAt?->toIso8601String() : null,
                    'ends_at' => $snapshot->active ? $snapshot->endsAt?->toIso8601String() : null,
                    'retry_after' => $snapshot->active ? $snapshot->retryAfterSeconds() : null,
                    'overdue' => $snapshot->overdue,
                ],
                'announcement' => $notice === null ? null : [
                    'starts_at' => $notice['starts_at']->toIso8601String(),
                    'ends_at' => $notice['ends_at']->toIso8601String(),
                    'message' => $notice['message'],
                ],
                'emergency_numbers' => self::emergencyNumbers(),
            ];
        });

        return $payload + ['timezone' => MaintenancePage::timezone(), 'server_time' => now()->toIso8601String()];
    }

    /** Vide l'état public mis en cache (après chaque écriture). */
    public static function forget(): void
    {
        Cache::forget(self::STATUS_CACHE_KEY);
    }

    private static function parseDate(string $value): ?Carbon
    {
        if (trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (InvalidFormatException) {
            return null;
        }
    }
}
