<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\SettingFactory;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Paramètre de la plateforme (DATABASE_DICTIONARY.md §10 `settings`) : clé unique, valeur JSON typée.
 * Lecture par `Setting::get()` (ou App\Support\Settings) depuis un cache de 24 h invalidé à chaque écriture ;
 * les clés de type `secret` sont chiffrées avant stockage et ne sont déchiffrées qu'à la lecture, jamais en cache.
 */
class Setting extends Model
{
    /** @use HasFactory<SettingFactory> */
    use HasFactory;

    public const TYPE_BOOL = 'bool';

    public const TYPE_INT = 'int';

    public const TYPE_STRING = 'string';

    public const TYPE_JSON = 'json';

    public const TYPE_SECRET = 'secret';

    /** @var list<string> */
    public const TYPES = [self::TYPE_BOOL, self::TYPE_INT, self::TYPE_STRING, self::TYPE_JSON, self::TYPE_SECRET];

    public const GROUP_GENERAL = 'general';

    public const GROUP_SECURITY = 'securite';

    public const GROUP_SMTP = 'smtp';

    public const GROUP_IDENTITY = 'identite';

    public const GROUP_NEWSLETTER = 'newsletter';

    public const GROUP_SYNC = 'synchro';

    public const GROUP_ASSISTANT = 'assistant';

    public const GROUP_MAINTENANCE = 'maintenance';

    public const GROUP_REGISTRATIONS = 'inscriptions';

    public const GROUP_OPTIMIZATION = 'optimisation';

    /** @var list<string> */
    public const GROUPS = [
        self::GROUP_GENERAL, self::GROUP_SECURITY, self::GROUP_SMTP, self::GROUP_IDENTITY, self::GROUP_NEWSLETTER,
        self::GROUP_SYNC, self::GROUP_ASSISTANT, self::GROUP_MAINTENANCE, self::GROUP_REGISTRATIONS, self::GROUP_OPTIMIZATION,
    ];

    public const CACHE_KEY = 'xomiton.settings';

