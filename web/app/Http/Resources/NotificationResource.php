<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Notification de la cloche (P6-07) : texte tel qu'enregistré par XomitonNotification::toArray(), **neutralisé à la
 * lecture** si elle est sensible et que la personne masque aujourd'hui les aperçus (`mask_sensitive_notifications`) —
 * une notification reçue avant l'activation du masquage ne ressort donc jamais en clair. `type` = nom court de la
 * classe (même valeur que `data.type` de la charge push), `data` = compléments d'ouverture profonde (identifiants).
 *
 * @mixin DatabaseNotification
 */
final class NotificationResource extends JsonResource
{
    /** Clés de `data` déjà exposées au premier niveau. */
    private const CONTENT_KEYS = ['title', 'body', 'url', 'icon', 'category', 'sensitive'];

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var DatabaseNotification $notification */
        $notification = $this->resource;
        $data = is_array($notification->data) ? $notification->data : [];
        $sensitive = (bool) $notification->is_sensitive;
        $title = (string) ($data['title'] ?? '');
        $body = (string) ($data['body'] ?? '');

        if ($sensitive && self::masks($request)) {
            $title = (string) __('notifications.discreet.title');
            $body = (string) __('notifications.discreet.body');
        }

        return [
            'id' => (string) $notification->getKey(),
            'type' => Str::snake(class_basename((string) $notification->type)),
            'category' => $notification->category ?? ($data['category'] ?? null),
            'title' => $title,
            'body' => $body,
            'url' => is_string($data['url'] ?? null) ? $data['url'] : null,
            'icon' => is_string($data['icon'] ?? null) ? $data['icon'] : 'bell',
            'sensitive' => $sensitive,
            'read' => $notification->read_at !== null,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
            'data' => (object) array_diff_key($data, array_flip(self::CONTENT_KEYS)),
        ];
    }

    private static function masks(Request $request): bool
    {
        $user = $request->user('sanctum') ?? $request->user();

        return $user instanceof User && (bool) $user->settings?->mask_sensitive_notifications;
    }
}
