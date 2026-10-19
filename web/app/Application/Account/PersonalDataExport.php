<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Models\Album;
use App\Models\AlbumMedia;
use App\Models\Alert;
use App\Models\AlertAttachment;
use App\Models\AssistantConversation;
use App\Models\AssistantMessage;
use App\Models\Comment;
use App\Models\Consent;
use App\Models\ConversationParticipant;
use App\Models\EventRsvp;
use App\Models\Follow;
use App\Models\ForumReply;
use App\Models\ForumTopic;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\Message;
use App\Models\ModerationReport;
use App\Models\PageFollower;
use App\Models\PollVote;
use App\Models\Post;
use App\Models\PostHide;
use App\Models\PostLike;
use App\Models\PostMedia;
use App\Models\PostSave;
use App\Models\PushSubscription;
use App\Models\SyncDevice;
use App\Models\User;
use App\Models\UserBlock;
use App\Models\UserWarning;
use BackedEnum;
use Closure;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Contenu de l'export RGPD (POST me/export, P6-06 ; texte `profile.settings.data.text` : « profil, publications et
 * activité ») : ce que la plateforme garde **sur la personne et qu'elle a fourni ou fait**, données sensibles comprises
 * (identité de genre, orientation, contact de confiance, récits et positions des alertes, messages, déchiffrés : c'est
 * son droit d'accès). Une section par famille : compte, profil (chemins de l'avatar et de la couverture), réglages,
 * consentements, publications (et leurs médias), commentaires, mentions « j'aime », enregistrements, publications
 * masquées de son fil (`hides`), votes, abonnements
 * (comptes et pages suivis), groupes, participations aux événements, invitations, conversations et messages
 * **qu'elle a écrits**, sujets et réponses de forum, conversations avec l'assistant, albums et médias, blocages,
 * avertissements reçus, signalements de modération déposés, alertes rattachées au compte (lieu, contact, besoins,
 * pièces jointes), appareils, abonnements push, newsletter. Publications et commentaires anonymes compris (ils sont
 * les siens) ; les signalements anonymes ne sont pas rattachés au compte, donc absents. Identifiants publics (ULID)
 * seulement, jamais les identifiants internes d'autres tables.
 *
 * Exclus (Q70) : mot de passe, jetons, codes, endpoints push ; **fichiers eux-mêmes** (l'export donne leurs chemins,
 * les fichiers restent téléchargeables depuis l'application ; archive ZIP à décider) ; messages écrits par d'autres
 * personnes et identité de qui a signalé ou averti (données d'autrui) ; notes internes des équipes (alertes, dossiers)
 * et indicateurs de modération de l'assistant ; notifications de la cloche (copies d'événements déjà listés).
 */
final class PersonalDataExport
{
    public const FORMAT_VERSION = 2;

    /**
     * @return array<string, mixed>
     */
    public function collect(User $user): array
    {
        $user->loadMissing(['profile', 'settings', 'zone', 'commune', 'roles']);
        $id = (int) $user->getKey();

        return [
            'format_version' => self::FORMAT_VERSION,
            'generated_at' => now()->toIso8601String(),
            'account' => $this->account($user),
            'profile' => $this->profile($user),
            'settings' => $this->settings($user),
            'consents' => self::rows(Consent::query()->where('user_id', $id), static fn (Consent $consent): array => [
                'kind' => self::value($consent->kind),
                'version' => $consent->version,
                'granted' => (bool) $consent->granted,
                'created_at' => self::date($consent->created_at),
            ]),
            'posts' => self::rows(Post::query()->withTrashed()->with('media')->where('user_id', $id), static fn (Post $post): array => [
                'id' => $post->ulid,
                'body' => $post->body,
                'tag' => self::value($post->tag),
                'visibility' => self::value($post->visibility),
                'anonymous' => (bool) $post->is_anonymous,
                'status' => self::value($post->status),
                'media' => $post->media->map(static fn (PostMedia $media): array => self::media($media))->values()->all(),
                'created_at' => self::date($post->created_at),
                'deleted_at' => self::date($post->deleted_at),
            ]),
            'comments' => self::rows(Comment::query()->withTrashed()->with(['post' => self::trashed()])->where('user_id', $id), static fn (Comment $comment): array => [
                'post_id' => $comment->post?->ulid,
                'body' => $comment->body,
                'anonymous' => (bool) $comment->is_anonymous,
                'created_at' => self::date($comment->created_at),
                'deleted_at' => self::date($comment->deleted_at),
            ]),
            'likes' => self::rows(PostLike::query()->with(['post' => self::trashed()])->where('user_id', $id), static fn (PostLike $like): array => [
                'post_id' => $like->post?->ulid,
                'created_at' => self::date($like->created_at),
            ]),
            'saves' => self::rows(PostSave::query()->with(['post' => self::trashed()])->where('user_id', $id), static fn (PostSave $save): array => [
                'post_id' => $save->post?->ulid,
                'created_at' => self::date($save->created_at),
            ]),
            'hides' => self::rows(PostHide::query()->with(['post' => self::trashed()])->where('user_id', $id), static fn (PostHide $hide): array => [
                'post_id' => $hide->post?->ulid,
                'created_at' => self::date($hide->created_at),
            ]),
            'poll_votes' => self::rows(PollVote::query()->with(['option', 'poll.post' => self::trashed()])->where('user_id', $id), static fn (PollVote $vote): array => [
                'post_id' => $vote->poll?->post?->ulid,
                'option' => $vote->option?->label,
                'created_at' => self::date($vote->created_at),
            ]),
            'following' => self::rows(Follow::query()->with(['followed' => self::trashed()])->where('follower_id', $id), static fn (Follow $follow): array => [
                'pseudo' => $follow->followed?->pseudo,
                'created_at' => self::date($follow->created_at),
            ]),
            'pages_followed' => self::rows(PageFollower::query()->with(['page' => self::trashed()])->where('user_id', $id), static fn (PageFollower $row): array => [
                'page' => $row->page?->name,
                'slug' => $row->page?->slug,
                'created_at' => self::date($row->created_at),
            ]),
            'groups' => self::rows(GroupMember::query()->with(['group' => self::trashed()])->where('user_id', $id), static fn (GroupMember $member): array => [
                'group' => $member->group?->name,
                'slug' => $member->group?->slug,
                'role' => self::value($member->role),
                'status' => self::value($member->status),
                'joined_at' => self::date($member->joined_at),
            ]),
            'events' => self::rows(EventRsvp::query()->with(['event' => self::trashed()])->where('user_id', $id), static fn (EventRsvp $rsvp): array => [
                'event_id' => $rsvp->event?->ulid,
                'title' => $rsvp->event?->title,
                'starts_at' => self::date($rsvp->event?->starts_at),
                'rsvp' => self::value($rsvp->status),
                'reminder' => (bool) $rsvp->reminder_opt_in,
                'created_at' => self::date($rsvp->created_at),
            ]),
            'invitations' => self::rows(Invitation::query()->with(['fromUser' => self::trashed(), 'toUser' => self::trashed()])
                ->where(static fn (Builder $query) => $query->where('from_user_id', $id)->orWhere('to_user_id', $id)), static fn (Invitation $invitation): array => [
                    'direction' => (int) $invitation->from_user_id === $id ? 'sent' : 'received',
                    'with' => (int) $invitation->from_user_id === $id ? $invitation->toUser?->pseudo : $invitation->fromUser?->pseudo,
                    'status' => self::value($invitation->status),
                    'responded_at' => self::date($invitation->responded_at),
                    'created_at' => self::date($invitation->created_at),
                ]),
            'conversations' => self::rows(ConversationParticipant::query()->with('conversation')->where('user_id', $id), static fn (ConversationParticipant $participant): array => [
                'conversation_id' => $participant->conversation?->ulid,
                'kind' => self::value($participant->conversation?->kind),
                'title' => $participant->conversation?->title,
                'role' => self::value($participant->role),
                'left_at' => self::date($participant->left_at),
                'created_at' => self::date($participant->created_at),
            ]),
            'messages' => self::rows(Message::query()->with('conversation')->where('user_id', $id), static fn (Message $message): array => [
                'conversation_id' => $message->conversation?->ulid,
                'body' => $message->body,
                'media_path' => $message->media_path,
                'deleted_for_everyone_at' => self::date($message->deleted_for_everyone_at),
                'created_at' => self::date($message->created_at),
            ]),
            'forum_topics' => self::rows(ForumTopic::query()->withTrashed()->with('forum')->where('user_id', $id), static fn (ForumTopic $topic): array => [
                'id' => $topic->ulid,
                'forum' => $topic->forum?->name,
                'title' => $topic->title,
                'body' => $topic->body,
                'anonymous' => (bool) $topic->is_anonymous,
                'status' => self::value($topic->status),
                'created_at' => self::date($topic->created_at),
                'deleted_at' => self::date($topic->deleted_at),
            ]),
            'forum_replies' => self::rows(ForumReply::query()->withTrashed()->with(['topic' => self::trashed()])->where('user_id', $id), static fn (ForumReply $reply): array => [
                'topic_id' => $reply->topic?->ulid,
                'body' => $reply->body,
                'anonymous' => (bool) $reply->is_anonymous,
                'status' => self::value($reply->status),
                'created_at' => self::date($reply->created_at),
                'deleted_at' => self::date($reply->deleted_at),
            ]),
            'assistant_conversations' => self::rows(AssistantConversation::query()->with('messages')->where('user_id', $id), static fn (AssistantConversation $conversation): array => [
                'created_at' => self::date($conversation->created_at),
                'messages' => $conversation->messages->sortBy('id')->map(static fn (AssistantMessage $message): array => [
                    'role' => self::value($message->role),
                    'body' => $message->body,
                    'created_at' => self::date($message->created_at),
                ])->values()->all(),
            ]),
            'albums' => self::rows(Album::query()->withTrashed()->with('media')->where('user_id', $id), static fn (Album $album): array => [
                'id' => $album->ulid,
                'kind' => self::value($album->kind),
                'title' => $album->title,
                'description' => $album->description,
                'visibility' => self::value($album->visibility),
                'media' => $album->media->map(static fn (AlbumMedia $media): array => self::media($media))->values()->all(),
                'created_at' => self::date($album->created_at),
                'deleted_at' => self::date($album->deleted_at),
            ]),
            'blocks' => self::rows(UserBlock::query()->with(['blockedUser' => self::trashed()])->where('user_id', $id), static fn (UserBlock $block): array => [
                'pseudo' => $block->blockedUser?->pseudo,
                'reason' => $block->reason,
                'created_at' => self::date($block->created_at),
            ]),
            'warnings' => self::rows(UserWarning::query()->where('user_id', $id), static fn (UserWarning $warning): array => [
                'reason' => $warning->reason,
                'created_at' => self::date($warning->created_at),
            ]),
            'reports_filed' => self::rows(ModerationReport::query()->where('reporter_user_id', $id), static fn (ModerationReport $report): array => [
                'kind' => self::value($report->kind),
                'reason' => $report->reason,
                'details' => $report->details,
                'status' => self::value($report->status),
                'created_at' => self::date($report->created_at),
            ]),
            'alerts' => self::rows(Alert::query()->with(['zone', 'commune', 'attachments'])->where('user_id', $id), static fn (Alert $alert): array => [
                'type' => self::value($alert->type),
                'tracking_code' => $alert->tracking_code,
                'status' => self::value($alert->status),
                'category' => $alert->category,
                'description' => $alert->description,
                'occurred_on' => $alert->occurred_on?->format('Y-m-d'),
                'needs' => $alert->needs,
                'zone' => $alert->zone?->name,
                'commune' => $alert->commune?->name,
                'lat' => $alert->lat,
                'lng' => $alert->lng,
                'location_accuracy' => $alert->location_accuracy,
                'contact' => $alert->contact,
                'preferred_channel' => self::value($alert->preferred_channel),
                'attachments' => $alert->attachments->map(static fn (AlertAttachment $file): array => [
                    'kind' => self::value($file->kind),
                    'mime' => $file->mime,
                    'size' => $file->size,
                    'created_at' => self::date($file->created_at),
                ])->values()->all(),
                'created_at' => self::date($alert->created_at),
                'closed_at' => self::date($alert->closed_at),
            ]),
            'devices' => self::rows(SyncDevice::query()->where('user_id', $id), static fn (SyncDevice $device): array => [
                'label' => $device->label,
                'platform' => $device->platform,
                'app_version' => $device->app_version,
                'revoked' => $device->isRevoked(),
                'last_seen_at' => self::date($device->last_seen_at),
                'created_at' => self::date($device->created_at),
            ]),
            'push_subscriptions' => self::rows(PushSubscription::query()->where('user_id', $id), static fn (PushSubscription $subscription): array => [
                'kind' => $subscription->kind,
                'device_label' => $subscription->device_label,
                'created_at' => self::date($subscription->created_at),
            ]),
            'newsletter' => ($subscriber = app(MemberNewsletter::class)->find($user)) === null ? null : [
                'email' => $subscriber->email,
                'status' => self::value($subscriber->status),
                'interests' => $subscriber->interests,
                'confirmed_at' => self::date($subscriber->confirmed_at),
                'unsubscribed_at' => self::date($subscriber->unsubscribed_at),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function account(User $user): array
    {
        return [
            'id' => (int) $user->getKey(),
            'pseudo' => $user->pseudo,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'status' => self::value($user->status),
            'roles' => $user->getRoleNames()->values()->all(),
            'zone' => $user->zone?->name,
            'commune' => $user->commune?->name,
            'email_verified_at' => self::date($user->email_verified_at),
            'last_login_at' => self::date($user->last_login_at),
            'created_at' => self::date($user->created_at),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function profile(User $user): ?array
    {
        $profile = $user->profile;

        return $profile === null ? null : [
            'pronouns' => $profile->pronouns,
            'age_range' => self::value($profile->age_range),
            'member_type' => self::value($profile->member_type),
            'ally_status' => $profile->ally_status,
            'gender_identity' => $profile->gender_identity,
            'orientation' => $profile->orientation,
            'bio' => $profile->bio,
            'interests' => $profile->interests,
            'shared_fields' => $profile->shared_fields,
            'website' => $profile->website,
            'avatar_path' => $profile->avatar_path,
            'cover_path' => $profile->cover_path,
            'visibility' => self::value($profile->visibility),
            'show_zone' => (bool) $profile->show_zone,
            'searchable' => (bool) $profile->searchable,
            'discreet_mode' => (bool) $profile->discreet_mode,
            'trusted_contact_name' => $profile->trusted_contact_name,
            'trusted_contact_phone' => $profile->trusted_contact_phone,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function settings(User $user): ?array
    {
        $settings = $user->settings;

        return $settings === null ? null : [
            'theme' => $settings->theme,
            'language' => $settings->language,
            'notification_prefs' => $settings->notificationPrefs(),
            'notification_digest' => $settings->notification_digest,
            'mask_sensitive_notifications' => (bool) $settings->mask_sensitive_notifications,
            'sos_share_gps' => (bool) $settings->sos_share_gps,
            'pin_enabled' => (bool) $settings->pin_enabled,
            'neutral_tab_title' => (bool) $settings->neutral_tab_title,
            'sounds_enabled' => (bool) ($settings->sounds_enabled ?? true),
        ];
    }

    /**
     * Lignes d'une section, dans l'ordre d'enregistrement.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  callable(TModel): array<string, mixed>  $map
     * @return list<array<string, mixed>>
     */
    private static function rows(Builder $query, callable $map): array
    {
        return $query->orderBy($query->getModel()->getQualifiedKeyName())->get()->map($map)->values()->all();
    }

    /** Relation chargée y compris supprimée logiquement (publication, groupe, sujet… effacés depuis). */
    private static function trashed(): Closure
    {
        return static function (Relation $relation): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($relation->getModel()), true)) {
                $relation->withTrashed(); // macro de SoftDeletingScope
            }
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function media(PostMedia|AlbumMedia $media): array
    {
        return [
            'kind' => self::value($media->kind),
            'path' => $media->path,
            'alt' => $media->alt,
        ];
    }

    private static function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }

    private static function date(mixed $value): ?string
    {
        return $value instanceof DateTimeInterface ? $value->format(DateTimeInterface::ATOM) : null;
    }
}
