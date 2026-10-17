<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AgeRange;
use App\Enums\MemberType;
use App\Enums\ProfileVisibility;
use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'pronouns',
        'age_range',
        'member_type',
        'gender_identity',
        'orientation',
        'bio',
        'completion',
        'avatar_path',
        'cover_path',
        'discreet_mode',
        'trusted_contact_phone',
        'trusted_contact_name',
        'website',
        'visibility',
        'show_zone',
        'searchable',
    ];

    /**
     * Données sensibles : jamais sérialisées par défaut (RG-02, consentement sensitive_data).
     *
     * @var list<string>
     */
    protected $hidden = [
        'gender_identity',
        'orientation',
        'trusted_contact_phone',
        'trusted_contact_name',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age_range' => AgeRange::class,
            'member_type' => MemberType::class,
            'visibility' => ProfileVisibility::class,
            'gender_identity' => 'encrypted',
            'orientation' => 'encrypted',
            'trusted_contact_phone' => 'encrypted',
            'completion' => 'integer',
            'discreet_mode' => 'boolean',
            'show_zone' => 'boolean',
            'searchable' => 'boolean',
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
     * @param  Builder<Profile>  $query
     */
    public function scopePeers(Builder $query): void
    {
        $query->where('member_type', MemberType::Peer);
    }

    /**
     * Profils visibles par un·e membre connecté·e (public ou communauté), hors profils réservés
     * aux abonné·es ou privés.
     *
     * @param  Builder<Profile>  $query
     */
    public function scopeVisibleToCommunity(Builder $query): void
    {
        $query->whereIn('visibility', [ProfileVisibility::Public, ProfileVisibility::Community]);
    }

    /**
     * Profils visibles sans connexion (visibilité « tout le monde »).
     *
     * @param  Builder<Profile>  $query
     */
    public function scopePubliclyVisible(Builder $query): void
    {
        $query->where('visibility', ProfileVisibility::Public);
    }

    /**
     * Profils proposés dans les suggestions « Qui suivre » et la recherche.
     *
     * @param  Builder<Profile>  $query
     */
    public function scopeSearchable(Builder $query): void
    {
        $query->where('searchable', true);
    }

    public function isPeer(): bool
    {
        return $this->member_type === MemberType::Peer;
    }
}
