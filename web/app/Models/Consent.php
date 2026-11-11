<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ConsentKind;
use Database\Factories\ConsentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consent extends Model
{
    /** @use HasFactory<ConsentFactory> */
    use HasFactory;

    /**
     * Une ligne par événement : jamais mise à jour (dictionnaire : [sans updated_at]).
     */
    public const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'email',
        'kind',
        'version',
        'granted',
        'ip',
        'user_agent',
    ];

    /**
     * L'adresse IP et le navigateur ne sortent jamais hors de l'administration.
     *
     * @var list<string>
     */
    protected $hidden = [
        'ip',
        'user_agent',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => ConsentKind::class,
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<Consent>  $query
     */
    public function scopeOfKind(Builder $query, ConsentKind $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * @param  Builder<Consent>  $query
     */
    public function scopeGranted(Builder $query): void
    {
        $query->where('granted', true);
    }

    /**
     * Dernier événement de consentement d'un compte pour un type donné.
     *
     * @param  Builder<Consent>  $query
     */
    public function scopeLatestFor(Builder $query, User $user, ConsentKind $kind): void
    {
        $query->where('user_id', $user->id)->where('kind', $kind)->latest('id')->limit(1);
    }
}
