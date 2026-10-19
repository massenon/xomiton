<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\Data\SosData;
use App\Application\Cases\Data\CaseInput;

/**
 * Validation et lecture de la charge utile d'un élément de `sync/push` (P9-06), avec les mêmes règles que la route
 * dédiée (Q11 a : une seule charge utile web et mobile). Implémentée côté HTTP par
 * App\Http\Requests\Api\Sync\SyncItemDecoder (Form Requests des alertes) ; appelée seulement pour un élément nouveau
 * (un rejeu est rendu depuis son reçu, sans revalider).
 */
interface ItemDecoder
{
    /**
     * Signalement ou besoin, jamais anonyme (ADR-26 : l'envoi anonyme passe par POST signalements).
     *
     * @throws ItemRejected
     */
    public function report(PushItem $item, int $deviceId): ReportData;

    /** SOS : un champ refusé est retiré, jamais le SOS (RG-01). */
    public function sos(PushItem $item, int $deviceId): SosData;

    /**
     * @throws ItemRejected
     */
    public function message(PushItem $item): MessageDraft;

    /**
     * Dossier collecté hors connexion (`collecte`, `op: create`) : règles de `POST pair/cases` (CaseRules::forCreate).
     *
     * @throws ItemRejected
     */
    public function caseInput(PushItem $item): CaseInput;

    /**
     * @throws ItemRejected
     */
    public function caseChanges(PushItem $item): CaseChanges;
}