    /** Durée de vie du cache (secondes) : 24 h, comme les référentiels (DEVELOPMENT_STANDARDS.md §9). */
    public const CACHE_TTL = 86400;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'group',
        'type',
        'value',
        'is_public',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (): bool => static::flushCache());
        static::deleted(fn (): bool => static::flushCache());
    }

    /**
     * Le mutateur de `value` dépend de `type` : on garantit que le type est affecté en premier.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function fill(array $attributes): static
    {
        if (array_key_exists('type', $attributes)) {
            $attributes = ['type' => $attributes['type']] + $attributes;
        }

        return parent::fill($attributes);
    }

    /**
     * Valeur décodée (et déchiffrée si `secret`) ; stockée en JSON, chiffrée pour les secrets.
     */
    protected function value(): Attribute
    {
        return Attribute::make(
            get: fn (?string $raw): mixed => static::decodeRaw($raw, (string) ($this->attributes['type'] ?? self::TYPE_JSON)),
            set: fn (mixed $value): array => [
                'value' => static::encodeRaw($value, (string) ($this->attributes['type'] ?? static::inferType($value))),
            ],
        );
    }

    /**
     * Administrateur·rice ayant fait la dernière modification.
     *
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Paramètres exposés au front (API publique, Vue), jamais les secrets.
     *
     * @param  Builder<Setting>  $query
     */
    public function scopePublic(Builder $query): void
    {
        $query->where('is_public', true)->where('type', '!=', self::TYPE_SECRET);
    }

    /**
     * @param  Builder<Setting>  $query
     */
    public function scopeInGroup(Builder $query, string $group): void
    {
        $query->where('group', $group);
    }

    /**
     * @param  Builder<Setting>  $query
     */
    public function scopeForKey(Builder $query, string $key): void
    {
        $query->where('key', $key);
    }

    public function isSecret(): bool
    {
        return $this->type === self::TYPE_SECRET;
    }

    /**
     * Lecture d'un paramètre depuis le cache ; `$default` si la clé est absente ou sa valeur nulle.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::cached()[$key] ?? null;

        if ($row === null) {
            return $default;
        }

        return static::decodeRaw($row['value'], $row['type']) ?? $default;
    }

    /**
     * Écriture d'un paramètre ; une clé inconnue est créée dans le groupe `general` avec un type déduit de la valeur.
     */
    public static function set(string $key, mixed $value, ?int $updatedBy = null): self
    {
        $setting = static::query()->firstOrNew(['key' => $key]);

        if (! $setting->exists) {
            $setting->group = self::GROUP_GENERAL;
            $setting->type = static::inferType($value);
        }

        $setting->value = $value;
        $setting->updated_by = $updatedBy;
        $setting->save();

        return $setting;
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, static::cached());
    }

    /**
     * Toutes les valeurs d'un groupe, clé => valeur décodée.
     *
     * @return array<string, mixed>
     */
    public static function group(string $group): array
    {
        $values = [];

        foreach (static::cached() as $key => $row) {
            if ($row['group'] === $group) {
                $values[$key] = static::decodeRaw($row['value'], $row['type']);
            }
        }

        return $values;
    }

    /**
     * Valeurs publiques (`is_public`), sans aucun secret, pour le front.
     *
     * @return array<string, mixed>
     */
    public static function publicValues(): array
    {
        $values = [];

        foreach (static::cached() as $key => $row) {
            if ($row['public'] && $row['type'] !== self::TYPE_SECRET) {
                $values[$key] = static::decodeRaw($row['value'], $row['type']);
            }
        }

        return $values;
    }

    public static function flushCache(): bool
    {
        return Cache::forget(self::CACHE_KEY);
    }

    /**
     * Lignes brutes (valeur encore chiffrée pour les secrets) mises en cache 24 h.
     *
     * @return array<string, array{type: string, group: string, public: bool, value: ?string}>
     */
    protected static function cached(): array
    {
        /** @var array<string, array{type: string, group: string, public: bool, value: ?string}> $rows */
        $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $rows = [];

            foreach (static::query()->get(['key', 'group', 'type', 'is_public', 'value']) as $setting) {
                $rows[$setting->key] = [
                    'type' => $setting->type,
                    'group' => $setting->group,
                    'public' => $setting->is_public,
                    'value' => $setting->getRawOriginal('value'),
                ];
            }

            return $rows;
        });

        return $rows;
    }

    /**
     * Type déduit d'une valeur PHP lors de la création d'une clé inconnue.
     */
    public static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => self::TYPE_BOOL,
            is_int($value) => self::TYPE_INT,
            is_array($value) => self::TYPE_JSON,
            default => self::TYPE_STRING,
        };
    }

    /**
     * Encodage pour la colonne `value` : JSON, ou JSON d'une chaîne chiffrée pour un secret ; `null` reste `NULL`.
     */
    protected static function encodeRaw(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = match ($type) {
            self::TYPE_BOOL => (bool) $value,
            self::TYPE_INT => (int) $value,
            self::TYPE_STRING => (string) $value,
            default => $value,
        };

        $json = json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

        if ($type === self::TYPE_SECRET) {
            return json_encode(Crypt::encryptString($json), JSON_THROW_ON_ERROR);
        }

        return $json;
    }

    /**
     * Décodage de la colonne `value` ; un secret illisible (APP_KEY changée) vaut `null` plutôt qu'une exception.
     */
    protected static function decodeRaw(?string $raw, string $type): mixed
    {
        if ($raw === null) {
            return null;
        }

        $decoded = json_decode($raw, true);

        if ($type !== self::TYPE_SECRET || ! is_string($decoded)) {
            return $decoded;
        }

        try {
            return json_decode(Crypt::decryptString($decoded), true);
        } catch (DecryptException) {
            return null;
        }
    }
}
