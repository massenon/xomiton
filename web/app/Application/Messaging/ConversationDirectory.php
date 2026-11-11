<?php

declare(strict_types=1);

namespace App\Application\Messaging;

use App\Domain\Messaging\ConversationRules;
use App\Enums\ConversationKind;
use App\Models\Conversation;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use App\Models\UserBlock;
use App\Support\LikePattern;
use DateTimeInterface;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Lectures de la messagerie (P8-01, docs/API.md §12) : liste des conversations d'une personne, fil des messages,
 * curseurs de lecture. Eloquent direct (ADR-29 : lectures).
 *
 *  - liste : conversations où la personne est présente (`left_at` nul), dernière activité en tête ; recherche sur le
 *    titre et le pseudonyme des autres participant·es (colonnes en clair), **jamais sur le corps** (chiffré, ADR-33) ;
 *  - dernier message et aperçu calculés à la lecture (corps déchiffré à la volée, `last_message_preview` n'est plus
 *    écrit), masqués en mode discret ou si la personne masque ses aperçus ;
 *  - messages des comptes que la personne a bloqués : masqués (liste, aperçu, non lus) ; dans un **groupe**, ceux des
 *    comptes qui l'ont bloquée aussi (la personne bloquée ne lit plus celle qui l'a bloquée) ;
 *  - groupe : la personne ne lit que les messages postérieurs à son entrée (`joined_after_message_id`, ajout ou retour) ;
 *  - conversation privée encore vide : visible de la seule personne qui l'a ouverte (un lien `?with=` ne fait apparaître
 *    aucune conversation chez l'autre avant un premier message) ;
 *  - non lus : messages d'autres personnes après `last_read_message_id`, hors messages supprimés pour tout le monde ;
 *  - sondage de la liste (D7, réponses légères) : empreinte `fingerprint()` calculée par agrégats, sans déchiffrer ni
 *    charger les participant·es ; inchangée → réponse vide.
 */
final class ConversationDirectory
{
    public function __construct(private readonly MessagingContacts $contacts) {}

    /**
     * @return LengthAwarePaginator<int, ConversationView>
     */
    public function paginate(User $viewer, ?string $search, bool $unreadOnly, int $perPage): LengthAwarePaginator
    {
        $query = $this->forViewer($viewer);
        $term = trim((string) $search);

        if ($term !== '') {
            $pattern = LikePattern::contains($term);
            $query->where(function (Builder $q) use ($pattern, $viewer): void {
                $q->whereRaw(LikePattern::clause('conversations.title'), [$pattern])
                    ->orWhereHas('participants', fn (Builder $p) => $p->where('user_id', '!=', $viewer->getKey())
                        ->whereHas('user', fn (Builder $u) => $u->whereRaw(LikePattern::clause('pseudo'), [$pattern])));
            });
        }

        if ($unreadOnly) {
            $query->whereExists(function ($q) use ($viewer): void {
                $q->selectRaw('1')->from('messages')
                    ->whereColumn('messages.conversation_id', 'conversations.id')
                    ->whereRaw('messages.id > COALESCE(me.last_read_message_id, 0)')
                    ->whereRaw('messages.id > COALESCE(me.joined_after_message_id, 0)')
                    ->whereNull('messages.deleted_for_everyone_at')
                    ->where(fn ($m) => $m->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $viewer->getKey()));
                $this->hideBlocked($q, $viewer, 'conversations.kind');
            });
        }

        $paginator = $query->paginate($perPage);
        $views = $this->views($viewer, new EloquentCollection($paginator->items()));
        $paginator->setCollection($views);

