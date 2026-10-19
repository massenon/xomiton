<?php

declare(strict_types=1);

namespace App\Application\Sync\Push\Handlers;

use App\Application\Messaging\SendMessage;
use App\Application\Sync\Push\ItemHandler;
use App\Application\Sync\Push\ItemOutcome;
use App\Application\Sync\Push\ItemRejected;
use App\Application\Sync\Push\PushContext;
use App\Application\Sync\Push\PushItem;
use App\Models\Conversation;
use Illuminate\Support\Facades\Gate;

/**
 * `message` : SendMessage (P8-01) avec `client_uuid` = uuid de l'élément (unique par conversation, ADR-32). Mêmes
 * gardes que `POST conversations/{ulid}/messages` : e-mail confirmé (Q12 c, 403 `email_unverified`), conversation
 * visible et droit d'écrire (ConversationPolicy::sendMessage), sinon 404 neutre ; refus métier (blocage, uuid pris)
 * rendus tels quels.
 */
final class MessageItemHandler implements ItemHandler
{
    /** = App\Http\Middleware\EnsureEmailIsVerified::CODE */
    public const EMAIL_UNVERIFIED = 'email_unverified';

    public function __construct(private readonly SendMessage $send) {}

    public function handle(PushItem $item, PushContext $context): ItemOutcome
    {
        $draft = $context->decoder->message($item);

        if (! $context->user->hasVerifiedEmail()) {
            throw new ItemRejected(403, self::EMAIL_UNVERIFIED, (string) __('auth.email_not_verified'));
        }

        $conversation = Conversation::query()->where('ulid', $draft->conversation)->first();

        if ($conversation === null || Gate::forUser($context->user)->denies('view', $conversation)) {
            throw ItemRejected::notFound();
        }

        if (Gate::forUser($context->user)->denies('sendMessage', $conversation)) {
            throw ItemRejected::forbidden();
        }

        $outcome = ($this->send)($context->user, $conversation, $draft->body, strtolower($item->uuid));

        return $outcome->created ? ItemOutcome::created($item, $outcome->message) : ItemOutcome::duplicate($item, $outcome->message);
    }
}
