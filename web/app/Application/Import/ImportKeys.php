<?php

declare(strict_types=1);

namespace App\Application\Import;

use Ramsey\Uuid\Uuid;

/**
 * Clés d'idempotence déterministes de l'import : un UUID v5 dérivé de la collection et de l'identifiant
 * du prototype, posé dans les colonnes `client_uuid` / `idempotency_key` / `device_uuid` prévues pour
 * l'idempotence (synchronisation). Rejouer l'import retrouve ainsi chaque ligne sans doublon, même quand
 * la clé naturelle a changé (codes VDH renumérotés, codes de suivi générés…).
 */
final class ImportKeys
{
    private const NAMESPACE = 'xomiton://prototype/';

    private function __construct() {}

    public static function uuid(string $collection, string|int $key): string
    {
        return Uuid::uuid5(Uuid::NAMESPACE_URL, self::NAMESPACE.$collection.'/'.$key)->toString();
    }

    /** Clé des dossiers : le code du prototype (`VBG-0001`), avant renumérotation. */
    public static function caseUuid(string $prototypeCode): string
    {
        return self::uuid('cases', mb_strtoupper(trim($prototypeCode)));
    }

    /** Nom normalisé pour les rapprochements par libellé (pair·es, contacts, organisateur·rices). */
    public static function normalizeName(string $name): string
    {
        return mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $name)));
    }
}
