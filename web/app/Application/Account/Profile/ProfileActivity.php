<?php

declare(strict_types=1);

namespace App\Application\Account\Profile;

use App\Application\Community\Events\EventLinks;
use App\Application\Community\Forums\ForumLinks;
use App\Application\Community\Groups\GroupLinks;
use App\Application\Feed\PostLinks;
use App\Enums\InvitationStatus;
use App\Enums\MembershipStatus;
use App\Enums\RsvpStatus;
use App\Models\AuditLog;
use App\Models\EventRsvp;
use App\Models\ForumTopic;
use App\Models\GroupMember;
use App\Models\Invitation;
use App\Models\Post;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Onglet « Activité » (P8-07, prototype `my-profile-activity.php` : « Journal de mon activité », 40 entrées, icône par
 * type) : **son propre profil seulement** (connexions, réglages, mot de passe : rien à montrer à d'autres ; la page
 * `/membres/{pseudo}` n'a pas cet onglet). Sources : journal d'audit du compte (actions de la liste ACTIONS, jamais
 * l'IP ni le navigateur), publications et sujets de forum **non anonymes** (RG-02 : une contribution anonyme n'est
 * rattachée à aucun profil, même dans le journal), « Je participe », groupes rejoints, connexions acceptées.
 */
final class ProfileActivity
{
    /** Action du journal d'audit → type d'entrée (icône et libellé : lang/fr/profile.php `activity.types`). */
    public const ACTIONS = [
        'auth.authenticated' => 'login',
        'auth.email_verified' => 'email',
        'auth.password_reset' => 'password',
        'me.settings_updated' => 'settings',
        'me.security_updated' => 'security',
        'account.export_requested' => 'data',
        'device.registered' => 'device',
        'group.created' => 'group',
        'page.created' => 'page',
        'event.created' => 'event',
    ];

    /** Type → icône Bootstrap Icons (prototype `$icons`). */
    public const ICONS = [
        'login' => 'box-arrow-in-right',
        'email' => 'envelope-check',
        'password' => 'key',
        'settings' => 'gear',
        'security' => 'shield-lock',
        'data' => 'download',
        'device' => 'phone',
        'group' => 'people',
        'page' => 'flag',
        'event' => 'calendar-event',
        'forum' => 'chat-square-text',
        'post' => 'pencil-square',
        'invitation' => 'person-plus',
    ];

    private const EXCERPT = 80;

    /**
     * @return list<array{id: string, type: string, icon: string, label: string, detail: string|null, href: string|null, at: string}>
     */
    public function for(User $user, int $limit): array
    {
        $id = (int) $user->getKey();
        $entries = [];

        AuditLog::query()->where('user_id', $id)->whereIn('action', array_keys(self::ACTIONS))
            ->latest('id')->limit($limit)->get(['id', 'action', 'created_at'])
            ->each(function (AuditLog $log) use (&$entries): void {
                $entries[] = $this->entry('audit-'.$log->getKey(), self::ACTIONS[$log->action], null, null, $log->created_at);
            });

        Post::query()->where('user_id', $id)->where('is_anonymous', false)->published()
            ->latest('id')->limit($limit)->get(['id', 'ulid', 'body', 'created_at'])
            ->each(function (Post $post) use (&$entries): void {
                $entries[] = $this->entry('post-'.$post->getKey(), 'post', self::excerpt((string) $post->body), PostLinks::show($post), $post->created_at);
            });

        ForumTopic::query()->where('user_id', $id)->where('is_anonymous', false)->published()
            ->latest('id')->limit($limit)->get(['id', 'ulid', 'title', 'created_at'])
            ->each(function (ForumTopic $topic) use (&$entries): void {
                $entries[] = $this->entry('topic-'.$topic->getKey(), 'forum', (string) $topic->title, ForumLinks::show($topic), $topic->created_at);
            });

        EventRsvp::query()->where('user_id', $id)->where('status', RsvpStatus::Going->value)->whereHas('event')
            ->with('event:id,ulid,title')->latest('id')->limit($limit)->get()
            ->each(function (EventRsvp $rsvp) use (&$entries): void {
                $event = $rsvp->event;
                $entries[] = $this->entry('rsvp-'.$rsvp->getKey(), 'event', $event !== null ? (string) __('profile.activity.going', ['title' => $event->title]) : null, $event !== null ? EventLinks::show($event) : null, $rsvp->updated_at ?? $rsvp->created_at);
            });

        GroupMember::query()->where('user_id', $id)->where('status', MembershipStatus::Active->value)
            ->whereHas('group', static fn ($q) => $q->active())
            ->with('group:id,ulid,name')->latest('id')->limit($limit)->get()
            ->each(function (GroupMember $membership) use (&$entries): void {
                $group = $membership->group;
                $entries[] = $this->entry('group-'.$membership->getKey(), 'group', $group !== null ? (string) __('profile.activity.joined', ['name' => $group->name]) : null, $group !== null ? GroupLinks::show($group) : null, $membership->joined_at ?? $membership->created_at);
            });

        Invitation::query()->where('status', InvitationStatus::Accepted)
            ->where(static fn ($q) => $q->where('from_user_id', $id)->orWhere('to_user_id', $id))
            ->with(['fromUser:id,pseudo', 'toUser:id,pseudo'])->latest('id')->limit($limit)->get()
            ->each(function (Invitation $invitation) use (&$entries, $id): void {
                $other = (int) $invitation->from_user_id === $id ? $invitation->toUser : $invitation->fromUser;
                $entries[] = $this->entry('invitation-'.$invitation->getKey(), 'invitation', $other !== null ? (string) __('profile.activity.connected', ['pseudo' => $other->pseudo]) : null, null, $invitation->responded_at ?? $invitation->updated_at);
            });

        usort($entries, static fn (array $a, array $b): int => strcmp($b['at'], $a['at']));

        return array_slice($entries, 0, $limit);
    }

    /**
     * @return array{id: string, type: string, icon: string, label: string, detail: string|null, href: string|null, at: string}
     */
    private function entry(string $id, string $type, ?string $detail, ?string $href, ?CarbonInterface $at): array
    {
        return [
            'id' => $id,
            'type' => $type,
            'icon' => self::ICONS[$type] ?? 'activity',
            'label' => (string) __('profile.activity.types.'.$type),
            'detail' => $detail,
            'href' => $href,
            'at' => ($at ?? now())->toIso8601String(),
        ];
    }

    private static function excerpt(string $body): ?string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($body)));

        return $text === '' ? null : Str::limit($text, self::EXCERPT);
    }
}
