<?php

declare(strict_types=1);

namespace App\Support;

use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Export CSV des écrans d'administration (P10-02, UI_UX_DESIGN_SYSTEM.md §4.2, ADR-33) : UTF-8 avec BOM (Excel),
 * séparateur « ; », filtres actifs respectés par l'appelant, lecture par paquets (pas de collection en mémoire).
 * Les colonnes sensibles sont **refusées par défaut** : toutes les colonnes `encrypted` de
 * `config('xomiton.encrypted_columns')` (toutes tables), plus récit, GPS, contact, téléphone, notes et commentaires
 * en clair. Un export « complet » doit le demander explicitement (`full: true`), après contrôle de `cases.export`
 * (WithDataTable). Une cellule commençant par = + - @ (formule) est neutralisée par une apostrophe (injection CSV).
 */
final class CsvExport
{
    public const DELIMITER = ';';

    public const BOM = "\u{FEFF}";

    /**
     * Clés sensibles hors chiffrement (ADR-33 : GPS, contact, récit, notes, commentaires), ajoutées aux colonnes
     * `encrypted` de la configuration. Comparaison sur la clé entière ou son dernier segment, sans casse.
     *
     * @var list<string>
     */
    public const SENSITIVE_COLUMNS = [
        'description', 'lat', 'lng', 'latitude', 'longitude', 'gps', 'contact', 'phone', 'notes', 'note', 'comment',
        'comments', 'body', 'email_body',
    ];

    private function __construct() {}

    /**
     * Colonnes jamais exportées sans `full` : SENSITIVE_COLUMNS + chaque colonne de `xomiton.encrypted_columns`.
     *
     * @return list<string>
     */
    public static function excludedColumns(): array
    {
        $excluded = self::SENSITIVE_COLUMNS;

        foreach ((array) config('xomiton.encrypted_columns', []) as $columns) {
            foreach ((array) $columns as $column) {
                $excluded[] = strtolower((string) $column);
            }
        }

        return array_values(array_unique($excluded));
    }

    /**
     * Réponse de téléchargement diffusée par paquets (contrôleur d'export, jamais une action Livewire).
     *
     * @param  array<string, string>  $columns  clé => en-tête affiché
     * @param  iterable<array<string, scalar|\Stringable|null>>  $rows  lignes indexées par clé de colonne
     */
    public static function download(string $filename, array $columns, iterable $rows, bool $full = false): StreamedResponse
    {
        self::guard(array_keys($columns), $full);

        return response()->streamDownload(static function () use ($columns, $rows): void {
            $out = fopen('php://output', 'wb');

            if ($out === false) {
                return;
            }

            fwrite($out, self::BOM);
            fputcsv($out, array_values($columns), self::DELIMITER, '"', '');

            foreach ($rows as $row) {
                $line = [];

                foreach (array_keys($columns) as $key) {
                    $line[] = self::cell($row[$key] ?? null);
                }

                fputcsv($out, $line, self::DELIMITER, '"', '');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store, private']);
    }

    /**
     * Refuse une colonne sensible hors export complet (erreur de programmation, jamais un message à l'écran).
     *
     * @param  list<string>  $keys
     */
    public static function guard(array $keys, bool $full = false): void
    {
        if ($full) {
            return;
        }

        $excluded = self::excludedColumns();

        foreach ($keys as $key) {
            $segment = str_contains($key, '.') ? substr($key, (int) strrpos($key, '.') + 1) : $key;

            if (in_array(strtolower($segment), $excluded, true)) {
                throw new LogicException(sprintf('Colonne « %s » exclue des exports CSV par défaut (ADR-33).', $key));
            }
        }
    }

    /** Valeur de cellule : texte sur une ligne, formule neutralisée. */
    public static function cell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        $text = trim((string) preg_replace('/\s+/u', ' ', (string) $value));

        if ($text === '' || is_numeric($text)) {
            return $text;
        }

        return in_array($text[0], ['=', '+', '-', '@'], true) ? "'".$text : $text;
    }

    /** Nom de fichier daté : `xomiton-journal-20261019.csv`. */
    public static function filename(string $screen): string
    {
        return 'xomiton-'.$screen.'-'.now()->format('Ymd').'.csv';
    }
}
