<?php

declare(strict_types=1);

namespace App\Application\Member;

use App\Application\Admin\Users\AdminRoles;
use App\Application\Messaging\ConversationDirectory;
use App\Application\Notifications\NotificationInbox;
use App\Application\Shared\KnowledgeBaseAnswerer;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * État de la coquille de l'espace membre et pair (P6-02, D9) : ce que chaque page Inertia reçoit en props partagées
 * (HandleInertiaRequests). Volontairement réduit : pas d'e-mail, de contact de confiance ni de réglage sensible, la page
 * étant écrite dans le HTML (data-page). La vue complète du compte reste `GET /api/v1/me` (UserResource).
 */
final class MemberShell
{
    public function __construct(
        private readonly NotificationInbox $inbox,
        private readonly ConversationDirectory $conversations,
    ) {}

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

    /** Préfixe du cache de la carte profil (par compte et département). */
    private const PROFILE_CARD_CACHE = 'member-shell:card:';

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
            'role' => AdminRoles::of($user),
            'roles' => $user->getRoleNames()->values()->all(),
            // Espaces disponibles (menus du gabarit) : member, peer, admin — rôles cumulables, un seul compte.
            'spaces' => AccountSpaces::for($user, AccountSpaces::SURFACE_WEB),
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

    /**
     * Données du gabarit membre Vue (P6-03, props partagées `shell`) : carte profil du sidenav (département, compteurs
     * réels), bouton SOS flottant (équivalent de x-sos-fab : endpoint, département et commune du profil, jamais de
     * position ; premier numéro d'urgence pour l'appel hors ligne, Settings en cache), lien d'administration si
     * `admin.access`. Département et compteurs : cache court par compte (`xomiton.feed.shell_stats_cache_seconds`, D7 :
     * réponses légères) au lieu d'une lecture de la zone et de trois COUNT à chaque visite.
     *
     * @return array{zone: ?string, stats: array{posts: int, followers: int, following: int}, sos: array{endpoint: string, zone_id: ?int, commune_id: ?int, number: ?string}, admin_url: ?string}
     */
    public function layout(User $user): array
    {
        $number = KnowledgeBaseAnswerer::emergencyNumbers()[0]['number'] ?? null;
        $card = $this->profileCard($user);

        return [
            'zone' => $card['zone'],
            'stats' => $card['stats'],
            'sos' => [
                'endpoint' => route('api.sos.store', [], false),
                'zone_id' => $user->zone_id === null ? null : (int) $user->zone_id,
                'commune_id' => $user->commune_id === null ? null : (int) $user->commune_id,
                'number' => is_string($number) && $number !== '' ? $number : null,
            ],
            'admin_url' => $user->can('admin.access') ? route('admin.dashboard', [], false) : null,
        ];
    }

    /**
     * Carte profil du sidenav : département du profil et compteurs (publications visibles seulement : ni masquées par la
     * modération ni supprimées).
     *
     * @return array{zone: ?string, stats: array{posts: int, followers: int, following: int}}
     */
    private function profileCard(User $user): array
    {
        $read = static function () use ($user): array {
            $user->loadMissing('zone');

            return [
                'zone' => $user->zone?->name,
                'stats' => [
                    'posts' => $user->posts()->published()->count(),
                    'followers' => $user->followers()->count(),
                    'following' => $user->following()->count(),
                ],
            ];
        };

        $seconds = (int) config('xomiton.feed.shell_stats_cache_seconds', 60);

        return $seconds > 0
            ? Cache::remember(self::PROFILE_CARD_CACHE.$user->getKey().':'.($user->zone_id ?? 0), $seconds, $read)
            : $read();
    }

    /** Même requête que la liste des conversations (comptes bloqués, entrée dans un groupe) : badge et liste concordent. */
    private function unreadConversations(User $user): int
    {
        return $this->conversations->unreadConversationsCount($user);
    }
}
