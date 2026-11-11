<?php

declare(strict_types=1);

namespace App\Application\Alerts\Support;

/**
 * Étapes du formulaire /signaler (P5-04, Q11 a : « Étape n sur 6 », signaler-et-sos.md) et champs de la charge utile
 * `POST signalements` portés par chacune. Source unique pour la vue (stepper, étape rouverte après une erreur) et pour
 * le composant Alpine `xmReport` (validation par étape), qui la reçoit en JSON : aucune liste recopiée côté client.
 */
final class ReportSteps
{
    /** @var array<string, list<string>> clé d'étape (alerts.report.steps.*) → champs de StoreReportRequest */
    public const FIELDS = [
        'type' => ['type', 'category'],
        'facts' => ['description', 'occurred_on'],
        'needs' => ['needs', 'zone_id', 'commune_id', 'lat', 'lng', 'accuracy'],
        'evidence' => ['attachments'],
        'contact' => ['anonymous', 'contactable', 'contact', 'preferred_channel'],
        'review' => ['idempotency_key'],
    ];

    /**
     * Cible du lien de <x-error-summary> d'un champ sans élément propre : la position (champs cachés lat, lng, accuracy)
     * → bloc `#f-position` ; la clé d'idempotence (contenu modifié après un envoi, ReportReplay) → bouton `#f-send`.
     *
     * @var array<string, string>
     */
    public const SUMMARY_TARGETS = ['lat' => 'position', 'lng' => 'position', 'accuracy' => 'position', 'idempotency_key' => 'send'];

    private function __construct() {}

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::FIELDS);
    }

    public static function total(): int
    {
        return count(self::FIELDS);
    }

    /**
     * Libellés du stepper, dans l'ordre.
     *
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(static fn (string $key): string => (string) __('alerts.report.steps.'.$key), self::keys());
    }

    /** Numéro (1..n) de l'étape qui porte un champ (`needs.0` → besoins) ; dernière étape pour un champ inconnu. */
    public static function stepOf(string $field): int
    {
        $base = explode('.', $field, 2)[0];
        $position = 1;

        foreach (self::FIELDS as $fields) {
            if (in_array($base, $fields, true)) {
                return $position;
            }

            $position++;
        }

        return self::total();
    }

    /**
     * Étape à rouvrir après un envoi refusé : la première qui porte une erreur, la première sinon.
     *
     * @param  iterable<string>  $fields
     */
    public static function firstWithErrors(iterable $fields): int
    {
        $steps = [];

        foreach ($fields as $field) {
            $steps[] = self::stepOf((string) $field);
        }

        return $steps === [] ? 1 : min($steps);
    }

    /**
     * Messages groupés par champ de base (`attachments.1` → `attachments`) pour <x-error-summary>, dont les liens visent
     * l'identifiant `f-{champ}` des champs de la vue (SUMMARY_TARGETS pour les champs sans élément propre).
     *
     * @param  array<string, list<string>>  $messages
     * @return array<string, list<string>>
     */
    public static function summary(array $messages): array
    {
        $grouped = [];

        foreach ($messages as $field => $list) {
            $base = explode('.', (string) $field, 2)[0];
            $base = self::SUMMARY_TARGETS[$base] ?? $base;
            $grouped[$base] = [...($grouped[$base] ?? []), ...array_values($list)];
        }

        return $grouped;
    }

    /**
     * Configuration du composant Alpine : clé, libellé et champs de chaque étape.
     *
     * @return list<array{key: string, label: string, fields: list<string>}>
     */
    public static function forClient(): array
    {
        return array_map(static fn (string $key): array => [
            'key' => $key,
            'label' => (string) __('alerts.report.steps.'.$key),
            'fields' => self::FIELDS[$key],
        ], self::keys());
    }
}
