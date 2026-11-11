<?php

declare(strict_types=1);

namespace App\Http\Requests\Member;

use App\Application\Community\Data\NetworkFilters;
use App\Domain\Community\NetworkRules;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Page Vue /qui-suivre (P8-06, who-to-follow.php) : lecture seule et tolérante comme le prototype — onglet
 * (`?tab=recues|envoyees`, sinon Suggestions), recherche (tronquée à `network.search_max`), zone (id d'une zone active)
 * et profil (`?role=membre|pair|equipe`) inconnus reviennent à la valeur par défaut (l'API, elle, répond 422) ;
 * `?invite=<id>` (carte « Qui suivre » du fil) prépare une invitation **sans l'envoyer**.
 */
final class NetworkPageRequest extends FormRequest
{
    /** Valeur du paramètre `role` de l'adresse (prototype) → rôle public. */
    public const ROLES = ['membre' => NetworkRules::ROLE_MEMBER, 'pair' => NetworkRules::ROLE_PEER, 'equipe' => NetworkRules::ROLE_TEAM];

    /** Valeur du paramètre `tab` de l'adresse (prototype) → onglet. */
    public const TABS = ['recues' => 'received', 'envoyees' => 'sent'];

    public function authorize(): bool
    {
        return $this->user() instanceof User;
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
        $user = $this->user();

        abort_unless($user instanceof User, 401);

        return $user;
    }

    /** `suggestions`, `received` ou `sent`. */
    public function tab(): string
    {
        $tab = $this->query('tab');

        return is_string($tab) && isset(self::TABS[$tab]) ? self::TABS[$tab] : 'suggestions';
    }

    public function filters(): NetworkFilters
    {
        $q = $this->query('q');
        $role = $this->query('role');
        $zone = filter_var($this->query('zone'), FILTER_VALIDATE_INT);

        return new NetworkFilters(
            q: is_string($q) && trim($q) !== '' ? mb_substr(trim($q), 0, (int) config('xomiton.network.search_max', 60)) : null,
            zoneId: $zone !== false && Zone::query()->active()->whereKey($zone)->exists() ? (int) $zone : null,
            role: is_string($role) ? (self::ROLES[$role] ?? null) : null,
        );
    }

    /** Valeur `role` de l'adresse pour un rôle public (aller-retour des filtres). */
    public static function roleParam(?string $role): ?string
    {
        $param = array_search($role, self::ROLES, true);

        return is_string($param) ? $param : null;
    }

    public function inviteId(): ?int
    {
        $id = filter_var($this->query('invite'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $id === false ? null : (int) $id;
    }
}
