<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Member;

use App\Application\Messaging\ConversationDirectory;
use App\Application\Messaging\MessagingContacts;
use App\Application\Messaging\StartConversation;
use App\Enums\ContentReportReason;
use App\Http\Controllers\Controller;
use App\Http\Resources\Messaging\ConversationResource;
use App\Http\Resources\Messaging\ParticipantPayload;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Page `/messages` (P8-01, prototype messaging.php) : page Inertia `Member/Messages` (liste + conversation) dans le
 * gabarit membre Vue. Première page de la liste fournie au rendu (affichage immédiat), la suite passe par l'API
 * (docs/API.md §12) : messages, envoi par la boîte d'envoi hors connexion, sondage court (D7 : ni WebSocket ni
 * processus persistant) — conversation ouverte toutes les `feed.messages_poll_seconds` (4 s), liste toutes les
 * `messaging.list_poll_seconds`, seulement onglet visible et réseau présent.
 *
 * `?c=<ulid>` ouvre une conversation (lien de la notification « Nouveau message ») ; `?with=<id>` (bouton « Message »
 * d'un profil, prototype `messaging.php?with=`) **ne crée rien** au chargement : conversation privée déjà ouverte →
 * `selected` ; sinon personne joignable → `with` (pseudonyme, avatar) et la page demande confirmation avant
 * `POST conversations` ; sinon `withUnavailable` (même réponse neutre que l'API). Un lien piégé ne fait donc
 * « contacter » personne, et une conversation vide n'apparaît jamais chez l'autre (ConversationDirectory).
 */
final class MessagesPageController extends Controller
{
    private const ULID = '/^[0-9A-HJKMNP-TV-Za-hjkmnp-tv-z]{26}$/';

    public function __invoke(Request $request, ConversationDirectory $directory, StartConversation $start, MessagingContacts $contacts): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $paginator = $directory->paginate($user, null, false, (int) config('xomiton.messaging.conversations_per_page', 30));
        $title = (string) __('community.messages.title');
        $selected = $request->query('c');
        $selected = is_string($selected) && preg_match(self::ULID, $selected) === 1 ? $selected : null;
        $withId = filter_var($request->query('with'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $with = null;
        $withUnavailable = false;

        if ($withId !== false && (int) $withId !== (int) $user->getKey()) {
            $other = User::query()->with('profile')->find((int) $withId);
            $existing = $other === null ? null : $start->existingPrivate($user, $other);

            if ($existing !== null && $directory->participant($user, $existing)?->hasLeft() === false) {
                $selected ??= $existing->ulid;
            } elseif ($other !== null && ($existing !== null || $contacts->canContact($user, $other))) {
                $with = ParticipantPayload::for($other);
            } else {
                $withUnavailable = true;
            }
        }

        ConversationResource::compact();

        return Inertia::render('Member/Messages', [
            'title' => $title,
            'conversations' => ConversationResource::collection(collect($paginator->items()))->resolve($request),
            'conversationsMeta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage()],
            'selected' => $selected,
            'with' => $with,
            'withUnavailable' => $withUnavailable,
            'config' => [
                'poll_seconds' => (int) config('xomiton.feed.messages_poll_seconds', 4),
                'list_poll_seconds' => (int) config('xomiton.messaging.list_poll_seconds', 15),
                'list_poll_idle_seconds' => (int) config('xomiton.messaging.list_poll_idle_seconds', 30),
                'body_max_length' => (int) config('xomiton.messaging.body_max_length', 1000),
                'recipient_search_min' => (int) config('xomiton.messaging.recipient_search_min', 2),
                'group_title_min' => (int) config('xomiton.messaging.group_title_min', 3),
                'mute_durations' => array_keys((array) config('xomiton.messaging.mute_durations', [])),
                'report_reasons' => array_map(
                    static fn (string $reason): array => ['value' => $reason, 'label' => ContentReportReason::from($reason)->label()],
                    ContentReportReason::selectable(),
                ),
                'attachment_accept' => 'image/jpeg,image/png,image/webp,application/pdf',
            ],
        ])->withViewData(['title' => $title]);
    }
}
