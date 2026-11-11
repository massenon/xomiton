<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Réponse mémorisée par le middleware `idempotent` (P5-09, ADR-32, DATABASE_DICTIONARY.md §10 `idempotency_keys`) :
 * même clé + même route + même acteur → même réponse rejouée. `status_code` nul = requête d'origine en cours.
 * Aucun lien au compte ni au chemin concret : `scope` est une HMAC (acteur, méthode, chemin), `route` le gabarit de route ;
 * en-têtes (`Location` peut porter un code de suivi ou un ULID) et corps rejoués sont chiffrés (ADR-33, RG-01 / RG-02).
 * Écrite et lue par App\Application\Shared\Idempotency\IdempotencyStore ; purgée par `idempotency:purge`.
 *
 * @property int $id
 * @property string $idempotency_key
 * @property string $scope
 * @property string $method
 * @property string $route
 * @property string $request_hash
 * @property int|null $status_code
 * @property array<string, string>|null $response_headers
 * @property string|null $response_body
 * @property Carbon $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class IdempotencyKey extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'idempotency_key',
        'scope',
        'method',
        'route',
        'request_hash',
        'status_code',
        'response_headers',
        'response_body',
        'expires_at',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = ['response_headers', 'response_body'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            // `Location` et le corps rejoués peuvent porter un code de suivi, un ULID de SOS ou des données de la personne (ADR-33).
            'response_headers' => 'encrypted:array',
            'response_body' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Lignes dont la fenêtre de rejeu est passée.
     *
     * @param  Builder<IdempotencyKey>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('expires_at', '<=', now());
    }

    public function isCompleted(): bool
    {
        return $this->status_code !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->lessThanOrEqualTo(now());
    }
}
