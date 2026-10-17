<?php

declare(strict_types=1);

namespace App\Application\Auth\Notifications;

use App\Models\DatabaseNotification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Route;

/**
 * Cloche « compte pair validé » (NOTIFICATIONS §2) ; l'e-mail `compte-valide` part séparément via EmailTemplateService.
 * Catégorie sécurité du compte : non désactivable.
 */
class PeerAccountValidated extends Notification
{
    use Queueable;

    public function __construct(private readonly User $validatedBy) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** Catégorie de préférences (`user_settings.notification_prefs`) : sécurité du compte, jamais désactivable. */
    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
    }

    /** Aucune donnée sensible : le texte peut s'afficher tel quel sur l'écran de verrouillage (NOTIFICATIONS §4.1). */
    public function isSensitive(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => (string) __('auth.peer_validated.title'),
            'body' => (string) __('auth.peer_validated.body'),
            'url' => Route::has('espace-pair') ? route('espace-pair') : url('/espace-pair'),
            'category' => $this->category(),
            'validated_by' => $this->validatedBy->getKey(),
        ];
    }
}
