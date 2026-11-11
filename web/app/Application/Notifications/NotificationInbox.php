<?php

declare(strict_types=1);

namespace App\Application\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Cloche de la personne connectée (P6-07, NOTIFICATIONS_INSTRUCTION.md : onglets Non lues · Lues · Toutes) : liste
 * filtrée, compteurs par filtre et par catégorie, lecture, tout marquer comme lu, suppression. Chaque requête est
 * bornée aux notifications du compte : une notification d'un autre compte est introuvable (404), jamais interdite.
 */
final class NotificationInbox
{
    public const FILTER_ALL = 'all';

    public const FILTER_UNREAD = 'unread';

    public const FILTER_READ = 'read';

    /** @var list<string> */
    public const FILTERS = [self::FILTER_ALL, self::FILTER_UNREAD, self::FILTER_READ];

    /**
     * Notifications du compte, les plus récentes d'abord (ordre stable pour la pagination).
     *
     * @return Builder<DatabaseNotification>
     */
    public function query(User $user, string $filter = self::FILTER_ALL, ?string $category = null, ?NotificationTab $tab = null): Builder
    {
        $query = $this->owned($user)->latestFirst();

        match ($filter) {
            self::FILTER_UNREAD => $query->whereNull('read_at'),
            self::FILTER_READ => $query->whereNotNull('read_at'),
            default => null,
        };

        if ($category !== null) {
            $query->inCategory($category);
        }

        $tab?->apply($query);

        return $query;
    }

    /**
     * Compteurs des onglets (toutes, non lues, lues), non lues / total par catégorie (cloche, polling 30 s) et par
     * filtre de la page /notifications (`tabs`, P8-10 : NotificationTab, `all` compris).
     *
     * @return array{all: int, unread: int, read: int, categories: array<string, array{all: int, unread: int}>, tabs: array<string, array{all: int, unread: int}>}
     */
    public function counts(User $user): array
    {
        $rows = $this->owned($user)
            ->toBase()
            ->selectRaw('category, type, COUNT(*) as total, SUM(CASE WHEN read_at IS NULL THEN 1 ELSE 0 END) as unread')
            ->groupBy('category', 'type')
            ->get();

        $categories = array_fill_keys(DatabaseNotification::CATEGORIES, ['all' => 0, 'unread' => 0]);
        $tabs = array_fill_keys(NotificationTab::values(), ['all' => 0, 'unread' => 0]);
        $all = 0;
        $unread = 0;

        foreach ($rows as $row) {
            $total = (int) $row->total;
            $pending = (int) $row->unread;
            $all += $total;
            $unread += $pending;
            $category = is_string($row->category) && $row->category !== '' ? $row->category : DatabaseNotification::CATEGORY_SYSTEM;
            $categories[$category] = [
                'all' => ($categories[$category]['all'] ?? 0) + $total,
                'unread' => ($categories[$category]['unread'] ?? 0) + $pending,
            ];
            $tab = NotificationTab::of($category, is_string($row->type) ? $row->type : null)->value;
            $tabs[$tab]['all'] += $total;
            $tabs[$tab]['unread'] += $pending;
        }

        $tabs[NotificationTab::All->value] = ['all' => $all, 'unread' => $unread];

        return ['all' => $all, 'unread' => $unread, 'read' => $all - $unread, 'categories' => $categories, 'tabs' => $tabs];
    }

    public function find(User $user, string $id): ?DatabaseNotification
    {
        return $this->owned($user)->whereKey($id)->first();
    }

    public function markRead(DatabaseNotification $notification): DatabaseNotification
    {
        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
        }

        return $notification;
    }

    /**
     * @return int nombre de notifications passées à « lue »
     */
    public function markAllRead(User $user, ?string $category = null, ?NotificationTab $tab = null): int
    {
        $query = $this->owned($user)->whereNull('read_at');

        if ($category !== null) {
            $query->inCategory($category);
        }

        $tab?->apply($query);

        return $query->update(['read_at' => now()]);
    }

    public function delete(DatabaseNotification $notification): void
    {
        $notification->delete();
    }

    /**
     * @return Builder<DatabaseNotification>
     */
    private function owned(User $user): Builder
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->getKey());
    }
}
