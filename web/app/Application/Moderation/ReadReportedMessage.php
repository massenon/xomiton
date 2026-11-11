<?php

declare(strict_types=1);

namespace App\Application\Moderation;

use App\Domain\Moderation\Exceptions\ModerationRefused;
use App\Enums\ModerationKind;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\Gate;

/**
 * Lecture par la modération d'un message privé signalé (P12-06, Q74, ADR-33 : corps chiffré au repos). Règles
 * appliquées : `moderation.manage` ; **ce seul message** (jamais le reste de la conversation) ; seulement tant que le
 * signalement est en attente ; chaque ouverture est journalisée (`moderation.message_read`, identifiants seulement, jamais
 * le texte). Le message retiré par son autrice reste lisible jusqu'à la décision (corps gardé, P8-01).
 */
final class ReadReportedMessage
{
    /**
     * @return array{author: ?string, sent_at: \Illuminate\Support\Carbon|null, body: string, has_media: bool}
     *
     * @throws ModerationRefused
     */
    public function __invoke(ModerationReport $report, User $by): array
    {
        $message = $this->message($report, $by);

        Audit::log('moderation.message_read', $report, sprintf('rapport=%d ; message=%d', $report->getKey(), $message->getKey()), $by);

        return $this->content($message);
    }

    /**
     * Même contrôle sans écriture au journal : relecture d'un message déjà ouvert pendant le même affichage.
     *
     * @return array{author: ?string, sent_at: \Illuminate\Support\Carbon|null, body: string, has_media: bool}
     *
     * @throws ModerationRefused
     */
    public function peek(ModerationReport $report, User $by): array
    {
        return $this->content($this->message($report, $by));
    }

    /**
     * @throws ModerationRefused
     */
    private function message(ModerationReport $report, User $by): Message
    {
        Gate::forUser($by)->authorize('handle', $report);

        if ($report->kind !== ModerationKind::Message || ! $report->isPending()) {
            throw new ModerationRefused('message_not_readable');
        }

        $message = $report->subject;

        if (! $message instanceof Message) {
            throw new ModerationRefused('message_not_readable');
        }

        return $message;
    }

    /**
     * @return array{author: ?string, sent_at: \Illuminate\Support\Carbon|null, body: string, has_media: bool}
     */
    private function content(Message $message): array
    {
        return [
            'author' => $message->user?->pseudo,
            'sent_at' => $message->created_at,
            'body' => (string) $message->body,
            'has_media' => $message->hasMedia(),
        ];
    }
}
