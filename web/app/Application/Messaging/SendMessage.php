<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Application\Shared\MediaUploader;
use App\Application\Shared\StoredMedia;
use App\Domain\Messaging\ConversationRules;
use App\Domain\Messaging\Exceptions\ClientUuidConflict;
use App\Domain\Messaging\Exceptions\ConversationUnavailable;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * POST conversations/{ulid}/messages (P8-01, ADR-32) : envoi d'un message, **idempotent par `client_uuid`** (unique
 * `(conversation_id, client_uuid)`) — un rejeu de la boîte d'envoi (même uuid, même auteur·rice) rend le message
 * d'origine (`created = false`, 200), jamais un doublon ni un 409 ; un uuid déjà pris par quelqu'un d'autre → 422.
 * Réutilisable par `sync/push` (P9-06).
 *
 *  - corps chiffré au repos (cast `encrypted`, `encrypted_columns.messages`) ; pièce jointe facultative (image ou PDF,
 *    disque privé via MediaUploader, servie en lien signé) ;
 *  - conversation privée : refus neutre (403 `conversation_unavailable`) si l'autre personne est injoignable ou s'il y a
 *    un blocage ; l'autre personne qui l'avait quittée (« masquée ») la retrouve ;
 *  - dernière activité de la conversation mise à jour sans recopier le corps en clair (ADR-33) ; le message envoyé est
 *    lu par son auteur·rice ; « Nouveau message » aux autres après validation de la transaction (MessageNotifier).
 */
final class SendMessage
{
    public function __construct(
        private readonly MessagingContacts $contacts,
        private readonly MediaUploader $uploader,
        private readonly MessageNotifier $notifier,
    ) {}

    public function __invoke(User $author, Conversation $conversation, ?string $body, string $clientUuid, ?UploadedFile $attachment = null): SendOutcome
    {
        $existing = $this->replay($author, $conversation, $clientUuid);

        if ($existing !== null) {
            return new SendOutcome($existing, created: false);
        }

        $this->ensureSendable($author, $conversation);

        $stored = $attachment === null ? null : $this->store($attachment);

        try {
            $message = DB::transaction(function () use ($author, $conversation, $body, $clientUuid, $stored): Message {
                $message = Message::query()->create([
                    'conversation_id' => $conversation->getKey(),
                    'user_id' => $author->getKey(),
                    'body' => ConversationRules::normalizeBody($body),
                    'media_path' => $stored?->path,
                    'client_uuid' => $clientUuid,
                ]);

                $conversation->touchLastMessage($message);

                ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
                    ->where('user_id', $author->getKey())->update(['last_read_message_id' => $message->getKey()]);

                if (! $conversation->isGroup()) {
                    ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
                        ->where('user_id', '!=', $author->getKey())->whereNotNull('left_at')->update(['left_at' => null]);
                }

                return $message;
            });
        } catch (UniqueConstraintViolationException) {
            // Rejeu concurrent du même envoi : le premier a gagné.
            if ($stored !== null) {
                $this->uploader->delete($stored);
            }

            $existing = $this->replay($author, $conversation, $clientUuid);

            if ($existing === null) {
                throw new ClientUuidConflict;
            }

            return new SendOutcome($existing, created: false);
        }

        $this->notifier->messageSent($message, $author, $conversation);

        return new SendOutcome($message->load('user.profile'), created: true);
    }

    private function replay(User $author, Conversation $conversation, string $clientUuid): ?Message
    {
        $existing = Message::query()->with('user.profile')->withClientUuid((int) $conversation->getKey(), $clientUuid)->first();

        if ($existing === null) {
            return null;
        }

        if ($existing->user_id === null || (int) $existing->user_id !== (int) $author->getKey()) {
            throw new ClientUuidConflict;
        }

        return $existing;
    }

    private function ensureSendable(User $author, Conversation $conversation): void
    {
        if ($conversation->isGroup()) {
            return;
        }

        $other = $conversation->participants()->with('user')->where('user_id', '!=', $author->getKey())->first()?->user;

        $allowed = ConversationRules::canSend(
            isParticipant: true,
            isGroup: false,
            otherReachable: $other !== null && $this->contacts->reachable($other),
            blocked: $other !== null && $this->contacts->blockedBetween($author, $other),
        );

        if (! $allowed) {
            throw new ConversationUnavailable;
        }
    }

    private function store(UploadedFile $file): StoredMedia
    {
        return $this->uploader->upload($file, self::collectionFor($file));
    }

    /** Collection de config/xomiton.php `uploads.collections` d'une pièce jointe : image, sinon document (PDF). */
    public static function collectionFor(UploadedFile $file): string
    {
        return str_starts_with((string) $file->getMimeType(), 'image/') ? 'images' : 'documents';
    }
}