        /** @var LengthAwarePaginator<int, ConversationView> $paginator */
        return $paginator;
    }

    public function view(User $viewer, Conversation $conversation): ConversationView
    {
        return $this->views($viewer, new EloquentCollection([$conversation]))->first();
    }

    /**
     * Historique, du plus récent au plus ancien (curseur sur l'id).
     *
     * @return CursorPaginator<int, Message>
     */
    public function messages(User $viewer, Conversation $conversation, int $perPage): CursorPaginator
    {
        return $this->messageQuery($viewer, $conversation)->orderByDesc('messages.id')->cursorPaginate($perPage);
    }

    /**
     * Sondage court : messages arrivés après `$afterId`, du plus ancien au plus récent.
     *
     * @return Collection<int, Message>
     */
    public function messagesAfter(User $viewer, Conversation $conversation, int $afterId, int $limit): Collection
    {
        return $this->messageQuery($viewer, $conversation)->where('messages.id', '>', $afterId)
            ->orderBy('messages.id')->limit(max(1, $limit))->get();
    }

    /**
     * Sondage court : messages déjà livrés (id ≤ `$afterId`) supprimés pour tout le monde depuis `$since`, pour que le
     * fil ouvert de l'autre personne les retire sans rechargement.
     *
     * @return list<int>
     */
    public function deletedSince(User $viewer, Conversation $conversation, int $afterId, DateTimeInterface $since, int $limit): array
    {
        return array_map('intval', $this->messageQuery($viewer, $conversation)->withoutEagerLoads()
            ->where('messages.id', '<=', $afterId)
            ->where('messages.deleted_for_everyone_at', '>=', $since)
            ->orderByDesc('messages.id')->limit(max(1, $limit))->pluck('messages.id')->all());
    }

    /**
     * Empreinte de la liste de la personne (sondage toutes les `list_poll_seconds`) : nombre de conversations, dernière
     * activité, curseurs de lecture, sourdines, départs et arrivées, suppressions, blocages et paramètres d'aperçu. Deux
     * requêtes d'agrégats, aucun corps déchiffré ; la liste n'est reconstruite que si l'empreinte change.
     */
    public function fingerprint(User $viewer, ?string $search, bool $unreadOnly, int $page): string
    {
        $viewerId = (int) $viewer->getKey();
        $mine = ConversationParticipant::query()->select('conversation_id')->where('user_id', $viewerId)->whereNull('left_at');

        $conversations = Conversation::query()->whereIn('id', $mine)
            ->selectRaw('COUNT(*) as n, MAX(updated_at) as u, MAX(last_message_at) as m')->first();
        $participants = ConversationParticipant::query()->whereIn('conversation_id', $mine)
            ->selectRaw('COUNT(*) as n, MAX(updated_at) as u, SUM(COALESCE(last_read_message_id, 0)) as r')->first();
        $messages = Message::query()->whereIn('conversation_id', $mine)
            ->selectRaw('MAX(id) as i, MAX(deleted_for_everyone_at) as d')->first();
        $blocks = UserBlock::query()->where('user_id', $viewerId)->orWhere('blocked_user_id', $viewerId)
            ->selectRaw('COUNT(*) as n, MAX(created_at) as c')->first();
        $viewer->loadMissing(['profile', 'settings']);

        return sha1(implode('|', [
            $viewerId, trim((string) $search), $unreadOnly ? 1 : 0, $page,
            $conversations?->getAttribute('n'), $conversations?->getAttribute('u'), $conversations?->getAttribute('m'),
            $participants?->getAttribute('n'), $participants?->getAttribute('u'), $participants?->getAttribute('r'),
            $messages?->getAttribute('i'), $messages?->getAttribute('d'),
            $blocks?->getAttribute('n'), $blocks?->getAttribute('c'),
            (int) (bool) $viewer->profile?->discreet_mode, (int) (bool) $viewer->settings?->mask_sensitive_notifications,
            now()->format('Y-m-d H'), // fin de sourdine, profils des autres : reconstruite au moins une fois par heure
        ]));
    }

    /**
     * Compteur « Messages » de l'en-tête (App\Application\Member\MemberShell) : conversations non en sourdine ayant au
     * moins un message non lu, avec les mêmes exclusions que la liste (comptes bloqués, entrée dans un groupe).
     */
    public function unreadConversationsCount(User $viewer): int
    {
        $viewerId = (int) $viewer->getKey();

        return Message::query()
            ->join('conversation_participants as cp', 'cp.conversation_id', '=', 'messages.conversation_id')
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('cp.user_id', $viewerId)
            ->whereNull('cp.left_at')
            ->where(fn (Builder $q) => $q->whereNull('cp.muted_until')->orWhere('cp.muted_until', '<', now()))
            ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->whereRaw('messages.id > COALESCE(cp.joined_after_message_id, 0)')
            ->where(fn (Builder $q) => $q->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $viewerId))
            ->whereNull('messages.deleted_for_everyone_at')
            ->tap(fn (Builder $q) => $this->hideBlocked($q->getQuery(), $viewer, 'conversations.kind'))
            ->distinct()
            ->count('messages.conversation_id');
    }

    /**
     * Conversation privée : dernier message lu par l'autre personne (« Vu », 0 si rien n'est lu) ; null dans un groupe.
     */
    public function readByOthersUpTo(User $viewer, Conversation $conversation): ?int
    {
        if ($conversation->isGroup()) {
            return null;
        }

        return (int) ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
            ->where('user_id', '!=', $viewer->getKey())->max('last_read_message_id');
    }

    /** Non lus restants de la personne dans une conversation (après `POST read`), mêmes exclusions que la liste. */
    public function unreadIn(User $viewer, Conversation $conversation): int
    {
        return (int) ($this->unreadCounts($viewer, [(int) $conversation->getKey()])[$conversation->getKey()] ?? 0);
    }

    public function latestMessageId(Conversation $conversation): ?int
    {
        $id = Message::query()->where('conversation_id', $conversation->getKey())->max('id');

        return $id === null ? null : (int) $id;
    }

    public function participant(User $user, Conversation $conversation): ?ConversationParticipant
    {
        return ConversationParticipant::query()->where('conversation_id', $conversation->getKey())
            ->where('user_id', $user->getKey())->first();
    }

    /**
     * Messages lisibles par la personne : ceux de la conversation, sans ceux des comptes qu'elle a bloqués.
     *
     * @return Builder<Message>
     */
    private function messageQuery(User $viewer, Conversation $conversation): Builder
    {
        $hidden = $this->hiddenAuthors($viewer, $conversation->isGroup());
        $floor = $conversation->isGroup() ? $this->participant($viewer, $conversation)?->joined_after_message_id : null;

        return Message::query()
            ->with('user.profile')
            ->where('messages.conversation_id', $conversation->getKey())
            ->when($floor !== null, fn (Builder $q) => $q->where('messages.id', '>', $floor))
            ->when($hidden !== [], fn (Builder $q) => $q->where(fn (Builder $m) => $m->whereNull('messages.user_id')
                ->orWhereNotIn('messages.user_id', $hidden)));
    }

    /**
     * Autrices masquées : comptes que la personne a bloqués ; dans un groupe, aussi ceux qui l'ont bloquée.
     *
     * @return list<int>
     */
    private function hiddenAuthors(User $viewer, bool $isGroup): array
    {
        $blocked = $this->contacts->blockedBy($viewer);

        return $isGroup ? array_values(array_unique([...$blocked, ...$this->contacts->blockersOf($viewer)])) : $blocked;
    }

    /**
     * Masque, dans une requête sur `messages` jointe à `conversations` (colonne `$kindColumn`), les messages des comptes
     * bloqués par la personne et, dans les groupes, de ceux qui l'ont bloquée.
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function hideBlocked($query, User $viewer, string $kindColumn): void
    {
        $blocked = $this->contacts->blockedBy($viewer);
        $blockers = $this->contacts->blockersOf($viewer);

        if ($blocked !== []) {
            $query->where(fn ($m) => $m->whereNull('messages.user_id')->orWhereNotIn('messages.user_id', $blocked));
        }

        if ($blockers !== []) {
            $query->where(fn ($m) => $m->whereNull('messages.user_id')->orWhereNotIn('messages.user_id', $blockers)
                ->orWhere($kindColumn, '!=', ConversationKind::Group->value));
        }
    }

    /**
     * @return Builder<Conversation>
     */
    private function forViewer(User $viewer): Builder
    {
        return Conversation::query()
            ->select('conversations.*')
            ->join('conversation_participants as me', function ($join) use ($viewer): void {
                $join->on('me.conversation_id', '=', 'conversations.id')
                    ->where('me.user_id', '=', $viewer->getKey())
                    ->whereNull('me.left_at');
            })
            ->where(fn (Builder $q) => $q->where('conversations.kind', ConversationKind::Group->value)
                ->orWhereNotNull('conversations.last_message_at')
                ->orWhere('conversations.created_by', $viewer->getKey()))
            ->orderByRaw('COALESCE(conversations.last_message_at, conversations.created_at) DESC')
            ->orderByDesc('conversations.id');
    }

    /**
     * @param  EloquentCollection<int, Conversation>  $conversations
     * @return Collection<int, ConversationView>
     */
    private function views(User $viewer, EloquentCollection $conversations): Collection
    {
        if ($conversations->isEmpty()) {
            return collect();
        }

        $conversations->load(['participants.user.profile']);
        $viewer->loadMissing(['profile', 'settings']);
        $viewerId = (int) $viewer->getKey();
        $ids = $conversations->modelKeys();
        $lastMessages = $this->lastMessages($viewer, $ids);
        $unread = $this->unreadCounts($viewer, $ids);
        $previewVisible = ConversationRules::previewVisible(
            (bool) $viewer->profile?->discreet_mode,
            (bool) $viewer->settings?->mask_sensitive_notifications,
        );

        $others = $conversations->reject(fn (Conversation $c) => $c->isGroup())
            ->map(fn (Conversation $c) => $c->participants->first(fn (ConversationParticipant $p) => (int) $p->user_id !== $viewerId)?->user_id)
            ->filter()->map(fn ($id) => (int) $id)->unique()->values()->all();
        [$blockedByMe, $blockedMe] = $this->blocks($viewerId, $others);

        return $conversations->map(function (Conversation $conversation) use ($viewer, $viewerId, $lastMessages, $unread, $previewVisible, $blockedByMe, $blockedMe): ConversationView {
            $me = $conversation->participants->first(fn (ConversationParticipant $p) => (int) $p->user_id === $viewerId);
            $present = $conversation->participants->filter(fn (ConversationParticipant $p) => ! $p->hasLeft())->values();
            $isParticipant = $me !== null && ! $me->hasLeft();
            $other = null;
            $blockedByViewer = false;
            $blocked = false;

            if (! $conversation->isGroup()) {
                $other = $conversation->participants->first(fn (ConversationParticipant $p) => (int) $p->user_id !== $viewerId)?->user;
                $otherId = $other === null ? null : (int) $other->getKey();
                $blockedByViewer = $otherId !== null && in_array($otherId, $blockedByMe, true);
                $blocked = $blockedByViewer || ($otherId !== null && in_array($otherId, $blockedMe, true));
            }

            $canManage = $isParticipant && $conversation->isGroup()
                && ($me->isAdmin() || ($conversation->created_by !== null && (int) $conversation->created_by === $viewerId));

            return new ConversationView(
                conversation: $conversation,
                viewer: $viewer,
                me: $me,
                participants: $present,
                other: $other,
                lastMessage: $lastMessages->get($conversation->getKey()),
                unread: (int) ($unread[$conversation->getKey()] ?? 0),
                previewVisible: $previewVisible,
                canSend: ConversationRules::canSend(
                    $isParticipant,
                    $conversation->isGroup(),
                    otherReachable: $other !== null && $this->contacts->reachable($other),
                    blocked: $blocked,
                ),
                blockedByMe: $blockedByViewer,
                canManage: $canManage,
            );
        })->values();
    }

    /**
     * Dernier message lisible de chaque conversation (entrée dans un groupe et blocages compris).
     *
     * @param  list<int>  $ids
     * @return Collection<int, Message>
     */
    private function lastMessages(User $viewer, array $ids): Collection
    {
        $latest = $this->readable($viewer, $ids)->selectRaw('MAX(messages.id)')->groupBy('messages.conversation_id');

        return Message::query()->with('user')->whereIn('id', $latest)->get()->keyBy('conversation_id');
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, int>
     */
    private function unreadCounts(User $viewer, array $ids): array
    {
        $viewerId = (int) $viewer->getKey();

        return $this->readable($viewer, $ids)
            ->selectRaw('messages.conversation_id as conversation_id, COUNT(*) as aggregate')
            ->whereRaw('messages.id > COALESCE(cp.last_read_message_id, 0)')
            ->whereNull('messages.deleted_for_everyone_at')
            ->where(fn (Builder $q) => $q->whereNull('messages.user_id')->orWhere('messages.user_id', '!=', $viewerId))
            ->groupBy('messages.conversation_id')
            ->pluck('aggregate', 'conversation_id')
            ->map(fn ($count) => (int) $count)
            ->all();
    }

    /**
     * Messages des conversations `$ids` que la personne peut lire (jointure `cp` = sa participation, `conversations`).
     *
     * @param  list<int>  $ids
     * @return Builder<Message>
     */
    private function readable(User $viewer, array $ids): Builder
    {
        $viewerId = (int) $viewer->getKey();
        $query = Message::query()
            ->join('conversation_participants as cp', function ($join) use ($viewerId): void {
                $join->on('cp.conversation_id', '=', 'messages.conversation_id')->where('cp.user_id', '=', $viewerId);
            })
            ->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->whereIn('messages.conversation_id', $ids)
            ->whereRaw('messages.id > COALESCE(cp.joined_after_message_id, 0)');
        $this->hideBlocked($query->getQuery(), $viewer, 'conversations.kind');

        return $query;
    }

    /**
     * @param  list<int>  $others
     * @return array{0: list<int>, 1: list<int>}
     */
    private function blocks(int $viewerId, array $others): array
    {
        if ($others === []) {
            return [[], []];
        }

        $rows = UserBlock::query()
            ->where(fn (Builder $q) => $q->where('user_id', $viewerId)->whereIn('blocked_user_id', $others))
            ->orWhere(fn (Builder $q) => $q->where('blocked_user_id', $viewerId)->whereIn('user_id', $others))
            ->get(['user_id', 'blocked_user_id']);

        $byMe = $rows->where('user_id', $viewerId)->pluck('blocked_user_id')->map(fn ($id) => (int) $id)->values()->all();
        $me = $rows->where('blocked_user_id', $viewerId)->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all();

        return [$byMe, $me];
    }
}
