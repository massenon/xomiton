<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Data\ReportData;
use App\Application\Alerts\Results\CreatedAlert;
use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Models\AlertAttachment;
use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envoi d'un signalement avec ses preuves (P5-06 `POST signalements`, P5-04 `/signaler`) : chaque fichier passe par
 * MediaUploader sur le disque privé (type réel, métadonnées retirées), puis CreateReport. Les fichiers qu'aucune ligne
 * `alert_attachments` ne référence sont supprimés si la création échoue ou si c'est un rejeu (ADR-32 : l'alerte d'origine
 * garde ses propres pièces), pour ne laisser aucun fichier orphelin ; une erreur levée après le commit (écouteur
 * d'AlertCreated, journal) ne supprime jamais les preuves d'une alerte enregistrée.
 */
final class SubmitReport
{
    public function __construct(
        private readonly CreateReport $create,
        private readonly MediaUploader $uploader,
    ) {}

    /**
     * @param  list<UploadedFile>  $files  preuves déjà validées (StoreReportRequest)
     * @param  Closure(list<StoredMedia>): ReportData  $data  charge utile, pièces stockées comprises
     */
    public function __invoke(array $files, Closure $data): CreatedAlert
    {
        $stored = [];

        try {
            foreach ($files as $file) {
                $stored[] = $this->uploader->upload($file, AlertAttachment::kindForMime((string) $file->getMimeType()));
            }

            $result = ($this->create)($data($stored));
        } catch (Throwable $exception) {
            $this->discard($stored);

            throw $exception;
        }

        if ($result->duplicate) {
            $this->discard($stored);
        }

        return $result;
    }

    /**
     * Supprime les fichiers stockés qu'aucune pièce jointe enregistrée ne référence (rollback, rejeu) ; ceux d'une alerte
     * déjà commitée restent.
     *
     * @param  list<StoredMedia>  $stored
     */
    private function discard(array $stored): void
    {
        if ($stored === []) {
            return;
        }

        try {
            $kept = AlertAttachment::query()
                ->whereIn('path', array_map(static fn (StoredMedia $media): string => $media->path, $stored))
                ->get(['disk', 'path'])
                ->map(static fn (AlertAttachment $attachment): string => $attachment->disk.'|'.$attachment->path)
                ->all();
        } catch (Throwable $exception) {
            // Base injoignable : la transaction n'a pas pu aboutir, aucune ligne ne référence ces fichiers.
            Log::warning('alerts.attachment_lookup_failed', ['error' => $exception->getMessage()]);
            $kept = [];
        }

        foreach ($stored as $media) {
            if (in_array($media->disk.'|'.$media->path, $kept, true)) {
                continue;
            }

            try {
                $this->uploader->delete($media);
            } catch (Throwable $exception) {
                Log::warning('alerts.attachment_not_discarded', ['error' => $exception->getMessage()]);
            }
        }
    }
}
