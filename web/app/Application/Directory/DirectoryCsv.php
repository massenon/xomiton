<?php

declare(strict_types=1);

namespace App\Application\Directory;

use Illuminate\Support\Str;

/**
 * Colonnes du CSV de l'annuaire (P11-04) : modèle téléchargeable, export et import partagent le même ordre et les
 * mêmes en-têtes (lang/fr/admin_data.php `directory.csv`). À l'import, les en-têtes sont reconnus sans casse ni accent
 * (« Téléphone », « telephone », « phone »…) et l'ordre des colonnes est libre ; seules « Nom » et « Rôle » sont
 * obligatoires.
 */
final class DirectoryCsv
{
    /** @var list<string> clés des colonnes, dans l'ordre du modèle */
    public const COLUMNS = ['name', 'role', 'org', 'phone', 'email', 'whatsapp', 'zone', 'commune', 'availability', 'active', 'public', 'national_focal', 'emergency'];

    /** Alias reconnus à l'import (en plus de l'en-tête du modèle), déjà normalisés. */
    private const ALIASES = [
        'name' => ['name', 'nom'],
        'role' => ['role', 'fonction'],
        'org' => ['org', 'organisation', 'structure'],
        'phone' => ['phone', 'telephone', 'tel'],
        'email' => ['email', 'mail', 'courriel', 'adresseemail'],
        'whatsapp' => ['whatsapp'],
        'zone' => ['zone', 'departement'],
        'commune' => ['commune'],
        'availability' => ['availability', 'disponibilite'],
        'active' => ['active', 'actif'],
        'public' => ['public', 'publique'],
        'national_focal' => ['nationalfocal', 'pointfocalnational', 'national'],
        'emergency' => ['emergency', 'urgence'],
    ];

    private function __construct() {}

    /**
     * En-têtes affichés, clé => libellé.
     *
     * @return array<string, string>
     */
    public static function headers(): array
    {
        $headers = [];

        foreach (self::COLUMNS as $column) {
            $headers[$column] = (string) __('admin_data.directory.csv.'.$column);
        }

        return $headers;
    }

    /**
     * Colonne correspondant à un en-tête du fichier, ou null.
     */
    public static function columnFor(string $header): ?string
    {
        $normalized = self::normalize($header);

        foreach (self::COLUMNS as $column) {
            if ($normalized === self::normalize((string) __('admin_data.directory.csv.'.$column)) || in_array($normalized, self::ALIASES[$column], true)) {
                return $column;
            }
        }

        return null;
    }

    /** Minuscules, sans accent ni ponctuation : « Point focal national » → « pointfocalnational ». */
    public static function normalize(string $value): string
    {
        return (string) preg_replace('/[^a-z0-9]/', '', Str::lower(Str::ascii(trim($value))));
    }

    /** « oui » / « non » du modèle ; à l'import, « non », « 0 », « false », « faux », « no » sont faux. */
    public static function toBool(?string $value, bool $default): bool
    {
        $text = Str::lower(trim((string) $value));

        if ($text === '') {
            return $default;
        }

        return ! in_array($text, ['non', '0', 'false', 'faux', 'no', 'n'], true);
    }
}
