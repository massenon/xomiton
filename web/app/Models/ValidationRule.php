<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ValidationRuleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

/**
 * Règle de validation administrable (Administration › Règles de validation). La clé est stable et sert
 * de référence aux Form Requests et au JS (`XM.rules[key]`) ; `params` porte les bornes de plausibilité
 * ou les champs de détection de doublons. `is_locked` protège `required`, jamais désactivable.
 */
class ValidationRule extends Model
{
    /** @use HasFactory<ValidationRuleFactory> */
    use HasFactory;

    public const KEY_REQUIRED = 'required';

    public const KEY_PLAUSIBILITY = 'plausibility';

    public const KEY_DUPLICATES = 'duplicates';

    /** @var list<string> */
    protected $fillable = [
        'key',
        'label',
        'description',
        'is_active',
        'is_locked',
        'params',
        'valid_example',
        'invalid_example',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_locked' => 'boolean',
            'params' => 'array',
            'position' => 'integer',
        ];
    }

    /** @param  Builder<ValidationRule>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Règles verrouillées (`required`) : toujours actives, l'administration ne peut pas les désactiver.
     *
     * @param  Builder<ValidationRule>  $query
     */
    public function scopeLocked(Builder $query): void
    {
        $query->where('is_locked', true);
    }

    /**
     * Règles que l'administration peut désactiver (tout sauf `required`).
     *
     * @param  Builder<ValidationRule>  $query
     */
    public function scopeUnlockable(Builder $query): void
    {
        $query->where('is_locked', false);
    }

    /** @param  Builder<ValidationRule>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('key');
    }

    /** @param  Builder<ValidationRule>  $query */
    public function scopeWithKey(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }

    /**
     * Paramètre de la règle (notation pointée : `lat.0`), ou la valeur par défaut.
     */
    public function param(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->params ?? [], $key, $default);
    }

    /** Une règle verrouillée reste active quoi qu'en dise `is_active`. */
    public function isActive(): bool
    {
        return $this->is_locked || $this->is_active;
    }

    public function canBeDisabled(): bool
    {
        return ! $this->is_locked;
    }

    /**
     * Une règle absente du catalogue est considérée active : on ne relâche jamais un contrôle par oubli.
     */
    public static function isEnabled(string $key): bool
    {
        $rule = static::query()->withKey($key)->first(['is_active', 'is_locked']);

        return $rule === null || $rule->isActive();
    }
}
