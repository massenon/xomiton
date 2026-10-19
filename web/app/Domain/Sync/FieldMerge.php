<?php

declare(strict_types=1);

namespace App\Domain\Sync;

/**
 * Fusion champ par champ d'une modification faite hors connexion (SOFTWARE_ARCHITECTURE.md §5, P9-06). Pour chaque
 * champ envoyé par l'appareil : `base` = valeur que l'appareil avait avant sa modification, `device` = sa nouvelle
 * valeur, `server` = valeur actuelle du serveur (toutes normalisées en chaîne ou null par l'appelant).
 *
 *  - appareil = serveur : rien à faire ;
 *  - serveur = base (personne n'a touché le champ côté serveur) : la valeur de l'appareil est appliquée ;
 *  - sinon (modification concurrente) : FieldOwner::Server → serveur gardé ; FieldOwner::Author → appareil appliqué
 *    si son compte est l'auteur·rice, conflit sinon ; FieldOwner::Shared → conflit.
 *
 * Fonction pure : aucune écriture, l'application enregistre les conflits (`sync_conflicts`).
 */
final class FieldMerge
{
    /**
     * @param  array<string, string|null>  $server
     * @param  array<string, string|null>  $base
     * @param  array<string, string|null>  $device
     * @param  array<string, FieldOwner>  $owners  champ absent : FieldOwner::Shared
     */
    public static function resolve(array $server, array $base, array $device, array $owners, bool $deviceIsAuthor): MergeOutcome
    {
        $applied = [];
        $kept = [];
        $conflicts = [];
        $unchanged = [];

        foreach ($device as $field => $value) {
            $current = $server[$field] ?? null;

            if ($value === $current) {
                $unchanged[] = $field;

                continue;
            }

            if (($base[$field] ?? null) === $current) {
                $applied[$field] = $value;

                continue;
            }

            $owner = $owners[$field] ?? FieldOwner::Shared;

            if ($owner === FieldOwner::Server) {
                $kept[] = $field;
            } elseif ($owner === FieldOwner::Author && $deviceIsAuthor) {
                $applied[$field] = $value;
            } else {
                $conflicts[$field] = ['server' => $current, 'device' => $value];
            }
        }

        return new MergeOutcome($applied, $kept, $conflicts, $unchanged);
    }
}
