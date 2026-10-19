<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Application\Notifications\NotificationInbox;
use App\Http\Resources\NotificationResource;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\DatabaseNotification;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Component;

/**
 * Cloche de l'administration (P10-03, prototype partials/admin-top.php #xmNotif, NOTIFICATIONS_INSTRUCTION.md §3) :
 * onglets Non lus · Lues · Toutes, « Tout marquer comme lu », lien vers le journal d'audit, actualisation par polling
 * 30 s (D7 : pas de WebSocket sur cPanel ; Livewire suspend le polling quand l'onglet est caché). Sources : les
 * notifications du compte des catégories de l'équipe — SOS et alertes (dont les alertes routées), modération
 * (contenus signalés), système (conflits de synchro, comptes en attente). Texte neutralisé si la personne masque le
 * contenu sensible (NotificationResource, même règle que l'espace membre et l'API).
 */
final class NotificationBell extends Component
{
    use InteractsWithUi;

    /** Catégories (`notifications.category`) affichées dans la cloche de l'administration. */
    public const CATEGORIES = [
        DatabaseNotification::CATEGORY_SOS,
        DatabaseNotification::CATEGORY_ALERTS,
        DatabaseNotification::CATEGORY_MODERATION,
        DatabaseNotification::CATEGORY_SYSTEM,
    ];

    /** Éléments affichés dans le panneau (les plus récents). */
    public const LIMIT = 10;

    public const POLL_SECONDS = 30;

    public string $tab = NotificationInbox::FILTER_UNREAD;

    public function setTab(string $tab): void
    {
        $this->tab = in_array($tab, NotificationInbox::FILTERS, true) ? $tab : NotificationInbox::FILTER_UNREAD;
    }

    /** Ouvre une notification : la marque comme lue puis suit son lien interne (sinon reste sur place). */
    public function open(string $id, NotificationInbox $inbox): void
    {
        $notification = $this->scoped($inbox, NotificationInbox::FILTER_ALL)->whereKey($id)->first();

        if (! $notification instanceof DatabaseNotification) {
            return;
        }

        $inbox->markRead($notification);
        $url = is_array($notification->data) ? ($notification->data['url'] ?? null) : null;

        if (is_string($url) && self::isInternal($url)) {
            $this->redirect($url);
        }
    }

    public function markAllRead(NotificationInbox $inbox): void
    {
        foreach (self::CATEGORIES as $category) {
            $inbox->markAllRead($this->user(), $category);
        }

        $this->toast((string) __('admin.bell.marked_all'));
    }

    public function render(NotificationInbox $inbox): View
    {
        $request = request();
        $format = LocaleFormatter::make(null, (string) config('xomiton.display_timezone'));
        $items = $this->scoped($inbox, $this->tab)->limit(self::LIMIT)->get()
            ->map(static function (DatabaseNotification $notification) use ($request, $format): array {
                $data = NotificationResource::make($notification)->toArray($request);

                return $data + [
                    'ago' => $format->ago($notification->created_at),
                    'color' => match ($notification->category) {
                        DatabaseNotification::CATEGORY_SOS, DatabaseNotification::CATEGORY_ALERTS => 'danger',
                        DatabaseNotification::CATEGORY_MODERATION => 'warning',
                        default => 'primary',
                    },
                ];
            })->all();

        return view('livewire.admin.notification-bell', [
            'items' => $items,
            'unread' => $this->scoped($inbox, NotificationInbox::FILTER_UNREAD)->count(),
            'canSeeAudit' => $this->user()->can('audit_logs.view'),
        ]);
    }

    /**
     * Notifications du compte, catégories de l'équipe, filtre de l'onglet.
     *
     * @return Builder<DatabaseNotification>
     */
    private function scoped(NotificationInbox $inbox, string $filter): Builder
    {
        return $inbox->query($this->user(), $filter)->whereIn('category', self::CATEGORIES);
    }

    /**
     * Lien interne seulement : chemin relatif `/…` (ni `//hote`, ni `/\hote` que les navigateurs normalisent en
     * `//hote`), ou URL http(s) du même hôte que `app.url`. Barre oblique inverse et caractères de contrôle refusés.
     */
    private static function isInternal(string $url): bool
    {
        if ($url === '' || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7F]/', $url) === 1) {
            return false;
        }

        if (str_starts_with($url, '/')) {
            return ! str_starts_with($url, '//');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            && isset($parts['host'])
            && strtolower($parts['host']) === strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
