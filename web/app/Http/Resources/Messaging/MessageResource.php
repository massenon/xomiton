<?php

declare(strict_types=1);

namespace App\Http\Resources\Messaging;

use App\Application\Shared\MediaUploader;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Message (P8-01, docs/API.md §12.4) : corps déchiffré pour les seul·es participant·es (vide s'il a été supprimé pour
 * tout le monde), auteur·rice (pseudonyme, RG-02), `client_uuid` (rapprochement de la boîte d'envoi), pièce jointe en
 * lien signé à expiration alignée (même lien, même ETag, 304), `status` de ses propres messages dans une conversation
 * privée (`sent` / `seen`, selon le curseur de lecture de l'autre personne, attribut de requête `messaging.read_up_to`).
 *
 * @mixin Message
 */
final class MessageResource extends JsonResource
{
    public const READ_UP_TO = 'messaging.read_up_to';

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Message $message */
        $message = $this->resource;
        $viewer = $request->user();
        $mine = $viewer instanceof User && $message->user_id !== null && (int) $message->user_id === (int) $viewer->getKey();
        $deleted = $message->isDeletedForEveryone();
        $readUpTo = $request->attributes->get(self::READ_UP_TO);

        return [
            'id' => (int) $message->getKey(),
            'client_uuid' => $message->client_uuid,
            'body' => $deleted ? null : (string) $message->body,
            'deleted' => $deleted,
            'mine' => $mine,
            'author' => $message->user === null ? null : ParticipantPayload::for($message->user),
            'attachment' => $deleted ? null : self::attachment($message),
            'status' => $mine && is_int($readUpTo) ? ($readUpTo >= (int) $message->getKey() ? 'seen' : 'sent') : null,
            'created_at' => $message->created_at?->toIso8601String(),
        ];
    }

    /**
     * Appelé pour les messages rendus seulement : le sondage `?after=` ne renvoie que les nouveaux messages, la liste des
     * conversations n'en appelle aucun (ConversationResource : `hasMedia()`).
     *
     * @return array{kind: string, url: string}|null
     */
    public static function attachment(Message $message): ?array
    {
        $path = ltrim((string) $message->media_path, '/');
        $disk = MediaUploader::privateDisk();

        if ($path === '' || ! Storage::disk($disk)->exists($path)) {
            return null;
        }

        return [
            'kind' => str_ends_with(strtolower($path), '.pdf') ? 'document' : 'image',
            'url' => app(MediaUploader::class)->signedUrl($disk, $path, MediaUploader::stableExpiry(
                (int) config('xomiton.messaging.attachment_url_minutes', 60),
                (int) config('xomiton.feed.media_url_window_minutes', 15),
            )),
        ];
    }
}
