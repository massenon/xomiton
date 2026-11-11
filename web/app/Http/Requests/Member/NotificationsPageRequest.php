<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Application\Notifications\NotificationTab;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Page Vue /notifications (P8-10, prototype notifications.php `?f=`) : lecture tolérante comme le prototype — filtre
 * inconnu → « Toutes », page invalide → 1. Adresse en français : `?filtre=invitations|evenements|echanges|systeme`.
 */
final class NotificationsPageRequest extends FormRequest
{
    /** Paramètre d'adresse → filtre. */
    public const PARAMS = [
        'invitations' => NotificationTab::Invitations,
        'evenements' => NotificationTab::Events,
        'echanges' => NotificationTab::Exchanges,
        'systeme' => NotificationTab::System,
    ];

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    public function viewer(): User
    {
        /** @var User $user */
        $user = $this->user();

        return $user;
    }

    public function tab(): NotificationTab
    {
        $param = $this->query('filtre');

        return is_string($param) ? (self::PARAMS[$param] ?? NotificationTab::All) : NotificationTab::All;
    }

    /** Paramètre d'adresse d'un filtre (`null` pour « Toutes »). */
    public static function paramOf(NotificationTab $tab): ?string
    {
        $param = array_search($tab, self::PARAMS, true);

        return is_string($param) ? $param : null;
    }
}
