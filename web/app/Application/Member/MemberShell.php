<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Application\Notifications\NotificationInbox;
use App\Enums\UserStatus;
use App\Models\ConversationParticipant;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * État de la coquille de l'espace membre et pair (P6-02, D9) : ce que chaque page Inertia reçoit en props partagées
 * (HandleInertiaRequests). Volontairement réduit : pas d'e-mail, de contact de confiance ni de réglage sensible, la page
 * étant écrite dans le HTML (data-page). La vue complète du compte reste `GET /api/v1/me` (UserResource).
 */
final class MemberShell
{
    public function __construct(private readonly NotificationInbox $inbox) {}

    /**
     * Permissions lues par l'interface Vue (menus, boutons) : la décision reste aux policies côté serveur.
     * Noms de RolesAndPermissionsSeeder ; une absence vaut false.
     */
    public const ABILITIES = [
        'social.access',
        'posts.create',
        'polls.create',
        'comments.create',
        'peer_space.access',
        'directory.view',
        'ccc_tools.view',
        'cases.create',
        'case_forms.view_own',
        'sync.view_own',
        'admin.access',
    ];

    /**
     * @return array<string, mixed>
     */
    public function user(User $user): array
    {
        $user->loadMissing(['profile', 'settings']);
        $profile = $user->profile;
        $settings = $user->settings;

        return [
            'id' => $user->getKey(),
            'pseudo' => $user->pseudo,
            'avatar_url' => $user->avatar_url,
            'role' => $user->getRoleNames()->first(),
            'roles' => $user->getRoleNames()->values()->all(),
            'email_verified' => $user->email_verified_at !== null,
            'pending_validation' => $user->status === UserStatus::Pending,
            'profile' => [
                'completion' => (int) ($profile?->completion ?? 0),
                'member_type' => $profile?->member_type?->value,
                'discreet_mode' => (bool) ($profile?->discreet_mode ?? false),
            ],
            'settings' => [
                'theme' => $settings?->theme,
                'language' => $settings?->language,
                'neutral_tab_title' => $this->neutralTitle($user),
                'mask_sensitive_notifications' => (bool) ($settings?->mask_sensitive_notifications ?? false),
                'sos_share_gps' => (bool) ($settings?->sos_share_gps ?? false),
            ],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function abilities(?User $user): array
    {
        $abilities = [];

        foreach (self::ABILITIES as $ability) {
            $abilities[$ability] = $user !== null && $user->can($ability);
        }

        return $abilities;
    }

    /**
     * Compteurs de l'en-tête : notifications non lues (même compte que la cloche, NotificationInbox, P6-07) et
     * conversations ayant au moins un message non lu (participant·e présent·e, conversation hors sourdine, messages
     * des autres non supprimés pour tout le monde).
     *
     * @return array{notifications: int, messages: int}
     */
    public function counters(User $user): array
    {
        return [
            'notifications' => (int) $this->inbox->counts($user)['unread'],
            'messages' => $this->unreadConversations($user),
        ];
    }

    public function neutralTitle(?User $user): bool
    {
        return (bool) ($user?->settings?->neutral_tab_title ?? false);
    }

    private function unreadConversations(User $user): int
    {
        $participants = (new ConversationParticipant)->getTable();
        $messages = (new Message)->getTable();

        return Message::query()
            ->join("$participants as cp", 'cp.conversation_id', '=', "$messages.conversation_id")
            ->where('cp.user_id', $user->getKey())
            ->whereNull('cp.left_at')
            ->where(function (Builder $q): void {
                $q->whereNull('cp.muted_until')->orWhere('cp.muted_until', '<', now());
            })
            ->whereRaw("$messages.id > COALESCE(cp.last_read_message_id, 0)")
            ->where(function (Builder $q) use ($messages, $user): void {
                $q->whereNull("$messages.user_id")->orWhere("$messages.user_id", '!=', $user->getKey());
            })
            ->whereNull("$messages.deleted_for_everyone_at")
            ->distinct()
            ->count("$messages.conversation_id");
    }
}
