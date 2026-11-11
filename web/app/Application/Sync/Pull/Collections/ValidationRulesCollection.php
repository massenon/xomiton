<?php

declare(strict_types=1);

namespace App\Application\Sync\Pull\Collections;

use App\Application\Cases\PeerSpace;
use App\Application\Sync\Pull\EloquentSyncCollection;
use App\Models\User;
use App\Models\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * `validation_rules` : règles de validation actives appliquées par le formulaire de collecte hors connexion (P9-01,
 * P9-04), pour les comptes qui collectent (espace pair actif, PeerSpace RG-03, et `cases.create`) ; désactivée :
 * retirée de l'appareil.
 */
final class ValidationRulesCollection extends EloquentSyncCollection
{
    public const NAME = 'validation_rules';

    public function name(): string
    {
        return self::NAME;
    }

    public function availableTo(User $user): bool
    {
        return PeerSpace::allows($user) && $user->can('cases.create');
    }

    protected function model(): string
    {
        return ValidationRule::class;
    }

    protected function visible(User $user): Builder
    {
        return ValidationRule::query()->active();
    }

    public function payload(Model $row, Request $request): array
    {
        /** @var ValidationRule $row */
        return [
            'id' => (int) $row->getKey(),
            'key' => (string) $row->key,
            'label' => (string) $row->label,
            'description' => $row->description,
            'is_locked' => (bool) $row->is_locked,
            'params' => $row->params ?? [],
            'valid_example' => $row->valid_example,
            'invalid_example' => $row->invalid_example,
            'position' => (int) $row->position,
        ];
    }
}
