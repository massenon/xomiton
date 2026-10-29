<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Application\Notifications\NotificationInbox;
use App\Http\Resources\NotificationResource;
use App\Models\DatabaseNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifications d'astreinte (`admin/notifications`, P15-16) : la cloche du compte restreinte aux catégories de
 * l'administration — SOS, alertes, modération, système, sécurité — avec le nombre de non lues par catégorie ; marquer
 * tout comme lu. Même NotificationInbox que `GET notifications` (jamais les notifications d'un autre compte).
 */
final class NotificationController extends AdminApiController
{
    /** @var list<string> */
    private const CATEGORIES = [
        DatabaseNotification::CATEGORY_SOS,
        DatabaseNotification::CATEGORY_ALERTS,
        DatabaseNotification::CATEGORY_MODERATION,
        DatabaseNotification::CATEGORY_SYSTEM,
        DatabaseNotification::CATEGORY_SECURITY,
    ];

    public function __construct(private readonly NotificationInbox $inbox) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        $user->loadMissing('settings');
        $filters = $request->validate([
            'filter' => ['sometimes', 'string', 'in:'.implode(',', NotificationInbox::FILTERS)],
            'category' => ['sometimes', 'string', 'in:'.implode(',', self::CATEGORIES)],
        ]);

        $query = $this->inbox->query($user, $filters['filter'] ?? NotificationInbox::FILTER_ALL, $filters['category'] ?? null)
            ->whereIn('category', self::CATEGORIES);
        $page = $query->paginate($this->perPage($request));
        $counts = $this->inbox->counts($user)['categories'];

        return $this->page(
            $page,
            $page->getCollection()->map(static fn ($notification): array => NotificationResource::make($notification)->resolve($request))->values()->all(),
            ['unread_by_category' => array_map(static fn (string $category): int => (int) ($counts[$category]['unread'] ?? 0), array_combine(self::CATEGORIES, self::CATEGORIES))],
        );
    }

    public function readAll(Request $request): JsonResponse
    {
        $user = $this->admin($request);
        $marked = 0;

        foreach (self::CATEGORIES as $category) {
            $marked += $this->inbox->markAllRead($user, $category);
        }

        return $this->data(['marked' => $marked]);
    }
}
